<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\View;
use App\Services\AuditService;
use App\Services\TicketService;
use PDO;

class ValidationController
{
    public function validateByToken(string $token): void
    {
        if (!RateLimiter::allow(RateLimiter::clientKey('ticket-token-validation'), 120, 300)) {
            http_response_code(429);
            echo 'Muitas consultas. Aguarde alguns minutos.';
            return;
        }

        if (!preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
            $this->renderPublicNotFound();
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.id,t.ticket_number,t.status,t.created_at,t.order_id,t.buyer_id,
                   e.name AS event_name,e.event_date,e.location AS event_location,
                   b.name AS buyer_name,b.cpf AS buyer_cpf
            FROM tickets t
            JOIN events e ON e.id=t.event_id
            LEFT JOIN buyers b ON b.id=t.buyer_id
            WHERE t.secure_token=?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        $isAuthenticated = Auth::check();
        $user = Auth::user();
        $this->logValidation($pdo, $ticket, 'QR', $token, null, null, $isAuthenticated, $user);

        if (!$ticket) {
            $this->renderPublicNotFound();
            return;
        }

        if (!$isAuthenticated) {
            View::render('validation/public_result', [
                'found' => true,
                'ticket_number' => $ticket['ticket_number'],
                'event_name' => $ticket['event_name'],
                'event_date' => $ticket['event_date'],
                'status' => $ticket['status'],
                'is_valid' => in_array($ticket['status'], ['VALID','AWARDED'], true),
                'buyer_name_masked' => $this->maskName((string)($ticket['buyer_name'] ?? '')),
                'buyer_cpf_masked' => TicketService::maskCpf($ticket['buyer_cpf'] ?? null),
            ]);
            return;
        }

        $buyerData = null;
        if (!empty($ticket['buyer_id'])) {
            $stmtBuyer = $pdo->prepare("SELECT * FROM buyers WHERE id=? LIMIT 1");
            $stmtBuyer->execute([(int)$ticket['buyer_id']]);
            $buyerData = $stmtBuyer->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        AuditService::logBuyerAccess(
            (int)$user['id'], (string)$user['name'], (string)$user['role'],
            !empty($ticket['buyer_id']) ? (int)$ticket['buyer_id'] : null,
            (int)$ticket['id'], 'VIEW_DETAILS', 'QR', 'SUCCESS'
        );

        $stmtOrder = $pdo->prepare("
            SELECT o.*,p.status AS payment_status,p.method AS payment_method,p.paid_at
            FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? LIMIT 1
        ");
        $stmtOrder->execute([(int)$ticket['order_id']]);

        View::render('validation/authenticated_result', [
            'ticket' => $ticket,
            'buyer' => $buyerData,
            'order' => $stmtOrder->fetch(PDO::FETCH_ASSOC) ?: null,
            'user' => $user,
        ]);
    }

    public function manualValidation(): void
    {
        if (!RateLimiter::allow(RateLimiter::clientKey('ticket-manual-validation'), 60, 300)) {
            http_response_code(429);
            echo 'Muitas consultas. Aguarde alguns minutos.';
            return;
        }

        $ticketNumber = strtoupper(trim((string)($_GET['ticket_number'] ?? $_POST['ticket_number'] ?? '')));
        $checkCode = strtoupper(trim((string)($_GET['check_code'] ?? $_POST['check_code'] ?? '')));
        $isAuthenticated = Auth::check();
        $user = Auth::user();

        if ($ticketNumber === '') {
            View::render('validation/manual_form', ['ticketNumber'=>'','checkCode'=>'','result'=>null]);
            return;
        }

        if (!$isAuthenticated && $checkCode === '') {
            View::render('validation/manual_form', [
                'ticketNumber'=>$ticketNumber,
                'checkCode'=>'',
                'result'=>['found'=>false,'message'=>'Informe também o código de conferência impresso na cartela.'],
            ]);
            return;
        }

        $pdo = Database::getConnection();
        $query = "
            SELECT t.id,t.ticket_number,t.status,t.created_at,t.order_id,t.buyer_id,
                   e.name AS event_name,e.event_date,e.location AS event_location,
                   b.name AS buyer_name,b.cpf AS buyer_cpf
            FROM tickets t
            JOIN events e ON e.id=t.event_id
            LEFT JOIN buyers b ON b.id=t.buyer_id
            WHERE UPPER(t.ticket_number)=UPPER(?)
        ";
        $params = [$ticketNumber];
        if (!$isAuthenticated || $checkCode !== '') {
            $query .= ' AND UPPER(t.check_code)=UPPER(?)';
            $params[] = $checkCode;
        }
        $query .= ' LIMIT 1';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->logValidation($pdo, $ticket, 'MANUAL', null, $ticketNumber, $checkCode, $isAuthenticated, $user);

        if (!$ticket) {
            View::render('validation/manual_form', [
                'ticketNumber'=>$ticketNumber,'checkCode'=>$checkCode,
                'result'=>['found'=>false,'message'=>'Nenhuma cartela encontrada com o número e código de conferência informados.'],
            ]);
            return;
        }

        if (!$isAuthenticated) {
            View::render('validation/manual_form', [
                'ticketNumber'=>$ticketNumber,'checkCode'=>'',
                'result'=>[
                    'found'=>true,
                    'ticket_number'=>$ticket['ticket_number'],
                    'event_name'=>$ticket['event_name'],
                    'event_date'=>$ticket['event_date'],
                    'status'=>$ticket['status'],
                    'is_valid'=>in_array($ticket['status'], ['VALID','AWARDED'], true),
                    'buyer_name_masked'=>$this->maskName((string)($ticket['buyer_name'] ?? '')),
                    'buyer_cpf_masked'=>TicketService::maskCpf($ticket['buyer_cpf'] ?? null),
                ],
            ]);
            return;
        }

        $buyerData = null;
        if (!empty($ticket['buyer_id'])) {
            $stmtBuyer = $pdo->prepare("SELECT * FROM buyers WHERE id=? LIMIT 1");
            $stmtBuyer->execute([(int)$ticket['buyer_id']]);
            $buyerData = $stmtBuyer->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        AuditService::logBuyerAccess(
            (int)$user['id'], (string)$user['name'], (string)$user['role'],
            !empty($ticket['buyer_id']) ? (int)$ticket['buyer_id'] : null,
            (int)$ticket['id'], 'MANUAL_CONFERENCE', 'MANUAL', 'SUCCESS'
        );

        View::render('validation/manual_form', [
            'ticketNumber'=>$ticketNumber,'checkCode'=>$checkCode,
            'result'=>['found'=>true,'ticket'=>$ticket,'buyer'=>$buyerData,'authenticated'=>true],
        ]);
    }

    private function logValidation(PDO $pdo, array|false $ticket, string $method, ?string $token, ?string $number, ?string $code, bool $authenticated, ?array $user): void
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_validations (ticket_id,validation_method,input_token,input_number,input_code,is_authenticated,user_id,ip_address,user_agent,result_status,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $ticket ? (int)$ticket['id'] : null, $method, $token, $number, $code,
                $authenticated ? 1 : 0, $user ? (int)$user['id'] : null,
                $_SERVER['REMOTE_ADDR'] ?? null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255),
                $ticket ? (string)$ticket['status'] : 'NOT_FOUND',
            ]);
        } catch (\Throwable $e) {
            error_log('[Validation audit] '.$e->getMessage());
        }
    }

    private function renderPublicNotFound(): void
    {
        http_response_code(404);
        View::render('validation/public_result', ['found'=>false,'status'=>'INVALID','message'=>'Cartela não localizada ou código inválido.']);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (!$parts) return 'Não informado';
        $first = array_shift($parts);
        return implode(' ', array_merge([$first], array_map(fn(string $p): string => mb_substr($p,0,1).'***', $parts)));
    }
}
