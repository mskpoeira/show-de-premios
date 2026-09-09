<?php

namespace AppServices;

use AppCoreDatabase;
use PDO;

class TicketService
{
    /**
     * Gera um token seguro de 128 bits de entropia (32 caracteres hexadecimais)
     */
    public static function generateSecureToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Gera um código curto de conferência manual no formato XXXX-XXXX (ex: K7P4-X2MQ)
     */
    public static function generateCheckCode(): string
    {
        // Conjunto de caracteres legíveis evitando confusões visuais (0, O, 1, I)
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $p1 = '';
        $p2 = '';
        for ($i = 0; $i < 4; $i++) {
            $p1 .= $chars[random_int(0, strlen($chars) - 1)];
            $p2 .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $p1 . '-' . $p2;
    }

    /**
     * Obtém o próximo número de controle sequencial e formatado (ex: JDA-0001)
     * Utiliza transação e lock para evitar duplicidade simultânea
     */
    public static function getNextSequenceNumber(int $batchId, PDO $pdo): array
    {
        $stmtBatch = $pdo->prepare("SELECT prefix, current_sequence, end_sequence FROM event_batches WHERE id = ?");
        $stmtBatch->execute([$batchId]);
        $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            throw new \Exception("Lote de cartelas não encontrado.");
        }

        $nextSeq = (int)$batch['current_sequence'] + 1;
        if ($nextSeq > (int)$batch['end_sequence']) {
            throw new \Exception("O lote atingiu a capacidade máxima de numeração (" . $batch['end_sequence'] . "). Crie um novo lote com outro prefixo.");
        }

        // Atualiza a sequência no lote e marca como bloqueado para alterações estruturais
        $stmtUpdate = $pdo->prepare("UPDATE event_batches SET current_sequence = ?, is_locked = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpdate->execute([$nextSeq, $batchId]);

        $prefix = $batch['prefix'];
        $formattedNumber = sprintf("%s-%04d", $prefix, $nextSeq);

        return [
            'sequence_number' => $nextSeq,
            'prefix' => $prefix,
            'ticket_number' => $formattedNumber
        ];
    }

    /**
     * Gera uma grade clássica de Bingo 75 Pedras 5x5
     * B: 1-15, I: 16-30, N: 31-45 (centro livre), G: 46-60, O: 61-75
     */
    public static function generateBingo75Matrix(bool $centerFree = true): array
    {
        $ranges = [
            'B' => range(1, 15),
            'I' => range(16, 30),
            'N' => range(31, 45),
            'G' => range(46, 60),
            'O' => range(61, 75)
        ];

        $columns = [];
        foreach ($ranges as $col => $nums) {
            shuffle($nums);
            $columns[$col] = array_slice($nums, 0, 5);
        }

        $matrix = [];
        $letters = ['B', 'I', 'N', 'G', 'O'];

        for ($row = 1; $row <= 5; $row++) {
            for ($colIdx = 0; $colIdx < 5; $colIdx++) {
                $letter = $letters[$colIdx];
                $isCenter = ($row === 3 && $colIdx === 2);
                $val = $columns[$letter][$row - 1];

                if ($isCenter && $centerFree) {
                    $val = 0; // Centro livre
                }

                $matrix[] = [
                    'column_letter' => $letter,
                    'row_index' => $row,
                    'col_index' => $colIdx + 1,
                    'number_value' => $val,
                    'is_center' => $isCenter ? 1 : 0
                ];
            }
        }

        return $matrix;
    }

    /**
     * Cria e persiste uma nova cartela para um comprador/pedido
     */
    public static function createTicket(
        int $eventId,
        int $batchId,
        ?int $buyerId = null,
        ?int $orderId = null,
        string $status = 'RESERVED',
        ?PDO $pdo = null
    ): array {
        $shouldCommit = false;
        if ($pdo === null) {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            $shouldCommit = true;
        }

        try {
            $seqData = self::getNextSequenceNumber($batchId, $pdo);
            $checkCode = self::generateCheckCode();
            $secureToken = self::generateSecureToken();

            $stmtTicket = $pdo->prepare("
                INSERT INTO tickets (
                    event_id, batch_id, buyer_id, order_id, ticket_number, sequence_number,
                    prefix, check_code, secure_token, grid_type, status, print_count, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '5x5', ?, 0, CURRENT_TIMESTAMP)
            ");

            $stmtTicket->execute([
                $eventId,
                $batchId,
                $buyerId,
                $orderId,
                $seqData['ticket_number'],
                $seqData['sequence_number'],
                $seqData['prefix'],
                $checkCode,
                $secureToken,
                $status
            ]);

            $ticketId = (int)$pdo->lastInsertId();

            // Verifica o tipo de grade do lote
            $stmtBatch = $pdo->prepare("SELECT batch_type, matrix_template_json FROM event_batches WHERE id = ?");
            $stmtBatch->execute([$batchId]);
            $batchRow = $stmtBatch->fetch(PDO::FETCH_ASSOC);

            if ($batchRow && $batchRow['batch_type'] === 'FIXED_GRID' && !empty($batchRow['matrix_template_json'])) {
                $matrix = json_decode($batchRow['matrix_template_json'], true);
            } else {
                $matrix = self::generateBingo75Matrix(true);
            }

            // Insere os números da cartela
            $stmtNum = $pdo->prepare("
                INSERT INTO ticket_numbers (
                    ticket_id, number_value, column_letter, row_index, col_index, is_center, is_hit
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($matrix as $cell) {
                $isHit = ($cell['is_center'] === 1) ? 1 : 0; // Centro livre já começa marcado
                $stmtNum->execute([
                    $ticketId,
                    $cell['number_value'],
                    $cell['column_letter'],
                    $cell['row_index'],
                    $cell['col_index'],
                    $cell['is_center'],
                    $isHit
                ]);
            }

            // Atualiza o total gerado no lote
            $pdo->prepare("UPDATE event_batches SET total_generated = total_generated + 1 WHERE id = ?")->execute([$batchId]);

            // Se a cartela for emitida como VÁLIDA, inicializa o ticket_game_state para draws abertos
            if ($status === 'VALID') {
                self::initGameStateForTicket($ticketId, $eventId, $pdo);
            }

            if ($shouldCommit) {
                $pdo->commit();
            }

            return [
                'id' => $ticketId,
                'ticket_number' => $seqData['ticket_number'],
                'check_code' => $checkCode,
                'secure_token' => $secureToken,
                'status' => $status,
                'matrix' => $matrix
            ];
        } catch (\Throwable $e) {
            if ($shouldCommit && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inicializa o estado de jogo da cartela para sorteios em andamento
     */
    public static function initGameStateForTicket(int $ticketId, int $eventId, PDO $pdo): void
    {
        $stmtDraws = $pdo->prepare("SELECT id FROM draws WHERE event_id = ? AND status IN ('OPEN', 'IN_PROGRESS')");
        $stmtDraws->execute([$eventId]);
        $draws = $stmtDraws->fetchAll(PDO::FETCH_COLUMN);

        $stmtInsertState = $pdo->prepare("
            INSERT OR IGNORE INTO ticket_game_state (draw_id, ticket_id, hits_count, needed_count, remaining_count, is_winner)
            VALUES (?, ?, 0, 24, 24, 0)
        ");

        foreach ($draws as $drawId) {
            $stmtInsertState->execute([$drawId, $ticketId]);
        }
    }

    /**
     * Registra reimpressão de uma cartela (preservando token, número e código)
     */
    public static function recordPrint(int $ticketId, ?int $userId = null, ?string $ip = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET print_count = print_count + 1, last_printed_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);

        $stmtLog = $pdo->prepare("
            INSERT INTO ticket_prints (ticket_id, user_id, ip_address, printed_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmtLog->execute([$ticketId, $userId, $ip]);
    }

    /**
     * Mascara CPF para exibição segura (ex: ***.456.789-**)
     */
    public static function maskCpf(?string $cpf): string
    {
        if (!$cpf) return 'Não informado';
        $clean = preg_replace('/\D/', '', $cpf);
        if (strlen($clean) === 11) {
            return '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**';
        }
        return '***.***.***-**';
    }

    /**
     * Mascara Telefone para exibição segura (ex: +55 (12) 9****-2387)
     */
    public static function maskPhone(?string $phone): string
    {
        if (!$phone) return 'Não informado';
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) >= 10) {
            $last4 = substr($clean, -4);
            $ddd = substr($clean, -11, 2);
            return "+55 ({$ddd}) 9****-{$last4}";
        }
        return '(**) *****-****';
    }

    /**
     * Busca dados completos de uma cartela por token ou ID (para impressão)
     */
    public static function getTicketForPrint(string $tokenOrNumber): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, b.name as buyer_name, b.cpf as buyer_cpf, b.phone as buyer_phone, b.email as buyer_email,
                   e.name as event_name, e.event_date, e.location as event_location, e.description as event_description
            FROM tickets t
            JOIN events e ON e.id = t.event_id
            LEFT JOIN buyers b ON b.id = t.buyer_id
            WHERE t.secure_token = ? OR t.ticket_number = ?
        ");
        $stmt->execute([$tokenOrNumber, $tokenOrNumber]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) return null;

        // Busca a matriz
        $stmtNums = $pdo->prepare("
            SELECT * FROM ticket_numbers 
            WHERE ticket_id = ? 
            ORDER BY row_index ASC, col_index ASC
        ");
        $stmtNums->execute([$ticket['id']]);
        $ticket['numbers'] = $stmtNums->fetchAll(PDO::FETCH_ASSOC);

        // Busca prêmios do evento
        $stmtPrizes = $pdo->prepare("SELECT * FROM prizes WHERE event_id = ? AND active = 1 ORDER BY order_num ASC");
        $stmtPrizes->execute([$ticket['event_id']]);
        $ticket['prizes'] = $stmtPrizes->fetchAll(PDO::FETCH_ASSOC);

        return $ticket;
    }
}
