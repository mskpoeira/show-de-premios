<?php

namespace AppServices;

use AppCoreAuth;
use AppCoreDatabase;

class AuditService
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $pdo = Database::getConnection();
            $userId = Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $dtBrasilia = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $createdAtBrasilia = $dtBrasilia->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, entity_type, entity_id, old_values_json, new_values_json, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $ip,
                substr($userAgent, 0, 255),
                $createdAtBrasilia,
            ]);
        } catch (\Throwable $e) {
            error_log("AuditLog Error: " . $e->getMessage());
        }
    }

    /**
     * Registra auditoria de consulta a dados pessoais de comprador / cartela
     */
    public static function logBuyerAccess(
        int $userId,
        string $userName,
        string $userRole,
        ?int $buyerId,
        ?int $ticketId,
        string $accessType,
        string $method = 'PANEL',
        string $result = 'SUCCESS'
    ): void {
        try {
            $pdo = Database::getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionId = session_id() ?: null;

            $stmt = $pdo->prepare("
                INSERT INTO ticket_access_logs 
                (user_id, user_name, user_role, buyer_id, ticket_id, access_type, method, ip_address, session_id, user_agent, result, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $userId,
                $userName,
                $userRole,
                $buyerId,
                $ticketId,
                $accessType,
                $method,
                $ip,
                $sessionId,
                substr($userAgent, 0, 255),
                $result
            ]);
        } catch (\Throwable $e) {
            error_log("AuditLogBuyerAccess Error: " . $e->getMessage());
        }
    }

    public static function getBrasiliaTime(string $format = 'Y-m-d H:i:s'): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        return $dt->format($format);
    }

    public static function translateAction(string $action): string
    {
        $map = [
            'LOGIN' => '🔑 Entrada no Sistema (Login)',
            'LOGOUT' => '🚪 Saída do Sistema (Logout)',
            'LOGIN_FAILED' => '⚠️ Tentativa de Login Falha',
            'ROUND_CREATE' => '➕ Criação de Rodada',
            'ROUND_SALES_SAVE' => '💾 Lançamento / Alteração de Vendas e Prêmios',
            'ROUND_CLOSE' => '🔒 Fechamento de Rodada',
            'ROUND_REOPEN' => '🔓 Reabertura de Rodada',
            'ROUND_STATUS_CHANGE' => '🔄 Alteração de Estado da Rodada',
            'BINGO_NUMBER_CALL' => '🎤 Pedra Cantada no Bingo',
            'BINGO_NUMBER_UNDO' => '↩️ Pedra Desfeita no Bingo',
            'WINNER_REGISTER' => '🏆 Registro de Ganhador(a)',
            'DAY_OPEN' => '📅 Abertura de Dia de Operação',
            'DAY_CLOSE' => '🏁 Fechamento de Dia de Operação',
            'DAY_REOPEN' => '🔓 Reabertura de Dia de Operação',
            'CASH_MOVEMENT' => '💵 Movimentação de Caixa',
            'CASH_INITIAL' => '💰 Fundo de Troco Inicial',
            'CASH_CLOSING' => '📋 Conferência de Caixa',
            'SELLER_CREATE' => '👤 Cadastro de Vendedor(a)',
            'SELLER_UPDATE' => '✏️ Edição de Vendedor(a)',
            'SELLER_TOGGLE' => '🔄 Alteração de Status de Vendedor(a)',
            'USER_CREATE' => '👥 Cadastro de Operador(a)',
            'USER_UPDATE' => '✏️ Alteração de Operador(a)',
            'USER_TOGGLE' => '🔄 Alteração de Status de Operador(a)',
            'SETUP_ADMIN' => '⚙️ Instalação Inicial do Sistema',
            'SETTINGS_UPDATE' => '⚙️ Alteração de Configurações',
            'BACKUP_GENERATE' => '💾 Cópia de Segurança (Backup)',
            'SYSTEM_RESET' => '🧹 Limpeza do Banco de Dados (Reset)',
            'SYSTEM_CLEAN' => '🧹 Limpeza de Rodadas e Auditoria',
            'TICKET_VIEW_PERSONAL' => '👁️ Consulta a Dados Pessoais de Cartela/Comprador',
            'TICKET_CALL_WINNER' => '📞 Tentativa de Ligação para Ganhador',
            'TICKET_WHATSAPP_WINNER' => '💬 Acionamento de WhatsApp do Ganhador',
            'TICKET_REPRINT' => '🖨️ Reimpressão de Cartela',
            'TICKET_INVALIDATE' => '🚫 Invalidação Manual de Cartela',
            'ONLINE_ORDER_CREATE' => '🛒 Criação de Pedido de Compra Online',
            'PAYMENT_CONFIRM' => '✅ Confirmação de Pagamento PIX/Caixa',
        ];

        return $map[$action] ?? ucwords(strtolower(str_replace('_', ' ', $action)));
    }

    public static function translateEntity(string $entity): string
    {
        $map = [
            'users' => 'Operadores / Usuários',
            'rounds' => 'Rodadas',
            'operation_days' => 'Dias de Operação',
            'sellers' => 'Vendedores(as)',
            'sales' => 'Vendas de Cartelas',
            'cash_movements' => 'Movimentações de Caixa',
            'cash_closings' => 'Fechamentos de Caixa',
            'settings' => 'Configurações do Sistema',
            'pricing_rules' => 'Regras de Precificação',
            'tickets' => 'Cartelas',
            'buyers' => 'Compradores',
            'orders' => 'Pedidos',
            'events' => 'Eventos',
        ];

        return $map[$entity] ?? ucfirst($entity);
    }
}
