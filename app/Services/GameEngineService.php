<?php

namespace AppServices;

use AppCoreDatabase;
use PDO;

class GameEngineService
{
    /**
     * Registra o sorteio de uma nova pedra (chamada)
     * Executa cálculo de acertos, faltantes e detecção de vencedores simultâneos
     */
    public static function callNumber(int $drawId, int $numberValue, ?int $calledBy = null): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Verifica se a pedra já foi cantada neste sorteio
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM called_numbers WHERE draw_id = ? AND number_value = ?");
            $stmtCheck->execute([$drawId, $numberValue]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                throw new \Exception("A pedra {$numberValue} já foi sorteada nesta rodada.");
            }

            // Letra correspondente no Bingo 75
            $letter = self::getBingoLetter($numberValue);

            // Determina a ordem da chamada
            $stmtOrder = $pdo->prepare("SELECT COALESCE(MAX(call_order), 0) + 1 FROM called_numbers WHERE draw_id = ?");
            $stmtOrder->execute([$drawId]);
            $callOrder = (int)$stmtOrder->fetchColumn();

            // Insere na tabela called_numbers
            $stmtCall = $pdo->prepare("
                INSERT INTO called_numbers (draw_id, number_value, letter, call_order, called_by, called_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmtCall->execute([$drawId, $numberValue, $letter, $callOrder, $calledBy]);
            $callId = (int)$pdo->lastInsertId();

            // Atualiza o sorteio
            $stmtDraw = $pdo->prepare("
                UPDATE draws 
                SET last_called_number = ?, last_called_letter = ?, total_numbers_called = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmtDraw->execute([$numberValue, $letter, $callOrder, $drawId]);

            // Busca os dados do sorteio e prêmio atual
            $stmtDrawInfo = $pdo->prepare("SELECT event_id, prize_id FROM draws WHERE id = ?");
            $stmtDrawInfo->execute([$drawId]);
            $drawInfo = $stmtDrawInfo->fetch(PDO::FETCH_ASSOC);
            $eventId = (int)$drawInfo['event_id'];
            $prizeId = (int)($drawInfo['prize_id'] ?? 1);

            // Busca a regra de vitória do prêmio
            $stmtPrize = $pdo->prepare("SELECT victory_rule FROM prizes WHERE id = ?");
            $stmtPrize->execute([$prizeId]);
            $victoryRule = $stmtPrize->fetchColumn() ?: 'FULL_CARD';

            // Marca o acerto na tabela ticket_numbers
            $stmtHit = $pdo->prepare("
                UPDATE ticket_numbers 
                SET is_hit = 1, hit_at_call_id = ?
                WHERE number_value = ? AND ticket_id IN (
                    SELECT id FROM tickets WHERE event_id = ? AND status = 'VALID'
                )
            ");
            $stmtHit->execute([$callId, $numberValue, $eventId]);

            // Recalcula o estado do jogo para todas as cartelas VÁLIDAS deste evento
            // OTIMIZAÇÃO EM LOTE: atualiza ticket_game_state baseado na contagem de acertos
            $pdo->exec("
                INSERT OR REPLACE INTO ticket_game_state (id, draw_id, ticket_id, hits_count, needed_count, remaining_count, is_winner, winning_call_id, updated_at)
                SELECT 
                    tgs.id,
                    {$drawId} as draw_id,
                    t.id as ticket_id,
                    COALESCE(COUNT(tn.id), 0) as hits_count,
                    24 as needed_count,
                    (24 - COALESCE(COUNT(tn.id), 0)) as remaining_count,
                    CASE WHEN COALESCE(COUNT(tn.id), 0) >= 24 THEN 1 ELSE 0 END as is_winner,
                    CASE WHEN COALESCE(COUNT(tn.id), 0) >= 24 AND tgs.winning_call_id IS NULL THEN {$callId} ELSE tgs.winning_call_id END as winning_call_id,
                    CURRENT_TIMESTAMP as updated_at
                FROM tickets t
                LEFT JOIN ticket_game_state tgs ON tgs.draw_id = {$drawId} AND tgs.ticket_id = t.id
                LEFT JOIN ticket_numbers tn ON tn.ticket_id = t.id AND tn.is_hit = 1 AND tn.is_center = 0
                WHERE t.event_id = {$eventId} AND t.status = 'VALID'
                GROUP BY t.id
            ");

            // Verifica se há vencedores nesta pedra (APENAS CARTELAS VÁLIDAS)
            $newWinners = self::checkWinnersForRule($drawId, $prizeId, $victoryRule, $callId, $numberValue, $callOrder, $pdo);

            $pdo->commit();

            return [
                'call_id' => $callId,
                'call_order' => $callOrder,
                'number' => $numberValue,
                'letter' => $letter,
                'new_winners' => $newWinners,
                'intelligence' => self::getGameIntelligence($drawId)
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Verifica vencedores de acordo com a regra do prêmio
     * Suporta empate simultâneo sem privilegiar uma cartela sobre a outra
     */
    private static function checkWinnersForRule(
        int $drawId,
        int $prizeId,
        string $rule,
        int $callId,
        int $winningNumber,
        int $callOrder,
        PDO $pdo
    ): array {
        // Busca cartelas VÁLIDAS que atingiram a condição de vitória e ainda não foram registradas como vencedoras deste prêmio
        $stmtCandidates = $pdo->prepare("
            SELECT tgs.ticket_id, t.buyer_id, t.ticket_number
            FROM ticket_game_state tgs
            JOIN tickets t ON t.id = tgs.ticket_id
            WHERE tgs.draw_id = ? AND tgs.remaining_count = 0 AND t.status = 'VALID'
            AND tgs.ticket_id NOT IN (
                SELECT ticket_id FROM winner_events WHERE draw_id = ? AND prize_id = ?
            )
        ");
        $stmtCandidates->execute([$drawId, $drawId, $prizeId]);
        $candidates = $stmtCandidates->fetchAll(PDO::FETCH_ASSOC);

        if (empty($candidates)) {
            return [];
        }

        $winnersCount = count($candidates);
        $recordedWinners = [];

        $stmtInsertWinner = $pdo->prepare("
            INSERT INTO winner_events (
                draw_id, prize_id, ticket_id, buyer_id, winning_number,
                call_order, simultaneous_winners_count, tie_resolved, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmtUpdateTicket = $pdo->prepare("UPDATE tickets SET status = 'AWARDED', updated_at = CURRENT_TIMESTAMP WHERE id = ?");

        foreach ($candidates as $cand) {
            $isTie = ($winnersCount > 1) ? 1 : 0;
            $stmtInsertWinner->execute([
                $drawId,
                $prizeId,
                $cand['ticket_id'],
                $cand['buyer_id'],
                $winningNumber,
                $callOrder,
                $winnersCount,
                ($isTie ? 0 : 1)
            ]);

            $stmtUpdateTicket->execute([$cand['ticket_id']]);

            // Busca dados do comprador para exibição restrita ao painel administrativo
            $buyerData = null;
            if ($cand['buyer_id']) {
                $stmtBuyer = $pdo->prepare("SELECT name, cpf, phone, email FROM buyers WHERE id = ?");
                $stmtBuyer->execute([$cand['buyer_id']]);
                $buyerData = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
            }

            $recordedWinners[] = [
                'ticket_id' => $cand['ticket_id'],
                'ticket_number' => $cand['ticket_number'],
                'buyer' => $buyerData,
                'winning_number' => $winningNumber,
                'is_tie' => $isTie,
                'total_winners_on_ball' => $winnersCount
            ];
        }

        return $recordedWinners;
    }

    /**
     * Retorna o Painel de Inteligência do Jogo em Tempo Real
     * (Cartelas válidas, pendentes, falta 1, falta 2, falta 3, distribuição de pontuação, melhores cartelas)
     */
    public static function getGameIntelligence(int $drawId): array
    {
        $pdo = Database::getConnection();

        // Informações do sorteio
        $stmtDraw = $pdo->prepare("
            SELECT d.*, p.title as prize_title, p.value as prize_value, p.victory_rule
            FROM draws d
            LEFT JOIN prizes p ON p.id = d.prize_id
            WHERE d.id = ?
        ");
        $stmtDraw->execute([$drawId]);
        $draw = $stmtDraw->fetch(PDO::FETCH_ASSOC);

        if (!$draw) {
            return [];
        }

        $eventId = (int)$draw['event_id'];

        // Contagem de cartelas por estado
        $stmtStats = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM tickets 
            WHERE event_id = ? 
            GROUP BY status
        ");
        $stmtStats->execute([$eventId]);
        $statusCounts = $stmtStats->fetchAll(PDO::FETCH_KEY_PAIR);

        $validCount = (int)($statusCounts['VALID'] ?? 0);
        $pendingCount = (int)($statusCounts['PENDING'] ?? 0);
        $reservedCount = (int)($statusCounts['RESERVED'] ?? 0);
        $cancelledCount = (int)($statusCounts['CANCELLED'] ?? 0);
        $invalidCount = (int)($statusCounts['INVALID'] ?? 0);
        $awardedCount = (int)($statusCounts['AWARDED'] ?? 0);
        $totalSold = $validCount + $awardedCount;

        // Armadas: Falta 1, Falta 2, Falta 3 (Apenas para cartelas VÁLIDAS)
        $stmtArmadas = $pdo->prepare("
            SELECT remaining_count, COUNT(*) as count
            FROM ticket_game_state tgs
            JOIN tickets t ON t.id = tgs.ticket_id
            WHERE tgs.draw_id = ? AND t.status IN ('VALID', 'AWARDED') AND tgs.remaining_count IN (0, 1, 2, 3)
            GROUP BY remaining_count
        ");
        $stmtArmadas->execute([$drawId]);
        $armadasPairs = $stmtArmadas->fetchAll(PDO::FETCH_KEY_PAIR);

        $falta0 = (int)($armadasPairs[0] ?? 0); // Vencedoras
        $falta1 = (int)($armadasPairs[1] ?? 0);
        $falta2 = (int)($armadasPairs[2] ?? 0);
        $falta3 = (int)($armadasPairs[3] ?? 0);

        // Distribuição de Pontuação (Histograma dos acertos)
        $stmtHist = $pdo->prepare("
            SELECT hits_count, COUNT(*) as count
            FROM ticket_game_state tgs
            JOIN tickets t ON t.id = tgs.ticket_id
            WHERE tgs.draw_id = ? AND t.status IN ('VALID', 'AWARDED')
            GROUP BY hits_count
            ORDER BY hits_count DESC
        ");
        $stmtHist->execute([$drawId]);
        $scoreDistribution = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        // Melhores Cartelas (Mais próximas de bater) - para uso interno administrativo
        $stmtBest = $pdo->prepare("
            SELECT t.id, t.ticket_number, tgs.remaining_count, tgs.hits_count, b.name as buyer_name, b.phone as buyer_phone
            FROM ticket_game_state tgs
            JOIN tickets t ON t.id = tgs.ticket_id
            LEFT JOIN buyers b ON b.id = t.buyer_id
            WHERE tgs.draw_id = ? AND t.status IN ('VALID', 'AWARDED') AND tgs.remaining_count <= 3
            ORDER BY tgs.remaining_count ASC, t.ticket_number ASC
            LIMIT 20
        ");
        $stmtBest->execute([$drawId]);
        $bestTickets = $stmtBest->fetchAll(PDO::FETCH_ASSOC);

        // Lista de pedras sorteadas na ordem
        $stmtCalled = $pdo->prepare("
            SELECT number_value, letter, call_order, called_at
            FROM called_numbers 
            WHERE draw_id = ? 
            ORDER BY call_order ASC
        ");
        $stmtCalled->execute([$drawId]);
        $calledNumbers = $stmtCalled->fetchAll(PDO::FETCH_ASSOC);

        // Últimas 5 pedras
        $lastCalled = array_slice(array_reverse($calledNumbers), 0, 5);

        // Vencedores já registrados
        $stmtWinners = $pdo->prepare("
            SELECT we.*, t.ticket_number, b.name as buyer_name, b.phone as buyer_phone, b.cpf as buyer_cpf
            FROM winner_events we
            JOIN tickets t ON t.id = we.ticket_id
            LEFT JOIN buyers b ON b.id = we.buyer_id
            WHERE we.draw_id = ?
            ORDER BY we.call_order ASC
        ");
        $stmtWinners->execute([$drawId]);
        $winners = $stmtWinners->fetchAll(PDO::FETCH_ASSOC);

        return [
            'draw' => $draw,
            'summary' => [
                'total_sold' => $totalSold,
                'valid_tickets' => $validCount + $awardedCount,
                'pending_tickets' => $pendingCount,
                'reserved_tickets' => $reservedCount,
                'cancelled_tickets' => $cancelledCount,
                'invalid_tickets' => $invalidCount,
                'total_called' => count($calledNumbers),
                'last_called_number' => $draw['last_called_number'],
                'last_called_letter' => $draw['last_called_letter'],
                'falta_1' => $falta1,
                'falta_2' => $falta2,
                'falta_3' => $falta3,
                'winners_count' => count($winners)
            ],
            'last_5_called' => $lastCalled,
            'called_numbers' => $calledNumbers,
            'score_distribution' => $scoreDistribution,
            'best_tickets' => $bestTickets,
            'winners' => $winners
        ];
    }

    /**
     * Retorna a letra do Bingo 75 para um número
     */
    public static function getBingoLetter(int $number): string
    {
        if ($number >= 1 && $number <= 15) return 'B';
        if ($number >= 16 && $number <= 30) return 'I';
        if ($number >= 31 && $number <= 45) return 'N';
        if ($number >= 46 && $number <= 60) return 'G';
        if ($number >= 61 && $number <= 75) return 'O';
        return '';
    }

    /**
     * Obtém ou inicializa o sorteio ativo para a rodada / evento atual
     */
    public static function getOrCreateActiveDraw(int $eventId, ?int $roundId = null): array
    {
        $pdo = Database::getConnection();

        $stmtFind = $pdo->prepare("
            SELECT * FROM draws 
            WHERE event_id = ? AND status IN ('OPEN', 'IN_PROGRESS') 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtFind->execute([$eventId]);
        $draw = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$draw) {
            // Busca o primeiro prêmio ativo do evento
            $stmtPrize = $pdo->prepare("SELECT id FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC LIMIT 1");
            $stmtPrize->execute([$eventId]);
            $prizeId = (int)($stmtPrize->fetchColumn() ?: 1);

            $stmtCreate = $pdo->prepare("
                INSERT INTO draws (event_id, round_id, prize_id, status, total_numbers_called, started_at, created_at)
                VALUES (?, ?, ?, 'OPEN', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtCreate->execute([$eventId, $roundId, $prizeId]);
            $drawId = (int)$pdo->lastInsertId();

            // Inicializa estado para todas as cartelas válidas
            $pdo->exec("
                INSERT OR IGNORE INTO ticket_game_state (draw_id, ticket_id, hits_count, needed_count, remaining_count, is_winner)
                SELECT {$drawId}, id, 0, 24, 24, 0
                FROM tickets
                WHERE event_id = {$eventId} AND status = 'VALID'
            ");

            return self::getOrCreateActiveDraw($eventId, $roundId);
        }

        return $draw;
    }
}
