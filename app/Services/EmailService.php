<?php

namespace AppServices;

use AppCoreDatabase;
use PDO;

class EmailService
{
    /**
     * Registra e envia as cartelas por e-mail para o comprador
     */
    public static function sendTicketsByEmail(int $orderId, ?string $recipientEmail = null): array
    {
        $pdo = Database::getConnection();

        // Busca o pedido, comprador e evento
        $stmtOrder = $pdo->prepare("
            SELECT o.*, b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone,
                   e.name as event_name, e.event_date, e.location as event_location
            FROM orders o
            JOIN buyers b ON b.id = o.buyer_id
            JOIN events e ON e.id = o.event_id
            WHERE o.id = ?
        ");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido não encontrado.'];
        }

        $email = $recipientEmail ?: $order['buyer_email'];
        if (empty($email)) {
            return ['success' => false, 'message' => 'Comprador não possui e-mail informado.'];
        }

        // Busca as cartelas do pedido
        $stmtTickets = $pdo->prepare("
            SELECT id, ticket_number, check_code, secure_token, status
            FROM tickets
            WHERE order_id = ?
        ");
        $stmtTickets->execute([$orderId]);
        $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tickets)) {
            return ['success' => false, 'message' => 'Nenhuma cartela vinculada a este pedido.'];
        }

        $subject = "Suas Cartelas do Show de Prêmios - " . $order['event_name'];

        $ticketListHtml = "";
        foreach ($tickets as $t) {
            $viewUrl = "https://showdepremios.mskpoeira.com.br/v/" . $t['secure_token'];
            $printUrl = "https://showdepremios.mskpoeira.com.br/cartelas/imprimir/" . $t['secure_token'];
            $ticketListHtml .= "
                <li style='margin-bottom: 12px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;'>
                    <strong>Cartela: {$t['ticket_number']}</strong> &nbsp;|&nbsp; Código: <code>{$t['check_code']}</code><br>
                    <a href='{$viewUrl}' style='color: #0284c7; text-decoration: none; font-weight: bold;'>🔍 Validar Cartela</a> &nbsp;|&nbsp;
                    <a href='{$printUrl}' style='color: #0d9488; text-decoration: none; font-weight: bold;'>🖨️ Visualizar / Imprimir</a>
                </li>
            ";
        }

        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1e293b;'>
                <div style='background: #0284c7; color: #fff; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>Show de Prêmios Oficial</h2>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>{$order['event_name']}</p>
                </div>
                <div style='padding: 20px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #fff;'>
                    <p>Olá, <strong>" . htmlspecialchars($order['buyer_name']) . "</strong>!</p>
                    <p>Seu pagamento foi confirmado com sucesso! Abaixo estão as suas cartelas oficiais para o sorteio:</p>
                    <ul style='list-style: none; padding: 0;'>
                        {$ticketListHtml}
                    </ul>
                    <p style='margin-top: 20px; font-size: 12px; color: #64748b;'>
                        Data do Evento: {$order['event_date']} às {$order['event_location']}<br>
                        Acompanhe o telão oficial em: <a href='https://showdepremios.mskpoeira.com.br/telao'>https://showdepremios.mskpoeira.com.br/telao</a>
                    </p>
                </div>
            </div>
        ";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Show de Prêmios <noreply@mskpoeira.com.br>',
            'Reply-To: contato@mskpoeira.com.br',
            'X-Mailer: PHP/' . phpversion()
        ];

        // Tentativa de envio via mail() nativo do PHP
        $sent = @mail($email, $subject, $body, implode("\r\n", $headers));

        // Atualiza a marcação de envio nas cartelas
        $stmtUp = $pdo->prepare("UPDATE tickets SET email_sent = 1, email_sent_at = CURRENT_TIMESTAMP WHERE order_id = ?");
        $stmtUp->execute([$orderId]);

        // Registra na auditoria
        AuditService::log(
            $sent ? 'EMAIL_SENT_SUCCESS' : 'EMAIL_SENT_ATTEMPT',
            'orders',
            $orderId,
            null,
            ['recipient' => $email, 'tickets_count' => count($tickets), 'status' => $sent ? 'SENT' : 'ATTEMPTED']
        );

        return [
            'success' => true,
            'message' => $sent ? 'E-mail enviado com sucesso!' : 'Tentativa de envio registrada no sistema.',
            'recipient' => $email
        ];
    }
}
