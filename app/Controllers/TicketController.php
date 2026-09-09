<?php

namespace AppControllers;

use AppCoreAuth;
use AppCoreDatabase;
use AppCoreResponse;
use AppCoreView;
use AppServicesAuditService;
use AppServicesTicketService;
use PDO;

class TicketController
{
    /**
     * Listagem e Gerenciamento de Cartelas
     * Rota: GET /cartelas
     */
    public function index(): void
    {
        $pdo = Database::getConnection();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Query base
        $where = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $where[] = "(t.ticket_number LIKE ? OR t.check_code LIKE ? OR b.name LIKE ? OR b.cpf LIKE ?)";
            $s = "%{$search}%";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        if (!empty($status)) {
            $where[] = "t.status = ?";
            $params[] = $status;
        }

        if ($batchId > 0) {
            $where[] = "t.batch_id = ?";
            $params[] = $batchId;
        }

        $whereClause = implode(" AND ", $where);

        // Contagem total
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tickets t
            LEFT JOIN buyers b ON b.id = t.buyer_id
            WHERE {$whereClause}
        ");
        $stmtCount->execute($params);
        $totalItems = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);

        // Busca registros
        $stmtTickets = $pdo->prepare("
            SELECT t.*, b.name as buyer_name, b.cpf as buyer_cpf, b.phone as buyer_phone,
                   eb.batch_code, eb.batch_type
            FROM tickets t
            LEFT JOIN buyers b ON b.id = t.buyer_id
            JOIN event_batches eb ON eb.id = t.batch_id
            WHERE {$whereClause}
            ORDER BY t.sequence_number DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtTickets->execute($params);
        $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

        // Lotes para filtro
        $stmtBatches = $pdo->query("SELECT id, batch_code, prefix, total_generated, is_locked FROM event_batches ORDER BY id DESC");
        $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

        View::render('tickets/index', [
            'tickets' => $tickets,
            'batches' => $batches,
            'search' => $search,
            'status' => $status,
            'batchId' => $batchId,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'user' => Auth::user(),
        ]);
    }

    /**
     * Impressão / Reimpressão profissional da cartela em A4
     * Rotas: /cartelas/imprimir/{tokenOrNumber}
     */
    public function printTicket(string $tokenOrNumber): void
    {
        $ticket = TicketService::getTicketForPrint($tokenOrNumber);

        if (!$ticket) {
            View::render('errors/404', ['message' => 'Cartela não encontrada para impressão.']);
            return;
        }

        $user = Auth::user();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Registra contagem de impressão mantendo estritamente os mesmos códigos/tokens
        TicketService::recordPrint((int)$ticket['id'], $user ? (int)$user['id'] : null, $ip);

        // Layout selecionável por parâmetro GET (layout=1, 2 ou 4 cartelas por A4, padrão 2)
        $layout = (int)($_GET['layout'] ?? 2);
        if (!in_array($layout, [1, 2, 4], true)) {
            $layout = 2;
        }

        View::render('tickets/print_a4', [
            'ticket' => $ticket,
            'layout' => $layout,
            'masked_cpf' => TicketService::maskCpf($ticket['buyer_cpf'] ?? null),
            'masked_phone' => TicketService::maskPhone($ticket['buyer_phone'] ?? null),
        ]);
    }

    /**
     * Invalidação manual de cartela
     * Rota: POST /cartelas/invalidar
     */
    public function invalidate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            exit;
        }

        $user = Auth::user();
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Invalidação manual pelo operador');

        if (!$ticketId) {
            echo json_encode(['success' => false, 'message' => 'ID de cartela inválido.']);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT ticket_number, status, buyer_id FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Cartela não encontrada.']);
            exit;
        }

        $pdo->prepare("UPDATE tickets SET status = 'INVALID', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$ticketId]);

        AuditService::log(
            'TICKET_INVALIDATE',
            'tickets',
            $ticketId,
            ['previous_status' => $ticket['status']],
            ['new_status' => 'INVALID', 'reason' => $reason, 'invalidated_by' => $user['name']]
        );

        echo json_encode(['success' => true, 'message' => "Cartela {$ticket['ticket_number']} invalidada com sucesso."]);
        exit;
    }

    /**
     * Geração em Lote de Cartelas (Pré-emissão para venda física ou gráfica)
     * Rota: POST /cartelas/gerar-lote
     */
    public function generateBatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            exit;
        }

        $pdo = Database::getConnection();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 100);

        if ($quantity < 1 || $quantity > 1000) {
            echo json_encode(['success' => false, 'message' => 'Quantidade para geração deve ser entre 1 e 1000 cartelas por vez.']);
            exit;
        }

        $stmtBatch = $pdo->prepare("SELECT * FROM event_batches WHERE id = ?");
        $stmtBatch->execute([$batchId]);
        $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            echo json_encode(['success' => false, 'message' => 'Lote não encontrado.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $generated = [];
            for ($i = 0; $i < $quantity; $i++) {
                $t = TicketService::createTicket((int)$batch['event_id'], $batchId, null, null, 'VALID', $pdo);
                $generated[] = $t['ticket_number'];
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Geradas com sucesso {$quantity} cartelas no lote {$batch['batch_code']}.",
                'first' => $generated[0] ?? '',
                'last' => end($generated) ?: ''
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
