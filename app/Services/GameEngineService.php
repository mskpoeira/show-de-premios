<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class GameEngineService
{
    public static function callNumber(int $drawId, int $numberValue, ?int $calledBy = null): array
    {
        if ($numberValue < 1 || $numberValue > 75) {
            throw new \InvalidArgumentException('A pedra deve estar entre 1 e 75.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $draw = self::getDraw($drawId, $pdo);
            if (!$draw) throw new \RuntimeException('Sorteio não encontrado.');
            if (in_array($draw['status'], ['CLOSED','HOMOLOGATED','CANCELLED'], true)) {
                throw new \RuntimeException('Este sorteio está encerrado.');
            }

            $dup = $pdo->prepare('SELECT 1 FROM draw_stones WHERE draw_id=? AND number_value=? LIMIT 1');
            $dup->execute([$drawId, $numberValue]);
            if ($dup->fetchColumn()) throw new \RuntimeException("A pedra {$numberValue} já foi sorteada.");

            $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(call_order),0)+1 FROM draw_stones WHERE draw_id=?');
            $orderStmt->execute([$drawId]);
            $callOrder = (int)$orderStmt->fetchColumn();
            $letter = self::getBingoLetter($numberValue);

            $insert = $pdo->prepare('INSERT INTO draw_stones (draw_id,number_value,letter,call_order,called_by,called_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)');
            $insert->execute([$drawId,$numberValue,$letter,$callOrder,$calledBy]);
            $stoneId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE draws SET status='IN_PROGRESS',last_called_number=?,last_called_letter=?,total_numbers_called=?,started_at=COALESCE(started_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$numberValue,$letter,$callOrder,$drawId]);

            self::recalculateStates($drawId, $pdo);
            $draw = self::getDraw($drawId, $pdo);
            $candidates = self::detectWinnerCandidates($drawId, (int)($draw['prize_id'] ?? 0), $stoneId, $numberValue, $callOrder, $pdo);

            if ($candidates) {
                $pdo->prepare("UPDATE draws SET status='CHECKING',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$drawId]);
            }

            self::syncLegacyRoundCache($drawId, $pdo);
            $pdo->commit();

            return [
                'call_id'=>$stoneId,
                'call_order'=>$callOrder,
                'number'=>$numberValue,
                'letter'=>$letter,
                'candidate_winners'=>$candidates,
                'requires_homologation'=>!empty($candidates),
                'intelligence'=>self::getGameIntelligence($drawId),
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function undoLastNumber(int $drawId, ?int $userId = null): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM draw_stones WHERE draw_id=? ORDER BY call_order DESC LIMIT 1');
            $stmt->execute([$drawId]);
            $stone = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stone) throw new \RuntimeException('Nenhuma pedra sorteada para desfazer.');

            $homologated = $pdo->prepare("SELECT COUNT(*) FROM winner_claims WHERE draw_id=? AND call_order=? AND status='HOMOLOGATED'");
            $homologated->execute([$drawId,(int)$stone['call_order']]);
            if ((int)$homologated->fetchColumn() > 0) {
                throw new \RuntimeException('A última pedra possui resultado homologado e não pode ser desfeita.');
            }

            $pdo->prepare("DELETE FROM winner_claims WHERE draw_id=? AND call_order=? AND status='PENDING'")
                ->execute([$drawId,(int)$stone['call_order']]);
            $pdo->prepare('DELETE FROM draw_stones WHERE id=?')->execute([(int)$stone['id']]);

            $prevStmt = $pdo->prepare('SELECT * FROM draw_stones WHERE draw_id=? ORDER BY call_order DESC LIMIT 1');
            $prevStmt->execute([$drawId]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            $total = $prev ? (int)$prev['call_order'] : 0;
            $status = $total > 0 ? 'IN_PROGRESS' : 'OPEN';
            $pdo->prepare('UPDATE draws SET status=?,last_called_number=?,last_called_letter=?,total_numbers_called=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$status,$prev['number_value'] ?? null,$prev['letter'] ?? null,$total,$drawId]);

            self::recalculateStates($drawId, $pdo);
            self::syncLegacyRoundCache($drawId, $pdo);
            $pdo->commit();

            return [
                'removed_number'=>(int)$stone['number_value'],
                'intelligence'=>self::getGameIntelligence($drawId),
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function registerPhysicalWinner(int $drawId, int $prizeId, int $claimedBy, ?string $name, ?string $phone, ?string $cpf, ?string $note): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $draw = self::getDraw($drawId, $pdo);
            if (!$draw) throw new \RuntimeException('Sorteio não encontrado.');
            if ((int)$draw['last_called_number'] <= 0 || (int)$draw['total_numbers_called'] <= 0) {
                throw new \RuntimeException('Não há pedra sorteada para vincular à conferência física.');
            }
            if ((int)$draw['prize_id'] !== $prizeId) {
                throw new \RuntimeException('O prêmio informado não corresponde ao prêmio atual do sorteio.');
            }

            $stmt = $pdo->prepare("INSERT INTO winner_claims (draw_id,prize_id,winner_type,ticket_id,buyer_id,physical_name,physical_phone,physical_cpf,note,winning_number,call_order,status,claimed_by,claimed_at,created_at) VALUES (?,?,'PHYSICAL',NULL,NULL,?,?,?,?,?,?,'PENDING',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $stmt->execute([$drawId,$prizeId,$name ?: null,$phone ?: null,$cpf ?: null,$note ?: null,(int)$draw['last_called_number'],(int)$draw['total_numbers_called'],$claimedBy]);
            $claimId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE draws SET status='CHECKING',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$drawId]);
            $pdo->commit();
            return ['claim_id'=>$claimId,'call_order'=>(int)$draw['total_numbers_called'],'winning_number'=>(int)$draw['last_called_number']];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function homologate(int $drawId, int $adminUserId, bool $continueNext = false): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $draw = self::getDraw($drawId, $pdo);
            if (!$draw) throw new \RuntimeException('Sorteio não encontrado.');
            $prizeId = (int)($draw['prize_id'] ?? 0);

            $lastPending = $pdo->prepare("SELECT MAX(call_order) FROM winner_claims WHERE draw_id=? AND prize_id=? AND status='PENDING'");
            $lastPending->execute([$drawId,$prizeId]);
            $callOrder = (int)$lastPending->fetchColumn();
            if ($callOrder <= 0) throw new \RuntimeException('Não há ganhador pendente para homologação.');

            $claimsStmt = $pdo->prepare("SELECT * FROM winner_claims WHERE draw_id=? AND prize_id=? AND call_order=? AND status='PENDING' ORDER BY id ASC");
            $claimsStmt->execute([$drawId,$prizeId,$callOrder]);
            $claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$claims) throw new \RuntimeException('Não há ganhador pendente para homologação.');

            $eventStmt = $pdo->prepare('SELECT tie_rule FROM events WHERE id=?');
            $eventStmt->execute([(int)$draw['event_id']]);
            $tieRule = (string)($eventStmt->fetchColumn() ?: 'SPLIT');
            $count = count($claims);

            $updateClaim = $pdo->prepare("UPDATE winner_claims SET status='HOMOLOGATED',simultaneous_winners_count=?,tie_resolution_type=?,homologated_by=?,homologated_at=CURRENT_TIMESTAMP WHERE id=?");
            $awardTicket = $pdo->prepare("UPDATE tickets SET status='AWARDED',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='VALID'");
            foreach ($claims as $claim) {
                $updateClaim->execute([$count,$count > 1 ? $tieRule : 'SINGLE',$adminUserId,(int)$claim['id']]);
                if ($claim['winner_type'] === 'DIGITAL' && !empty($claim['ticket_id'])) {
                    $awardTicket->execute([(int)$claim['ticket_id']]);
                }
            }

            $newStatus = $continueNext ? 'IN_PROGRESS' : 'CLOSED';
            $pdo->prepare('UPDATE draws SET status=?,finished_at=CASE WHEN ?=\'CLOSED\' THEN CURRENT_TIMESTAMP ELSE finished_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$newStatus,$newStatus,$drawId]);
            self::syncLegacyRoundCache($drawId, $pdo, $newStatus);
            $pdo->commit();

            return ['homologated'=>count($claims),'call_order'=>$callOrder,'tie_rule'=>$count > 1 ? $tieRule : 'SINGLE','status'=>$newStatus];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function getGameIntelligence(int $drawId): array
    {
        $pdo = Database::getConnection();
        $draw = self::getDraw($drawId, $pdo);
        if (!$draw) return [];

        self::recalculateStates($drawId, $pdo);
        $eventId = (int)$draw['event_id'];

        $statsStmt = $pdo->prepare('SELECT status,COUNT(*) AS count FROM tickets WHERE event_id=? GROUP BY status');
        $statsStmt->execute([$eventId]);
        $statusCounts = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $armadas = $pdo->prepare("SELECT remaining_count,COUNT(*) FROM ticket_game_state tgs JOIN tickets t ON t.id=tgs.ticket_id WHERE tgs.draw_id=? AND t.status IN ('VALID','AWARDED') AND remaining_count IN (0,1,2,3) GROUP BY remaining_count");
        $armadas->execute([$drawId]);
        $near = $armadas->fetchAll(PDO::FETCH_KEY_PAIR);

        $hist = $pdo->prepare("SELECT hits_count,COUNT(*) AS count FROM ticket_game_state tgs JOIN tickets t ON t.id=tgs.ticket_id WHERE tgs.draw_id=? AND t.status IN ('VALID','AWARDED') GROUP BY hits_count ORDER BY hits_count DESC");
        $hist->execute([$drawId]);

        $best = $pdo->prepare("SELECT t.id,t.ticket_number,tgs.remaining_count,tgs.hits_count FROM ticket_game_state tgs JOIN tickets t ON t.id=tgs.ticket_id WHERE tgs.draw_id=? AND t.status IN ('VALID','AWARDED') AND tgs.remaining_count<=3 ORDER BY tgs.remaining_count,t.ticket_number LIMIT 20");
        $best->execute([$drawId]);

        $stonesStmt = $pdo->prepare('SELECT number_value,letter,call_order,called_at FROM draw_stones WHERE draw_id=? ORDER BY call_order');
        $stonesStmt->execute([$drawId]);
        $stones = $stonesStmt->fetchAll(PDO::FETCH_ASSOC);

        $claimsStmt = $pdo->prepare("SELECT id,winner_type,ticket_id,physical_name,winning_number,call_order,status,simultaneous_winners_count,tie_resolution_type FROM winner_claims WHERE draw_id=? ORDER BY call_order,id");
        $claimsStmt->execute([$drawId]);
        $claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

        $validCount = (int)($statusCounts['VALID'] ?? 0);
        $awardedCount = (int)($statusCounts['AWARDED'] ?? 0);

        return [
            'draw'=>$draw,
            'summary'=>[
                'total_sold'=>$validCount+$awardedCount,
                'valid_tickets'=>$validCount+$awardedCount,
                'pending_tickets'=>(int)($statusCounts['PENDING'] ?? 0),
                'reserved_tickets'=>(int)($statusCounts['RESERVED'] ?? 0),
                'cancelled_tickets'=>(int)($statusCounts['CANCELLED'] ?? 0),
                'invalid_tickets'=>(int)($statusCounts['INVALID'] ?? 0),
                'total_called'=>count($stones),
                'last_called_number'=>$draw['last_called_number'],
                'last_called_letter'=>$draw['last_called_letter'],
                'falta_1'=>(int)($near[1] ?? 0),
                'falta_2'=>(int)($near[2] ?? 0),
                'falta_3'=>(int)($near[3] ?? 0),
                'pending_claims'=>count(array_filter($claims, fn(array $c): bool => $c['status']==='PENDING')),
                'homologated_winners'=>count(array_filter($claims, fn(array $c): bool => $c['status']==='HOMOLOGATED')),
            ],
            'last_5_called'=>array_slice(array_reverse($stones),0,5),
            'called_numbers'=>$stones,
            'score_distribution'=>$hist->fetchAll(PDO::FETCH_ASSOC),
            'best_tickets'=>$best->fetchAll(PDO::FETCH_ASSOC),
            'claims'=>$claims,
        ];
    }

    public static function getOrCreateActiveDraw(int $eventId, ?int $roundId = null): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM draws WHERE event_id=? AND status IN ('OPEN','IN_PROGRESS','CHECKING')";
        $params = [$eventId];
        if ($roundId !== null) {
            $sql .= ' AND (round_id=? OR round_id IS NULL)';
            $params[] = $roundId;
        }
        $sql .= ' ORDER BY CASE WHEN round_id IS NOT NULL THEN 0 ELSE 1 END,id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $draw = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($draw) {
            if ($roundId !== null && empty($draw['round_id'])) {
                $pdo->prepare('UPDATE draws SET round_id=? WHERE id=?')->execute([$roundId,(int)$draw['id']]);
                $draw['round_id'] = $roundId;
            }
            return $draw;
        }

        $prizeStmt = $pdo->prepare('SELECT id FROM prizes WHERE event_id=? AND active=1 ORDER BY order_num LIMIT 1');
        $prizeStmt->execute([$eventId]);
        $prizeId = (int)($prizeStmt->fetchColumn() ?: 0);
        if ($prizeId <= 0) throw new \RuntimeException('Evento sem prêmio ativo.');

        $create = $pdo->prepare("INSERT INTO draws (event_id,round_id,prize_id,status,total_numbers_called,created_at,updated_at) VALUES (?,?,?,'OPEN',0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $create->execute([$eventId,$roundId,$prizeId]);
        $drawId = (int)$pdo->lastInsertId();
        self::recalculateStates($drawId, $pdo);
        return self::getDraw($drawId, $pdo) ?: [];
    }

    public static function getBingoLetter(int $number): string
    {
        if ($number>=1 && $number<=15) return 'B';
        if ($number<=30) return 'I';
        if ($number<=45) return 'N';
        if ($number<=60) return 'G';
        if ($number<=75) return 'O';
        return '';
    }

    private static function recalculateStates(int $drawId, PDO $pdo): void
    {
        $draw = self::getDraw($drawId, $pdo);
        if (!$draw) return;
        $prizeId = (int)($draw['prize_id'] ?? 0);
        $ruleStmt = $pdo->prepare('SELECT victory_rule FROM prizes WHERE id=?');
        $ruleStmt->execute([$prizeId]);
        $rule = strtoupper((string)($ruleStmt->fetchColumn() ?: 'FULL_CARD'));

        $stonesStmt = $pdo->prepare('SELECT number_value FROM draw_stones WHERE draw_id=?');
        $stonesStmt->execute([$drawId]);
        $called = array_fill_keys(array_map('intval',$stonesStmt->fetchAll(PDO::FETCH_COLUMN)), true);

        $ticketsStmt = $pdo->prepare("SELECT id FROM tickets WHERE event_id=? AND status IN ('VALID','AWARDED')");
        $ticketsStmt->execute([(int)$draw['event_id']]);
        $ticketIds = array_map('intval',$ticketsStmt->fetchAll(PDO::FETCH_COLUMN));

        $cellsStmt = $pdo->prepare('SELECT number_value,row_index,col_index,is_center FROM ticket_numbers WHERE ticket_id=? ORDER BY row_index,col_index');
        $existsStmt = $pdo->prepare('SELECT id FROM ticket_game_state WHERE draw_id=? AND ticket_id=? LIMIT 1');
        $insertStmt = $pdo->prepare('INSERT INTO ticket_game_state (draw_id,ticket_id,hits_count,needed_count,remaining_count,is_winner,updated_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $updateStmt = $pdo->prepare('UPDATE ticket_game_state SET hits_count=?,needed_count=?,remaining_count=?,is_winner=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');

        foreach ($ticketIds as $ticketId) {
            $cellsStmt->execute([$ticketId]);
            $cells = $cellsStmt->fetchAll(PDO::FETCH_ASSOC);
            [$hits,$needed,$remaining,$winner] = self::evaluateRule($cells,$called,$rule,$prizeId,$pdo,(int)$draw['event_id']);
            $existsStmt->execute([$drawId,$ticketId]);
            $stateId = (int)($existsStmt->fetchColumn() ?: 0);
            if ($stateId > 0) $updateStmt->execute([$hits,$needed,$remaining,$winner?1:0,$stateId]);
            else $insertStmt->execute([$drawId,$ticketId,$hits,$needed,$remaining,$winner?1:0]);
        }
    }

    private static function evaluateRule(array $cells, array $called, string $rule, int $prizeId, PDO $pdo, int $eventId): array
    {
        $isMarked = static function(array $cell) use ($called): bool {
            return (int)$cell['is_center'] === 1 || isset($called[(int)$cell['number_value']]);
        };
        $nonCenter = array_values(array_filter($cells, fn(array $c): bool => (int)$c['is_center'] !== 1));
        $totalHits = count(array_filter($nonCenter,$isMarked));

        if ($rule === 'FULL_CARD') {
            $needed = count($nonCenter);
            return [$totalHits,$needed,max(0,$needed-$totalHits),$totalHits >= $needed];
        }

        $patterns = [];
        if (in_array($rule,['LINE','HORIZONTAL_LINE'],true)) {
            for($row=1;$row<=5;$row++) $patterns[] = array_values(array_filter($cells,fn(array $c): bool => (int)$c['row_index']===$row));
        } elseif ($rule === 'VERTICAL_LINE') {
            for($col=1;$col<=5;$col++) $patterns[] = array_values(array_filter($cells,fn(array $c): bool => (int)$c['col_index']===$col));
        } elseif ($rule === 'FOUR_CORNERS') {
            $patterns[] = array_values(array_filter($cells,fn(array $c): bool => in_array([(int)$c['row_index'],(int)$c['col_index']],[[1,1],[1,5],[5,1],[5,5]],true)));
        } elseif ($rule === 'DIAGONAL') {
            $patterns[] = array_values(array_filter($cells,fn(array $c): bool => (int)$c['row_index']===(int)$c['col_index']));
            $patterns[] = array_values(array_filter($cells,fn(array $c): bool => ((int)$c['row_index']+(int)$c['col_index'])===6));
        } else {
            try {
                $stmt = $pdo->prepare('SELECT pattern_matrix_json FROM game_rules WHERE event_id=? AND (rule_type=? OR rule_name=?) LIMIT 1');
                $stmt->execute([$eventId,$rule,$rule]);
                $matrix = json_decode((string)$stmt->fetchColumn(),true);
                if (is_array($matrix)) {
                    $pattern=[];
                    foreach($cells as $cell) {
                        $r=(int)$cell['row_index']-1; $c=(int)$cell['col_index']-1;
                        if (!empty($matrix[$r][$c])) $pattern[]=$cell;
                    }
                    if ($pattern) $patterns[]=$pattern;
                }
            } catch (\Throwable $e) {}
        }

        if (!$patterns) {
            $needed=count($nonCenter);
            return [$totalHits,$needed,max(0,$needed-$totalHits),$totalHits >= $needed];
        }

        $bestRemaining=PHP_INT_MAX; $bestNeeded=0; $winner=false;
        foreach($patterns as $pattern) {
            $required=array_values(array_filter($pattern,fn(array $c): bool => (int)$c['is_center']!==1));
            $marked=count(array_filter($required,$isMarked));
            $remaining=max(0,count($required)-$marked);
            if ($remaining < $bestRemaining) { $bestRemaining=$remaining; $bestNeeded=count($required); }
            if ($remaining===0) $winner=true;
        }
        return [$totalHits,$bestNeeded,$bestRemaining==PHP_INT_MAX?$bestNeeded:$bestRemaining,$winner];
    }

    private static function detectWinnerCandidates(int $drawId, int $prizeId, int $stoneId, int $winningNumber, int $callOrder, PDO $pdo): array
    {
        if ($prizeId <= 0) return [];
        $stmt = $pdo->prepare("SELECT tgs.ticket_id,t.ticket_number,t.buyer_id FROM ticket_game_state tgs JOIN tickets t ON t.id=tgs.ticket_id WHERE tgs.draw_id=? AND tgs.is_winner=1 AND t.status='VALID'");
        $stmt->execute([$drawId]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $inserted=[];
        $exists=$pdo->prepare("SELECT id FROM winner_claims WHERE draw_id=? AND prize_id=? AND winner_type='DIGITAL' AND ticket_id=? AND status IN ('PENDING','HOMOLOGATED') LIMIT 1");
        $insert=$pdo->prepare("INSERT INTO winner_claims (draw_id,prize_id,winner_type,ticket_id,buyer_id,winning_number,call_order,status,claimed_at,created_at) VALUES (?,?,'DIGITAL',?,?,?,?, 'PENDING',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        foreach($candidates as $candidate) {
            $exists->execute([$drawId,$prizeId,(int)$candidate['ticket_id']]);
            if ($exists->fetchColumn()) continue;
            $insert->execute([$drawId,$prizeId,(int)$candidate['ticket_id'],$candidate['buyer_id'] ?: null,$winningNumber,$callOrder]);
            $inserted[]=['ticket_id'=>(int)$candidate['ticket_id'],'ticket_number'=>$candidate['ticket_number']];
        }
        return $inserted;
    }

    private static function syncLegacyRoundCache(int $drawId, PDO $pdo, ?string $forceStatus = null): void
    {
        $draw=self::getDraw($drawId,$pdo);
        if (!$draw || empty($draw['round_id'])) return;
        $stmt=$pdo->prepare('SELECT number_value FROM draw_stones WHERE draw_id=? ORDER BY call_order');
        $stmt->execute([$drawId]);
        $numbers=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        $status=$forceStatus ?: (string)$draw['status'];
        $pdo->prepare('UPDATE rounds SET called_numbers_json=?,last_called_number=?,last_called_at=?,status=? WHERE id=?')
            ->execute([json_encode($numbers),$draw['last_called_number'],end($numbers)!==false?date('Y-m-d H:i:s'):null,$status,(int)$draw['round_id']]);
    }

    private static function getDraw(int $drawId, PDO $pdo): ?array
    {
        $stmt=$pdo->prepare('SELECT * FROM draws WHERE id=? LIMIT 1');
        $stmt->execute([$drawId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
