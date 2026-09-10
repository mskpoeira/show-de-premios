<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\PixService;
use App\Services\TicketService;
use PDO;

class OnlineSalesController
{
    /**
     * Página inicial pública de compra de cartelas
     * Rota: GET /comprar
     */
    public function showCheckout(): void
    {
        $pdo = Database::getConnection();

        // Busca o evento ativo
        $stmtEvent = $pdo->query("SELECT * FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            View::render('sales/no_event', []);
            return;
        }

        // Busca os prêmios do evento
        $stmtPrizes = $pdo->prepare("SELECT * FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC");
        $stmtPrizes->execute([$event['id']]);
        $prizes = $stmtPrizes->fetchAll(PDO::FETCH_ASSOC);

        View::render('sales/checkout', [
            'event' => $event,
            'prizes' => $prizes,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_error']);
    }

    /**
     * Processamento do pedido de compra de cartelas
     * Rota: POST /comprar
     */
    public function processCheckout(): void
    {
        $pdo = Database::getConnection();

        $name = trim($_POST['name'] ?? '');
        $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $phoneRaw = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);
        $wantsEmail = !empty($_POST['wants_email']) ? 1 : 0;

        // Validações
        if (empty($name)) {
            $_SESSION['flash_error'] = 'Por favor, informe seu nome completo.';
            Response::redirect('/comprar');
            return;
        }

        if (strlen($cpf) !== 11) {
            $_SESSION['flash_error'] = 'Por favor, informe um CPF válido com 11 dígitos.';
            Response::redirect('/comprar');
            return;
        }

        if (empty($phoneRaw)) {
            $_SESSION['flash_error'] = 'Por favor, informe um telefone/WhatsApp de contato.';
            Response::redirect('/comprar');
            return;
        }

        if ($quantity < 1 || $quantity > 50) {
            $_SESSION['flash_error'] = 'A quantidade permitida por pedido é de 1 a 50 cartelas.';
            Response::redirect('/comprar');
            return;
        }

        // Normalização interna de telefone no padrão +5512982422387
        $phoneDigits = preg_replace('/\D/', '', $phoneRaw);
        if (str_starts_with($phoneDigits, '55') && strlen($phoneDigits) >= 12) {
            $phoneNormalized = '+' . $phoneDigits;
        } else {
            $phoneNormalized = '+55' . $phoneDigits;
        }

        $pdo->beginTransaction();

        try {
            // Busca evento ativo
            $stmtEvent = $pdo->query("SELECT * FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
            $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new \Exception("Nenhum evento ativo no momento.");
            }

            // Busca ou cadastra o comprador por CPF
            $stmtBuyer = $pdo->prepare("SELECT id FROM buyers WHERE cpf = ?");
            $stmtBuyer->execute([$cpf]);
            $buyerId = $stmtBuyer->fetchColumn();

            if (!$buyerId) {
                $stmtNewBuyer = $pdo->prepare("
                    INSERT INTO buyers (name, cpf, phone, email, wants_email, created_at)
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtNewBuyer->execute([$name, $cpf, $phoneNormalized, !empty($email) ? $email : null, $wantsEmail]);
                $buyerId = (int)$pdo->lastInsertId();
            } else {
                $buyerId = (int)$buyerId;
                $stmtUpBuyer = $pdo->prepare("
                    UPDATE buyers 
                    SET name = ?, phone = ?, email = COALESCE(?, email), wants_email = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpBuyer->execute([$name, $phoneNormalized, !empty($email) ? $email : null, $wantsEmail, $buyerId]);
            }

            // Cálculo do valor com regra de pacotes promocionais
            $singlePrice = (float)$event['single_price'];
            $bundleQty = (int)$event['bundle_qty'];
            $bundlePrice = (float)$event['bundle_price'];

            $totalAmount = 0.00;
            if ($bundleQty > 1 && $quantity >= $bundleQty) {
                $bundles = intdiv($quantity, $bundleQty);
                $rem = $quantity % $bundleQty;
                $totalAmount = ($bundles * $bundlePrice) + ($rem * $singlePrice);
            } else {
                $totalAmount = $quantity * $singlePrice;
            }

            // Gera código único para o pedido
            $orderCode = 'PED-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (event_id, buyer_id, order_code, quantity, total_amount, status, payment_method, created_at)
                VALUES (?, ?, ?, ?, ?, 'PENDING', 'PIX', CURRENT_TIMESTAMP)
            ");
            $stmtOrder->execute([$event['id'], $buyerId, $orderCode, $quantity, $totalAmount]);
            $orderId = (int)$pdo->lastInsertId();

            // Busca lote ativo de cartelas
            $stmtBatch = $pdo->prepare("SELECT id FROM event_batches WHERE event_id = ? ORDER BY id ASC LIMIT 1");
            $stmtBatch->execute([$event['id']]);
            $batchId = (int)$stmtBatch->fetchColumn();

            if (!$batchId) {
                throw new \Exception("Nenhum lote de cartelas cadastrado para este evento.");
            }

            // Reserva os números e cria as cartelas para o pedido
            for ($i = 0; $i < $quantity; $i++) {
                $t = TicketService::createTicket($event['id'], $batchId, $buyerId, $orderId, 'RESERVED', $pdo);
                $pdo->prepare("INSERT INTO order_items (order_id, ticket_id, unit_price, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
                    ->execute([$orderId, $t['id'], $totalAmount / $quantity]);
            }

            // Gera o Payload PIX usando as configurações salvas no sistema
            $stmtPixKey = $pdo->query("SELECT value FROM settings WHERE key = 'pix_key'");
            $pixKey = $stmtPixKey ? $stmtPixKey->fetchColumn() : '';
            $stmtPixName = $pdo->query("SELECT value FROM settings WHERE key = 'pix_receiver_name'");
            $pixName = $stmtPixName ? $stmtPixName->fetchColumn() : 'Show de Premios';
            $stmtPixCity = $pdo->query("SELECT value FROM settings WHERE key = 'pix_receiver_city'");
            $pixCity = $stmtPixCity ? $stmtPixCity->fetchColumn() : 'Sao Paulo';

            $pixPayload = '';
            if (!empty($pixKey)) {
                $pixPayload = PixService::createPayload(
                    (string)$pixKey,
                    (string)($pixName ?: 'Show de Premios'),
                    (string)($pixCity ?: 'Sao Paulo'),
                    'Show de Premios ' . $orderCode,
                    $totalAmount,
                    $orderCode
                );
            }

            // Registra o pagamento na tabela payments
            $stmtPay = $pdo->prepare("
                INSERT INTO payments (order_id, payment_code, amount, method, status, pix_payload, created_at)
                VALUES (?, ?, ?, 'PIX', 'PENDING', ?, CURRENT_TIMESTAMP)
            ");
            $stmtPay->execute([$orderId, $orderCode, $totalAmount, $pixPayload]);

            $pdo->commit();

            Response::redirect('/pedido/' . $orderCode);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Erro ao processar pedido: ' . $e->getMessage();
            Response::redirect('/comprar');
        }
    }

    /**
     * Tela do Pedido e Pagamento PIX
     * Rota: GET /pedido/{orderCode}
     */
    public function showOrder(string $orderCode): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT o.*, b.name as buyer_name, b.cpf as buyer_cpf, b.phone as buyer_phone, b.email as buyer_email,
                   e.name as event_name, p.amount, p.pix_payload, p.status as payment_status, p.paid_at
            FROM orders o
            JOIN buyers b ON b.id = o.buyer_id
            JOIN events e ON e.id = o.event_id
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.order_code = ?
        ");
        $stmt->execute([$orderCode]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            View::render('errors/404', ['message' => 'Pedido não encontrado.']);
            return;
        }

        // Busca as cartelas associadas
        $stmtTickets = $pdo->prepare("
            SELECT id, ticket_number, check_code, secure_token, status, print_count
            FROM tickets 
            WHERE order_id = ?
            ORDER BY id ASC
        ");
        $stmtTickets->execute([$order['id']]);
        $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

        View::render('sales/order', [
            'order' => $order,
            'tickets' => $tickets,
            'is_paid' => ($order['status'] === 'PAID'),
            'isAuthenticated' => Auth::check(),
            'user' => Auth::user()
        ]);
    }

    /**
     * Endpoint JSON para consulta do status do pagamento em tempo real
     * Rota: GET /pedido/{orderCode}/status
     */
    public function orderStatusJson(string $orderCode): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_code = ?");
        $stmt->execute([$orderCode]);
        $status = $stmt->fetchColumn();

        echo json_encode([
            'order_code' => $orderCode,
            'status' => $status ?: 'NOT_FOUND',
            'is_paid' => ($status === 'PAID')
        ]);
        exit;
    }

    /**
     * Confirmação manual de pagamento (Disponível apenas para MASTER, ADMINISTRADOR e CAIXA)
     * Rota: POST /pedido/{orderCode}/confirmar
     */
    public function confirmPayment(string $orderCode): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Acesso não autorizado.'], 403);
            return;
        }

        $user = Auth::user();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT id, event_id, buyer_id, total_amount, status FROM orders WHERE order_code = ?");
            $stmt->execute([$orderCode]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new \Exception("Pedido não encontrado.");
            }

            if ($order['status'] === 'PAID') {
                $pdo->rollBack();
                Response::redirect('/pedido/' . $orderCode);
                return;
            }

            // Atualiza o pedido para PAGO
            $pdo->prepare("UPDATE orders SET status = 'PAID', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$order['id']]);

            // Atualiza o pagamento para PAGO
            $pdo->prepare("UPDATE payments SET status = 'PAID', paid_at = CURRENT_TIMESTAMP, confirmed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?")
                ->execute([$user['id'], $order['id']]);

            // Habilita as cartelas tornando-as VÁLIDAS para o sorteio
            $pdo->prepare("UPDATE tickets SET status = 'VALID', updated_at = CURRENT_TIMESTAMP WHERE order_id = ?")
                ->execute([$order['id']]);

            // Inicializa estado de jogo para todas as cartelas válidas recém-habilitadas
            $stmtTickets = $pdo->prepare("SELECT id FROM tickets WHERE order_id = ?");
            $stmtTickets->execute([$order['id']]);
            $ticketIds = $stmtTickets->fetchAll(PDO::FETCH_COLUMN);

            foreach ($ticketIds as $tid) {
                TicketService::initGameStateForTicket((int)$tid, (int)$order['event_id'], $pdo);
            }

            // Registra auditoria da confirmação financeira
            AuditService::log(
                'PAYMENT_CONFIRM',
                'orders',
                $order['id'],
                ['previous_status' => $order['status']],
                ['new_status' => 'PAID', 'confirmed_by' => $user['name'], 'order_code' => $orderCode]
            );

            $pdo->commit();

            // Envio opcional de e-mail se comprador tiver solicitado
            EmailService::sendTicketsByEmail($order['id']);

            Response::redirect('/pedido/' . $orderCode);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Erro ao confirmar pagamento: ' . $e->getMessage();
            Response::redirect('/pedido/' . $orderCode);
        }
    }
}
