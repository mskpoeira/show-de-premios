<?php

namespace AppControllers;

use AppCoreAuth;
use AppCoreDatabase;
use AppCoreResponse;
use AppCoreView;
use AppServicesAuditService;
use AppServicesTicketService;
use PDO;

class ValidationController
{
    /**
     * Validação pública ou autenticada por Token Seguro do QR Code
     * Rota: /v/{token}
     */
    public function validateByToken(string $token): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.id, t.ticket_number, t.check_code, t.status, t.created_at, t.order_id,
                   t.buyer_id, e.name as event_name, e.event_date, e.location as event_location
            FROM tickets t
            JOIN events e ON e.id = t.event_id
            WHERE t.secure_token = ?
        ");
        $stmt->execute([$token]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        $isAuthenticated = Auth::check();
        $user = Auth::user();

        // Registra validação na tabela ticket_validations
        $stmtVal = $pdo->prepare("
            INSERT INTO ticket_validations (ticket_id, validation_method, input_token, is_authenticated, user_id, ip_address, user_agent, result_status, created_at)
            VALUES (?, 'QR', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmtVal->execute([
            $ticket ? $ticket['id'] : null,
            $token,
            $isAuthenticated ? 1 : 0,
            $user ? $user['id'] : null,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $ticket ? $ticket['status'] : 'NOT_FOUND'
        ]);

        if (!$ticket) {
            View::render('validation/public_result', [
                'found' => false,
                'status' => 'INVALID',
                'message' => 'Cartela não localizada ou código inválido.'
            ]);
            return;
        }

        // SE O USUÁRIO FOR PÚBLICO (NÃO AUTENTICADO):
        // REGRA ABSOLUTA DE PRIVACIDADE: NUNCA enviar nome, CPF, telefone ou e-mail!
        if (!$isAuthenticated) {
            View::render('validation/public_result', [
                'found' => true,
                'ticket_number' => $ticket['ticket_number'],
                'event_name' => $ticket['event_name'],
                'status' => $ticket['status'], // VALID, RESERVED, PENDING, CANCELLED, INVALID, AWARDED
                'is_valid' => in_array($ticket['status'], ['VALID', 'AWARDED'], true),
                'check_code' => $ticket['check_code']
            ]);
            return;
        }

        // SE O USUÁRIO FOR AUTENTICADO (MASTER, ADMIN, CAIXA):
        // Carrega dados completos do comprador e audita a consulta
        $buyerData = null;
        if ($ticket['buyer_id']) {
            $stmtBuyer = $pdo->prepare("SELECT * FROM buyers WHERE id = ?");
            $stmtBuyer->execute([$ticket['buyer_id']]);
            $buyerData = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
        }

        // Registra log de auditoria do acesso a dados pessoais
        AuditService::logBuyerAccess(
            (int)$user['id'],
            $user['name'],
            $user['role'],
            $ticket['buyer_id'] ? (int)$ticket['buyer_id'] : null,
            (int)$ticket['id'],
            'VIEW_DETAILS',
            'QR',
            'SUCCESS'
        );

        // Busca o pedido e pagamento se houver
        $stmtOrder = $pdo->prepare("
            SELECT o.*, p.status as payment_status, p.method as payment_method, p.paid_at
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.id = ?
        ");
        $stmtOrder->execute([$ticket['order_id']]);
        $orderData = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        View::render('validation/authenticated_result', [
            'ticket' => $ticket,
            'buyer' => $buyerData,
            'order' => $orderData,
            'user' => $user
        ]);
    }

    /**
     * Tela de conferência e validação manual por Número da Cartela e Código de Conferência
     * Rota: /validar
     */
    public function manualValidation(): void
    {
        $ticketNumber = trim($_GET['ticket_number'] ?? $_POST['ticket_number'] ?? '');
        $checkCode = strtoupper(trim($_GET['check_code'] ?? $_POST['check_code'] ?? ''));

        if (empty($ticketNumber)) {
            View::render('validation/manual_form', [
                'ticketNumber' => '',
                'checkCode' => '',
                'result' => null
            ]);
            return;
        }

        $pdo = Database::getConnection();
        $query = "
            SELECT t.id, t.ticket_number, t.check_code, t.status, t.created_at, t.order_id,
                   t.buyer_id, e.name as event_name, e.event_date, e.location as event_location
            FROM tickets t
            JOIN events e ON e.id = t.event_id
            WHERE UPPER(t.ticket_number) = UPPER(?)
        ";
        $params = [$ticketNumber];

        if (!empty($checkCode)) {
            $query .= " AND UPPER(t.check_code) = UPPER(?)";
            $params[] = $checkCode;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        $isAuthenticated = Auth::check();
        $user = Auth::user();

        // Registra validação
        $stmtVal = $pdo->prepare("
            INSERT INTO ticket_validations (ticket_id, validation_method, input_number, input_code, is_authenticated, user_id, ip_address, user_agent, result_status, created_at)
            VALUES (?, 'MANUAL', ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmtVal->execute([
            $ticket ? $ticket['id'] : null,
            $ticketNumber,
            $checkCode,
            $isAuthenticated ? 1 : 0,
            $user ? $user['id'] : null,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $ticket ? $ticket['status'] : 'NOT_FOUND'
        ]);

        if (!$ticket) {
            View::render('validation/manual_form', [
                'ticketNumber' => $ticketNumber,
                'checkCode' => $checkCode,
                'result' => [
                    'found' => false,
                    'message' => 'Nenhuma cartela encontrada com os dados informados.'
                ]
            ]);
            return;
        }

        if (!$isAuthenticated) {
            // Usuário público: exibe apenas status e número
            View::render('validation/manual_form', [
                'ticketNumber' => $ticketNumber,
                'checkCode' => $checkCode,
                'result' => [
                    'found' => true,
                    'ticket_number' => $ticket['ticket_number'],
                    'event_name' => $ticket['event_name'],
                    'status' => $ticket['status'],
                    'is_valid' => in_array($ticket['status'], ['VALID', 'AWARDED'], true),
                    'check_code' => $ticket['check_code']
                ]
            ]);
            return;
        }

        // Usuário autenticado
        $buyerData = null;
        if ($ticket['buyer_id']) {
            $stmtBuyer = $pdo->prepare("SELECT * FROM buyers WHERE id = ?");
            $stmtBuyer->execute([$ticket['buyer_id']]);
            $buyerData = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
        }

        AuditService::logBuyerAccess(
            (int)$user['id'],
            $user['name'],
            $user['role'],
            $ticket['buyer_id'] ? (int)$ticket['buyer_id'] : null,
            (int)$ticket['id'],
            'MANUAL_CONFERENCE',
            'MANUAL',
            'SUCCESS'
        );

        View::render('validation/manual_form', [
            'ticketNumber' => $ticketNumber,
            'checkCode' => $checkCode,
            'result' => [
                'found' => true,
                'ticket' => $ticket,
                'buyer' => $buyerData,
                'authenticated' => true
            ]
        ]);
    }
}
