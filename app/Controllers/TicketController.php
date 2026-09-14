<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\TicketService;
use PDO;

class TicketController
{
    public function index(): void
    {
        $pdo = Database::getConnection();
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(t.ticket_number LIKE ? OR t.check_code LIKE ? OR b.name LIKE ? OR b.cpf LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($batchId > 0) {
            $where[] = 't.batch_id = ?';
            $params[] = $batchId;
        }
        $whereSql = implode(' AND ', $where);

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tickets t LEFT JOIN buyers b ON b.id=t.buyer_id WHERE {$whereSql}");
        $stmtCount->execute($params);
        $totalItems = (int)$stmtCount->fetchColumn();
        $totalPages = (int)max(1, ceil($totalItems / $perPage));

        $stmtTickets = $pdo->prepare("
            SELECT t.*, b.name AS buyer_name, b.cpf AS buyer_cpf, b.phone AS buyer_phone, eb.batch_code, eb.batch_type
            FROM tickets t
            LEFT JOIN buyers b ON b.id=t.buyer_id
            JOIN event_batches eb ON eb.id=t.batch_id
            WHERE {$whereSql}
            ORDER BY t.sequence_number DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtTickets->execute($params);

        $stmtBatches = $pdo->query("SELECT id,batch_code,prefix,total_generated,is_locked FROM event_batches ORDER BY id DESC");

        View::render('tickets/index', [
            'tickets' => $stmtTickets->fetchAll(PDO::FETCH_ASSOC),
            'batches' => $stmtBatches->fetchAll(PDO::FETCH_ASSOC),
            'search' => $search,
            'status' => $status,
            'batchId' => $batchId,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'user' => Auth::user(),
        ]);
    }

    public function printTicket(string $secureToken): void
    {
        $ticket = TicketService::getTicketForPrint($secureToken);
        if (!$ticket || !in_array($ticket['status'], ['VALID', 'AWARDED'], true)) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'Cartela válida não encontrada para impressão.']);
            return;
        }

        $user = Auth::user();
        TicketService::recordPrint((int)$ticket['id'], $user ? (int)$user['id'] : null, $_SERVER['REMOTE_ADDR'] ?? null);

        $layout = (int)($_GET['layout'] ?? 2);
        if (!in_array($layout, [1,2,4], true)) {
            $layout = 2;
        }

        View::render('tickets/print_a4', [
            'ticket' => $ticket,
            'layout' => $layout,
            'masked_cpf' => TicketService::maskCpf($ticket['buyer_cpf'] ?? null),
            'masked_phone' => TicketService::maskPhone($ticket['buyer_phone'] ?? null),
        ]);
    }

    public function invalidate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::isOperator()) {
            http_response_code(403);
            echo json_encode(['success'=>false,'message'=>'Não autorizado.']);
            exit;
        }

        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($ticketId <= 0 || $reason === '') {
            http_response_code(422);
            echo json_encode(['success'=>false,'message'=>'Informe a cartela e o motivo da invalidação.']);
            exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT ticket_number,status,buyer_id FROM tickets WHERE id=? LIMIT 1");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['success'=>false,'message'=>'Cartela não encontrada.']);
            exit;
        }
        if ($ticket['status'] === 'AWARDED') {
            http_response_code(409);
            echo json_encode(['success'=>false,'message'=>'Cartela premiada não pode ser invalidada sem procedimento administrativo de estorno.']);
            exit;
        }

        $pdo->prepare("UPDATE tickets SET status='INVALID',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$ticketId]);
        AuditService::log('TICKET_INVALIDATE','tickets',$ticketId,['previous_status'=>$ticket['status']],['new_status'=>'INVALID','reason'=>$reason,'invalidated_by_user_id'=>Auth::id()]);
        echo json_encode(['success'=>true,'message'=>"Cartela {$ticket['ticket_number']} invalidada com sucesso."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function generateBatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success'=>false,'message'=>'Apenas administrador pode gerar lote digital.']);
            exit;
        }

        $batchId = (int)($_POST['batch_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 100);
        if ($batchId <= 0 || $quantity < 1 || $quantity > 1000) {
            http_response_code(422);
            echo json_encode(['success'=>false,'message'=>'Lote inválido ou quantidade fora do intervalo de 1 a 1000.']);
            exit;
        }

        $pdo = Database::getConnection();
        $stmtBatch = $pdo->prepare("SELECT * FROM event_batches WHERE id=? LIMIT 1");
        $stmtBatch->execute([$batchId]);
        $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            http_response_code(404);
            echo json_encode(['success'=>false,'message'=>'Lote não encontrado.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $generated = [];
            for ($i=0;$i<$quantity;$i++) {
                $ticket = TicketService::createTicket((int)$batch['event_id'],$batchId,null,null,'VALID',$pdo);
                $generated[] = $ticket['ticket_number'];
            }
            $pdo->commit();
            AuditService::log('DIGITAL_TICKET_BATCH_GENERATE','event_batches',$batchId,null,['quantity'=>$quantity,'created_by'=>Auth::id()]);
            echo json_encode(['success'=>true,'message'=>"Geradas {$quantity} cartelas digitais no lote {$batch['batch_code']}.",'first'=>$generated[0] ?? '','last'=>end($generated) ?: ''], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            error_log('[Digital batch] '.$e->getMessage());
            echo json_encode(['success'=>false,'message'=>'Falha ao gerar lote digital.']);
        }
        exit;
    }
}
