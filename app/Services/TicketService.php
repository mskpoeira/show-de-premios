<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class TicketService
{
    public static function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 256 bits
    }

    public static function generateCheckCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $parts = ['', ''];
        for ($i = 0; $i < 4; $i++) {
            $parts[0] .= $chars[random_int(0, strlen($chars) - 1)];
            $parts[1] .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $parts[0] . '-' . $parts[1];
    }

    public static function getNextSequenceNumber(int $batchId, PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT prefix, current_sequence, end_sequence FROM event_batches WHERE id = ?");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            throw new \RuntimeException('Lote de cartelas não encontrado.');
        }

        $prefix = strtoupper(trim((string)$batch['prefix']));
        if (!preg_match('/^[A-Z]{3}$/', $prefix)) {
            throw new \RuntimeException('O prefixo do lote deve conter exatamente 3 letras.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE event_batches
            SET current_sequence = current_sequence + 1,
                is_locked = 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND current_sequence < end_sequence
        ");
        $stmtUpdate->execute([$batchId]);
        if ($stmtUpdate->rowCount() !== 1) {
            throw new \RuntimeException('O lote atingiu sua capacidade máxima. Crie ou selecione o próximo lote/prefixo.');
        }

        $stmtCurrent = $pdo->prepare("SELECT current_sequence FROM event_batches WHERE id = ?");
        $stmtCurrent->execute([$batchId]);
        $sequence = (int)$stmtCurrent->fetchColumn();
        if ($sequence < 1 || $sequence > 9999) {
            throw new \RuntimeException('Sequência de cartela fora do intervalo permitido (0001–9999).');
        }

        return [
            'sequence_number' => $sequence,
            'prefix' => $prefix,
            'ticket_number' => sprintf('%s-%04d', $prefix, $sequence),
        ];
    }

    public static function generateBingo75Matrix(bool $centerFree = true): array
    {
        $ranges = [
            'B' => range(1, 15),
            'I' => range(16, 30),
            'N' => range(31, 45),
            'G' => range(46, 60),
            'O' => range(61, 75),
        ];
        $columns = [];
        foreach ($ranges as $column => $numbers) {
            shuffle($numbers);
            $columns[$column] = array_slice($numbers, 0, 5);
        }

        $matrix = [];
        $letters = ['B', 'I', 'N', 'G', 'O'];
        for ($row = 1; $row <= 5; $row++) {
            for ($col = 0; $col < 5; $col++) {
                $letter = $letters[$col];
                $isCenter = $row === 3 && $col === 2;
                $matrix[] = [
                    'column_letter' => $letter,
                    'row_index' => $row,
                    'col_index' => $col + 1,
                    'number_value' => ($isCenter && $centerFree) ? 0 : $columns[$letter][$row - 1],
                    'is_center' => $isCenter ? 1 : 0,
                ];
            }
        }
        return $matrix;
    }

    public static function createTicket(
        int $eventId,
        int $batchId,
        ?int $buyerId = null,
        ?int $orderId = null,
        string $status = 'RESERVED',
        ?PDO $pdo = null
    ): array {
        $ownTransaction = false;
        if ($pdo === null) {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            $ownTransaction = true;
        }

        try {
            $seq = self::getNextSequenceNumber($batchId, $pdo);
            $checkCode = self::generateCheckCode();
            $secureToken = self::generateSecureToken();

            $stmtTicket = $pdo->prepare("
                INSERT INTO tickets (
                    event_id, batch_id, buyer_id, order_id, ticket_number, sequence_number,
                    prefix, check_code, secure_token, grid_type, status, print_count, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '5x5', ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtTicket->execute([
                $eventId, $batchId, $buyerId, $orderId, $seq['ticket_number'], $seq['sequence_number'],
                $seq['prefix'], $checkCode, $secureToken, $status,
            ]);
            $ticketId = (int)$pdo->lastInsertId();

            $stmtBatch = $pdo->prepare("SELECT batch_type, matrix_template_json FROM event_batches WHERE id = ?");
            $stmtBatch->execute([$batchId]);
            $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
            $matrix = ($batch && $batch['batch_type'] === 'FIXED_GRID' && !empty($batch['matrix_template_json']))
                ? (json_decode((string)$batch['matrix_template_json'], true) ?: self::generateBingo75Matrix(true))
                : self::generateBingo75Matrix(true);

            $stmtNum = $pdo->prepare("
                INSERT INTO ticket_numbers (ticket_id, number_value, column_letter, row_index, col_index, is_center, is_hit, hit_at_call_id)
                VALUES (?, ?, ?, ?, ?, ?, 0, NULL)
            ");
            foreach ($matrix as $cell) {
                $stmtNum->execute([
                    $ticketId,
                    $cell['number_value'],
                    $cell['column_letter'],
                    $cell['row_index'],
                    $cell['col_index'],
                    $cell['is_center'],
                ]);
            }

            $pdo->prepare("UPDATE event_batches SET total_generated = total_generated + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$batchId]);

            if ($status === 'VALID') {
                self::initGameStateForTicket($ticketId, $eventId, $pdo);
            }

            if ($ownTransaction) {
                $pdo->commit();
            }

            return [
                'id' => $ticketId,
                'ticket_number' => $seq['ticket_number'],
                'check_code' => $checkCode,
                'secure_token' => $secureToken,
                'status' => $status,
                'matrix' => $matrix,
            ];
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function initGameStateForTicket(int $ticketId, int $eventId, PDO $pdo): void
    {
        $stmtDraws = $pdo->prepare("SELECT id FROM draws WHERE event_id = ? AND status IN ('OPEN','IN_PROGRESS','CHECKING')");
        $stmtDraws->execute([$eventId]);
        $drawIds = $stmtDraws->fetchAll(PDO::FETCH_COLUMN);

        $stmtExists = $pdo->prepare("SELECT id FROM ticket_game_state WHERE draw_id = ? AND ticket_id = ? LIMIT 1");
        $stmtInsert = $pdo->prepare("INSERT INTO ticket_game_state (draw_id, ticket_id, hits_count, needed_count, remaining_count, is_winner, updated_at) VALUES (?, ?, 0, 24, 24, 0, CURRENT_TIMESTAMP)");
        foreach ($drawIds as $drawId) {
            $stmtExists->execute([(int)$drawId, $ticketId]);
            if (!$stmtExists->fetchColumn()) {
                $stmtInsert->execute([(int)$drawId, $ticketId]);
            }
        }
    }

    public static function recordPrint(int $ticketId, ?int $userId = null, ?string $ip = null): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE tickets SET print_count = print_count + 1, last_printed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$ticketId]);
        $pdo->prepare("INSERT INTO ticket_prints (ticket_id, user_id, ip_address, printed_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
            ->execute([$ticketId, $userId, $ip]);
    }

    public static function maskCpf(?string $cpf): string
    {
        if (!$cpf) return 'Não informado';
        $clean = preg_replace('/\D/', '', $cpf);
        return strlen($clean) === 11 ? '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**' : '***.***.***-**';
    }

    public static function maskPhone(?string $phone): string
    {
        if (!$phone) return 'Não informado';
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) >= 10) {
            $last4 = substr($clean, -4);
            $ddd = substr($clean, -11, 2);
            return "+55 ({$ddd}) *****-{$last4}";
        }
        return '(**) *****-****';
    }

    public static function getTicketForPrint(string $secureToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $secureToken)) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, b.name AS buyer_name, b.cpf AS buyer_cpf, b.phone AS buyer_phone, b.email AS buyer_email,
                   e.name AS event_name, e.event_date, e.location AS event_location, e.description AS event_description
            FROM tickets t
            JOIN events e ON e.id = t.event_id
            LEFT JOIN buyers b ON b.id = t.buyer_id
            WHERE t.secure_token = ?
            LIMIT 1
        ");
        $stmt->execute([$secureToken]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) return null;

        $stmtNums = $pdo->prepare("SELECT * FROM ticket_numbers WHERE ticket_id = ? ORDER BY row_index ASC, col_index ASC");
        $stmtNums->execute([(int)$ticket['id']]);
        $ticket['numbers'] = $stmtNums->fetchAll(PDO::FETCH_ASSOC);

        $stmtPrizes = $pdo->prepare("SELECT * FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC");
        $stmtPrizes->execute([(int)$ticket['event_id']]);
        $ticket['prizes'] = $stmtPrizes->fetchAll(PDO::FETCH_ASSOC);

        return $ticket;
    }
}
