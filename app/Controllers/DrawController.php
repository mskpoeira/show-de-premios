<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\GameEngineService;
use PDO;

class DrawController
{
    /**
     * Painel Operacional Central do Sorteio
     * Rotas: /sorteio e /admin/sorteio
     */
    public function index(): void
    {
        $pdo = Database::getConnection();

        // Busca evento ativo
        $stmtEvent = $pdo->query("SELECT * FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            View::render('draw/no_event', []);
            return;
        }

        // Obtém ou cria sorteio ativo
        $draw = GameEngineService::getOrCreateActiveDraw((int)$event['id']);
        $intelligence = GameEngineService::getGameIntelligence((int)$draw['id']);

        // Prêmios do evento para troca de rodada/prêmio
        $stmtPrizes = $pdo->prepare("SELECT * FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC");
        $stmtPrizes->execute([$event['id']]);
        $prizes = $stmtPrizes->fetchAll(PDO::FETCH_ASSOC);

        View::render('draw/index', [
            'event' => $event,
            'draw' => $draw,
            'intelligence' => $intelligence,
            'prizes' => $prizes,
            'user' => Auth::user(),
        ]);
    }

    /**
     * Endpoint para cantar uma nova pedra
     * Rota: POST /sorteio/cantar
     */
    public function call(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        $number = (int)($_POST['number'] ?? 0);
        $drawId = (int)($_POST['draw_id'] ?? 0);

        if ($number < 1 || $number > 75 || !$drawId) {
            echo json_encode(['success' => false, 'message' => 'Número da pedra inválido (deve ser entre 1 e 75).']);
            exit;
        }

        try {
            $result = GameEngineService::callNumber($drawId, $number, (int)$user['id']);

            AuditService::log(
                'BINGO_NUMBER_CALL',
                'draws',
                $drawId,
                null,
                ['number' => $number, 'letter' => $result['letter'], 'order' => $result['call_order']]
            );

            echo json_encode([
                'success' => true,
                'call' => [
                    'number' => $number,
                    'letter' => $result['letter'],
                    'order' => $result['call_order'],
                ],
                'new_winners' => $result['new_winners'],
                'intelligence' => $result['intelligence']
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Endpoint para desfazer a última pedra cantada
     * Rota: POST /sorteio/desfazer
     */
    public function undo(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        $drawId = (int)($_POST['draw_id'] ?? 0);

        if (!$drawId) {
            echo json_encode(['success' => false, 'message' => 'ID do sorteio inválido.']);
            exit;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Busca a última pedra cantada
            $stmtLast = $pdo->prepare("
                SELECT id, number_value, call_order 
                FROM called_numbers 
                WHERE draw_id = ? 
                ORDER BY call_order DESC LIMIT 1
            ");
            $stmtLast->execute([$drawId]);
            $lastCall = $stmtLast->fetch(PDO::FETCH_ASSOC);

            if (!$lastCall) {
                throw new \Exception("Nenhuma pedra sorteada para desfazer.");
            }

            // Remove a pedra de called_numbers
            $pdo->prepare("DELETE FROM called_numbers WHERE id = ?")->execute([$lastCall['id']]);

            // Desmarca acertos correspondentes
            $pdo->prepare("UPDATE ticket_numbers SET is_hit = 0, hit_at_call_id = NULL WHERE hit_at_call_id = ?")
                ->execute([$lastCall['id']]);

            // Remove vencedores gerados por esta pedra se houver
            $pdo->prepare("DELETE FROM winner_events WHERE draw_id = ? AND winning_number = ?")
                ->execute([$drawId, $lastCall['number_value']]);

            // Atualiza status da última chamada no sorteio
            $stmtPrev = $pdo->prepare("
                SELECT number_value, letter, call_order 
                FROM called_numbers 
                WHERE draw_id = ? 
                ORDER BY call_order DESC LIMIT 1
            ");
            $stmtPrev->execute([$drawId]);
            $prevCall = $stmtPrev->fetch(PDO::FETCH_ASSOC);

            $stmtUpDraw = $pdo->prepare("
                UPDATE draws 
                SET last_called_number = ?, last_called_letter = ?, total_numbers_called = COALESCE(?, 0), updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmtUpDraw->execute([
                $prevCall ? $prevCall['number_value'] : null,
                $prevCall ? $prevCall['letter'] : null,
                $prevCall ? $prevCall['call_order'] : 0,
                $drawId
            ]);

            // Recalcula ticket_game_state
            $stmtDrawInfo = $pdo->prepare("SELECT event_id FROM draws WHERE id = ?");
            $stmtDrawInfo->execute([$drawId]);
            $eventId = (int)$stmtDrawInfo->fetchColumn();

            $pdo->exec("
                UPDATE ticket_game_state
                SET 
                    hits_count = (
                        SELECT COUNT(tn.id) FROM ticket_numbers tn 
                        WHERE tn.ticket_id = ticket_game_state.ticket_id AND tn.is_hit = 1 AND tn.is_center = 0
                    ),
                    remaining_count = (
                        24 - (SELECT COUNT(tn.id) FROM ticket_numbers tn 
                        WHERE tn.ticket_id = ticket_game_state.ticket_id AND tn.is_hit = 1 AND tn.is_center = 0)
                    ),
                    is_winner = CASE WHEN (
                        SELECT COUNT(tn.id) FROM ticket_numbers tn 
                        WHERE tn.ticket_id = ticket_game_state.ticket_id AND tn.is_hit = 1 AND tn.is_center = 0
                    ) >= 24 THEN 1 ELSE 0 END
                WHERE draw_id = {$drawId}
            ");

            AuditService::log(
                'BINGO_NUMBER_UNDO',
                'draws',
                $drawId,
                ['undone_number' => $lastCall['number_value'], 'order' => $lastCall['call_order']],
                null
            );

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'intelligence' => GameEngineService::getGameIntelligence($drawId)
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Consulta em tempo real da inteligência do jogo (Polling / SSE)
     * Rota: GET /sorteio/inteligencia
     */
    public function intelligenceJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $drawId = (int)($_GET['draw_id'] ?? 0);

        if (!$drawId) {
            $pdo = Database::getConnection();
            $stmtEvent = $pdo->query("SELECT id FROM events WHERE status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
            $eventId = (int)$stmtEvent->fetchColumn();
            if ($eventId) {
                $draw = GameEngineService::getOrCreateActiveDraw($eventId);
                $drawId = (int)$draw['id'];
            }
        }

        if (!$drawId) {
            echo json_encode(['error' => 'Nenhum sorteio ativo.']);
            exit;
        }

        echo json_encode(GameEngineService::getGameIntelligence($drawId));
        exit;
    }

    /**
     * Ação de Contato com o Ganhador (LIGAR ou WHATSAPP)
     * Registra estritamente o acesso na auditoria
     * Rota: POST /sorteio/ganhador/contato
     */
    public function winnerContact(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        $buyerId = (int)($_POST['buyer_id'] ?? 0);
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $type = strtoupper(trim($_POST['type'] ?? 'WHATSAPP')); // 'CALL' ou 'WHATSAPP'

        $action = ($type === 'CALL') ? 'TICKET_CALL_WINNER' : 'TICKET_WHATSAPP_WINNER';

        AuditService::logBuyerAccess(
            (int)$user['id'],
            $user['name'],
            $user['role'],
            $buyerId ?: null,
            $ticketId ?: null,
            $action,
            'PANEL',
            'SUCCESS'
        );

        echo json_encode(['success' => true, 'logged' => true, 'action' => $action]);
        exit;
    }

    /**
     * Mudança de prêmio em disputa no sorteio
     * Rota: POST /sorteio/alterar-premio
     */
    public function changePrize(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $drawId = (int)($_POST['draw_id'] ?? 0);
        $prizeId = (int)($_POST['prize_id'] ?? 0);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE draws SET prize_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$prizeId, $drawId]);

        AuditService::log('DRAW_PRIZE_CHANGE', 'draws', $drawId, null, ['prize_id' => $prizeId]);

        echo json_encode(['success' => true]);
        exit;
    }
}
