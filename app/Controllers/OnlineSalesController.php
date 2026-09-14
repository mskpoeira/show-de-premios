<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\PixService;
use App\Services\TicketService;
use PDO;

class OnlineSalesController
{
    public function showCheckout(): void
    {
        $pdo = Database::getConnection();
        $event = $this->activeDigitalEvent($pdo);
        if (!$event) {
            View::render('sales/no_event', []);
            return;
        }

        $stmtPrizes = $pdo->prepare("SELECT * FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC");
        $stmtPrizes->execute([$event['id']]);

        View::render('sales/checkout', [
            'event' => $event,
            'prizes' => $stmtPrizes->fetchAll(PDO::FETCH_ASSOC),
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_error']);
    }

    public function processCheckout(): void
    {
        if (!RateLimiter::allow(RateLimiter::clientKey('checkout'), 20, 300)) {
            http_response_code(429);
            echo 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $cpf = preg_replace('/\D/', '', (string)($_POST['cpf'] ?? ''));
        $phoneRaw = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? 1);
        $wantsEmail = !empty($_POST['wants_email']) ? 1 : 0;

        if (mb_strlen($name) < 3) {
            return $this->checkoutError('Por favor, informe seu nome completo.');
        }
        if (!$this->isValidCpf($cpf)) {
            return $this->checkoutError('Por favor, informe um CPF válido.');
        }

        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 13) {
            return $this->checkoutError('Por favor, informe um telefone/WhatsApp válido.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->checkoutError('Informe um e-mail válido ou deixe o campo vazio.');
        }
        if ($quantity < 1 || $quantity > 50) {
            return $this->checkoutError('A quantidade permitida por pedido é de 1 a 50 cartelas.');
        }

        if (str_starts_with($phoneDigits, '55') && strlen($phoneDigits) >= 12) {
            $phoneNormalized = '+' . $phoneDigits;
        } else {
            $phoneNormalized = '+55' . $phoneDigits;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $event = $this->activeDigitalEvent($pdo);
            if (!$event) {
                throw new \RuntimeException('Nenhum evento com venda digital ativa no momento.');
            }

            $stmtBuyer = $pdo->prepare("SELECT id FROM buyers WHERE cpf = ? ORDER BY id DESC LIMIT 1");
            $stmtBuyer->execute([$cpf]);
            $buyerId = (int)($stmtBuyer->fetchColumn() ?: 0);

            if ($buyerId <= 0) {
                $stmt = $pdo->prepare("INSERT INTO buyers (name, cpf, phone, email, wants_email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([$name, $cpf, $phoneNormalized, $email !== '' ? $email : null, $wantsEmail]);
                $buyerId = (int)$pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare("UPDATE buyers SET name = ?, phone = ?, email = ?, wants_email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $phoneNormalized, $email !== '' ? $email : null, $wantsEmail, $buyerId]);
            }

            $singlePrice = (float)$event['single_price'];
            $bundleQty = max(2, (int)$event['bundle_qty']);
            $bundlePrice = (float)$event['bundle_price'];
            $bundles = intdiv($quantity, $bundleQty);
            $remainder = $quantity % $bundleQty;
            $totalAmount = round(($bundles * $bundlePrice) + ($remainder * $singlePrice), 2);

            $orderCode = 'PED-' . strtoupper(bin2hex(random_bytes(16)));
            $pricingSnapshot = json_encode([
                'single_price' => $singlePrice,
                'bundle_quantity' => $bundleQty,
                'bundle_price' => $bundlePrice,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
            ], JSON_UNESCAPED_UNICODE);

            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (event_id, buyer_id, order_code, quantity, total_amount, status, payment_method, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'PENDING', 'PIX', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtOrder->execute([(int)$event['id'], $buyerId, $orderCode, $quantity, $totalAmount, $pricingSnapshot]);
            $orderId = (int)$pdo->lastInsertId();

            $stmtBatch = $pdo->prepare("SELECT id FROM event_batches WHERE event_id = ? AND is_locked IN (0,1) ORDER BY id ASC LIMIT 1");
            $stmtBatch->execute([(int)$event['id']]);
            $batchId = (int)($stmtBatch->fetchColumn() ?: 0);
            if ($batchId <= 0) {
                throw new \RuntimeException('Nenhum lote de cartelas digitais disponível para este evento.');
            }

            $unitPrice = round($totalAmount / $quantity, 2);
            for ($i = 0; $i < $quantity; $i++) {
                $ticket = TicketService::createTicket((int)$event['id'], $batchId, $buyerId, $orderId, 'RESERVED', $pdo);
                $pdo->prepare("INSERT INTO order_items (order_id, ticket_id, unit_price, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
                    ->execute([$orderId, $ticket['id'], $unitPrice]);
            }

            $config = PixService::getConfig();
            $pixPayload = '';
            if (!empty($config['key'])) {
                $pixPayload = PixService::createPayload(
                    (string)$config['key'],
                    (string)($config['receiver'] ?: 'Show de Premios'),
                    (string)($config['city'] ?? 'Ubatuba'),
                    'Show de Premios ' . $orderCode,
                    $totalAmount,
                    substr(str_replace('-', '', $orderCode), 0, 25)
                );
            }

            $stmtPay = $pdo->prepare("INSERT INTO payments (order_id, payment_code, amount, method, status, pix_payload, created_at, updated_at) VALUES (?, ?, ?, 'PIX', 'PENDING', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmtPay->execute([$orderId, $orderCode, $totalAmount, $pixPayload]);

            AuditService::log('DIGITAL_ORDER_CREATE', 'orders', $orderId, null, [
                'order_code' => $orderCode,
                'event_id' => (int)$event['id'],
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
            ]);

            $pdo->commit();
            Response::redirect('/pedido/' . rawurlencode($orderCode));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Checkout] ' . $e->getMessage());
            $this->checkoutError('Não foi possível concluir o pedido. Tente novamente ou procure um operador.');
        }
    }

    public function showOrder(string $orderCode): void
    {
        if (!RateLimiter::allow(RateLimiter::clientKey('order-view'), 120, 300)) {
            http_response_code(429);
            echo 'Muitas consultas. Aguarde e tente novamente.';
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT o.*, b.name AS buyer_name, b.cpf AS buyer_cpf, b.phone AS buyer_phone, b.email AS buyer_email,
                   e.name AS event_name, p.amount, p.pix_payload, p.status AS payment_status, p.paid_at
            FROM orders o
            JOIN buyers b ON b.id = o.buyer_id
            JOIN events e ON e.id = o.event_id
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.order_code = ?
            LIMIT 1
        ");
        $stmt->execute([$orderCode]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'Pedido não encontrado.']);
            return;
        }

        $isAuthenticated = Auth::check();
        $isPaid = $order['status'] === 'PAID';
        $canViewTicketSecrets = $isPaid || $isAuthenticated;

        $tickets = [];
        if ($canViewTicketSecrets) {
            $stmtTickets = $pdo->prepare("SELECT id, ticket_number, check_code, secure_token, status, print_count FROM tickets WHERE order_id = ? ORDER BY id ASC");
            $stmtTickets->execute([(int)$order['id']]);
            $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!$isAuthenticated) {
            $order['buyer_name'] = $this->maskName((string)$order['buyer_name']);
            $order['buyer_phone'] = null;
            $order['buyer_email'] = null;
        }

        View::render('sales/order', [
            'order' => $order,
            'tickets' => $tickets,
            'is_paid' => $isPaid,
            'canViewTicketSecrets' => $canViewTicketSecrets,
            'isAuthenticated' => $isAuthenticated,
            'user' => Auth::user(),
        ]);
    }

    public function orderStatusJson(string $orderCode): void
    {
        if (!RateLimiter::allow(RateLimiter::clientKey('order-status'), 180, 300)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'RATE_LIMITED', 'is_paid' => false]);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_code = ? LIMIT 1");
        $stmt->execute([$orderCode]);
        $status = $stmt->fetchColumn();
        echo json_encode(['status' => $status ?: 'NOT_FOUND', 'is_paid' => $status === 'PAID'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function confirmPayment(string $orderCode): void
    {
        if (!Auth::check() || !Auth::canConfirmPayments()) {
            http_response_code(403);
            Response::redirect('/painel', null, 'Seu perfil não pode confirmar pagamentos.');
        }

        $user = Auth::user();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT id, event_id, buyer_id, total_amount, status FROM orders WHERE order_code = ? LIMIT 1");
            $stmt->execute([$orderCode]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new \RuntimeException('Pedido não encontrado.');
            }

            if ($order['status'] === 'PAID') {
                $pdo->rollBack();
                Response::redirect('/pedido/' . rawurlencode($orderCode));
            }
            if (!in_array($order['status'], ['PENDING', 'RESERVED'], true)) {
                throw new \RuntimeException('O pedido não está em situação que permita confirmação de pagamento.');
            }

            $pdo->prepare("UPDATE orders SET status = 'PAID', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$order['id']]);
            $pdo->prepare("UPDATE payments SET status = 'PAID', paid_at = CURRENT_TIMESTAMP, confirmed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ? AND status = 'PENDING'")
                ->execute([(int)$user['id'], $order['id']]);
            $pdo->prepare("UPDATE tickets SET status = 'VALID', updated_at = CURRENT_TIMESTAMP WHERE order_id = ? AND status IN ('RESERVED','PENDING')")
                ->execute([$order['id']]);

            $stmtTickets = $pdo->prepare("SELECT id FROM tickets WHERE order_id = ? AND status = 'VALID'");
            $stmtTickets->execute([$order['id']]);
            foreach ($stmtTickets->fetchAll(PDO::FETCH_COLUMN) as $ticketId) {
                TicketService::initGameStateForTicket((int)$ticketId, (int)$order['event_id'], $pdo);
            }

            AuditService::log('PAYMENT_CONFIRM', 'orders', (int)$order['id'], ['previous_status' => $order['status']], [
                'new_status' => 'PAID',
                'confirmed_by_user_id' => (int)$user['id'],
                'order_code' => $orderCode,
            ]);

            $pdo->commit();
            EmailService::sendTicketsByEmail((int)$order['id']);
            Response::redirect('/pedido/' . rawurlencode($orderCode), 'Pagamento confirmado manualmente por operador autorizado.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Payment confirmation] ' . $e->getMessage());
            Response::redirect('/pedido/' . rawurlencode($orderCode), null, 'Não foi possível confirmar o pagamento.');
        }
    }

    private function activeDigitalEvent(PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->query("SELECT * FROM events WHERE status = 'ACTIVE' AND COALESCE(modality, 'HYBRID') IN ('DIGITAL_ONLY','HYBRID') ORDER BY id DESC LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $stmt = $pdo->query("SELECT * FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    private function checkoutError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
        Response::redirect('/comprar');
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (!$parts) {
            return 'Comprador';
        }
        $first = array_shift($parts);
        $masked = [$first];
        foreach ($parts as $part) {
            $masked[] = mb_substr($part, 0, 1) . '***';
        }
        return implode(' ', $masked);
    }

    private function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += ((int)$cpf[$i]) * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$t] !== $digit) {
                return false;
            }
        }
        return true;
    }
}
