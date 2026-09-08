<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;

class SpeakerController
{
    public static function getLetterForNumber(int $num): string
    {
        if ($num >= 1 && $num <= 15) return 'B';
        if ($num >= 16 && $num <= 30) return 'I';
        if ($num >= 31 && $num <= 45) return 'N';
        if ($num >= 46 && $num <= 60) return 'G';
        if ($num >= 61 && $num <= 75) return 'O';
        return '';
    }

    public static function generateSpeakerToken(int $roundId, string $date = ''): string
    {
        $secret = $_ENV['APP_KEY'] ?? 'showdepremios_locutor_celular_2026';
        return substr(hash_hmac('sha256', "speaker_round_{$roundId}_{$date}", $secret), 0, 24);
    }

    public static function validateSpeakerToken(int $roundId, string $token): bool
    {
        if (empty($token) || $roundId <= 0) return false;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT r.id, d.operation_date 
                FROM rounds r 
                JOIN operation_days d ON d.id = r.operation_day_id 
                WHERE r.id = ?
            ");
            $stmt->execute([$roundId]);
            $round = $stmt->fetch();
            if (!$round) return false;

            $expected = self::generateSpeakerToken($roundId, (string)$round['operation_date']);
            return hash_equals($expected, $token);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(): void
    {
        $pdo = Database::getConnection();
        $roundId = (int)($_GET['id'] ?? $_GET['round_id'] ?? 0);

        // 1. Busca rodada especificada ou a rodada ativa do dia
        if ($roundId > 0) {
            $stmt = $pdo->prepare("
                SELECT r.*, d.status as day_status, d.operation_date 
                FROM rounds r 
                JOIN operation_days d ON d.id = r.operation_day_id 
                WHERE r.id = ?
            ");
            $stmt->execute([$roundId]);
            $round = $stmt->fetch();
        } else {
            // Busca a rodada mais relevante (priorizando em andamento, conferência ou aberta)
            $stmt = $pdo->query("
                SELECT r.*, d.status as day_status, d.operation_date 
                FROM rounds r 
                JOIN operation_days d ON d.id = r.operation_day_id 
                WHERE d.status = 'OPEN'
                ORDER BY 
                    CASE 
                        WHEN r.status = 'IN_PROGRESS' THEN 0 
                        WHEN r.status = 'CHECKING' THEN 1 
                        WHEN r.status = 'PAUSED' THEN 2 
                        WHEN r.status = 'OPEN' THEN 3 
                        ELSE 4 
                    END,
                    r.round_number DESC 
                LIMIT 1
            ");
            $round = $stmt->fetch();
        }

        if (!$round) {
            // Se ainda não achou, pega a última cadastrada
            $stmtLast = $pdo->query("
                SELECT r.*, d.status as day_status, d.operation_date 
                FROM rounds r 
                JOIN operation_days d ON d.id = r.operation_day_id 
                ORDER BY r.id DESC 
                LIMIT 1
            ");
            $round = $stmtLast->fetch();
        }

        if (!$round) {
            View::render('speaker/waiting', ['title' => 'Locutor — Aguardando Rodada'], false);
            return;
        }

        $calledNumbers = [];
        if (!empty($round['called_numbers_json'])) {
            $calledNumbers = json_decode($round['called_numbers_json'], true) ?: [];
        }

        // Preços e regras da rodada
        $pricingRule = \App\Services\PricingService::getRuleForDate($round['operation_date']);
        $singlePrice = (!empty($round['single_price']) && (float)$round['single_price'] > 0)
            ? (float)$round['single_price']
            : (float)$pricingRule['single_price'];
        $bundleQty = (!empty($round['bundle_quantity']) && (int)$round['bundle_quantity'] > 0)
            ? (int)$round['bundle_quantity']
            : (int)$pricingRule['bundle_quantity'];
        $bundlePrice = (!empty($round['bundle_price']) && (float)$round['bundle_price'] > 0)
            ? (float)$round['bundle_price']
            : (float)$pricingRule['bundle_price'];

        // Vendedores para datalist de ganhadores
        $sellers = [];
        try {
            $sellers = $pdo->query("SELECT id, name, nickname FROM sellers WHERE active = 1 ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {}

        View::render('speaker/index', [
            'title' => "Locutor & Sorteio — Rodada {$round['round_number']}",
            'round' => $round,
            'calledNumbers' => $calledNumbers,
            'sellers' => $sellers,
            'singlePrice' => $singlePrice,
            'bundleQty' => $bundleQty,
            'bundlePrice' => $bundlePrice,
        ]);
    }

    public function callNumber(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $number = (int)($_POST['number'] ?? 0);

        if ($number < 1 || $number > 75) {
            self::jsonResponse(['success' => false, 'error' => 'O número sorteado deve estar entre 1 e 75.'], 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            self::jsonResponse(['success' => false, 'error' => 'Rodada não encontrada.'], 404);
        }

        if ($round['status'] === 'CLOSED') {
            self::jsonResponse(['success' => false, 'error' => 'Esta rodada já está encerrada. Reabra-a antes de sortear números.'], 400);
        }

        $calledNumbers = [];
        if (!empty($round['called_numbers_json'])) {
            $calledNumbers = json_decode($round['called_numbers_json'], true) ?: [];
        }

        if (in_array($number, $calledNumbers, true)) {
            $letter = self::getLetterForNumber($number);
            self::jsonResponse([
                'success' => false, 
                'already_called' => true,
                'error' => "A pedra {$letter}-{$number} já foi cantada anteriormente nesta rodada!"
            ], 400);
        }

        $calledNumbers[] = $number;
        $now = date('Y-m-d H:i:s');
        $letter = self::getLetterForNumber($number);

        // Se a rodada estava OPEN, inicia automaticamente para IN_PROGRESS
        $newStatus = $round['status'] === 'OPEN' ? 'IN_PROGRESS' : $round['status'];

        $stmtUp = $pdo->prepare("
            UPDATE rounds 
            SET called_numbers_json = ?, 
                last_called_number = ?, 
                last_called_at = ?,
                status = ?
            WHERE id = ?
        ");
        $stmtUp->execute([
            json_encode($calledNumbers),
            $number,
            $now,
            $newStatus,
            $roundId
        ]);

        AuditService::log('BINGO_NUMBER_CALL', 'rounds', $roundId, null, [
            'number' => $number,
            'letter' => $letter,
            'total_called' => count($calledNumbers)
        ]);

        self::jsonResponse([
            'success' => true,
            'number' => $number,
            'letter' => $letter,
            'called_numbers' => $calledNumbers,
            'total_called' => count($calledNumbers),
            'status' => $newStatus,
            'last_called_at' => $now
        ]);
    }

    public function undoNumber(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            self::jsonResponse(['success' => false, 'error' => 'Rodada não encontrada.'], 404);
        }

        $calledNumbers = json_decode($round['called_numbers_json'] ?: '[]', true) ?: [];
        if (empty($calledNumbers)) {
            self::jsonResponse(['success' => false, 'error' => 'Nenhum número foi cantado ainda nesta rodada.'], 400);
        }

        $removed = array_pop($calledNumbers);
        $lastNumber = !empty($calledNumbers) ? end($calledNumbers) : null;
        $now = date('Y-m-d H:i:s');

        $stmtUp = $pdo->prepare("
            UPDATE rounds 
            SET called_numbers_json = ?, 
                last_called_number = ?, 
                last_called_at = ?
            WHERE id = ?
        ");
        $stmtUp->execute([
            json_encode(array_values($calledNumbers)),
            $lastNumber,
            $now,
            $roundId
        ]);

        AuditService::log('BINGO_NUMBER_UNDO', 'rounds', $roundId, null, [
            'removed_number' => $removed,
            'remaining_count' => count($calledNumbers)
        ]);

        self::jsonResponse([
            'success' => true,
            'removed_number' => $removed,
            'called_numbers' => array_values($calledNumbers),
            'last_called_number' => $lastNumber,
            'last_letter' => $lastNumber ? self::getLetterForNumber($lastNumber) : '',
            'total_called' => count($calledNumbers)
        ]);
    }

    public function updateStatus(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['OPEN', 'IN_PROGRESS', 'PAUSED', 'CHECKING', 'CLOSED'];

        if (!in_array($newStatus, $allowed, true)) {
            self::jsonResponse(['success' => false, 'error' => 'Status inválido informado.'], 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            self::jsonResponse(['success' => false, 'error' => 'Rodada não encontrada.'], 404);
        }

        $closedAt = $newStatus === 'CLOSED' ? date('Y-m-d H:i:s') : ($round['closed_at'] ?? null);

        $stmtUp = $pdo->prepare("UPDATE rounds SET status = ?, closed_at = ? WHERE id = ?");
        $stmtUp->execute([$newStatus, $closedAt, $roundId]);

        AuditService::log('ROUND_STATUS_CHANGE', 'rounds', $roundId, null, [
            'old_status' => $round['status'],
            'new_status' => $newStatus
        ]);

        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json') || isset($_POST['_ajax'])) {
            self::jsonResponse([
                'success' => true,
                'status' => $newStatus,
                'message' => "Status da rodada alterado para: {$newStatus}"
            ]);
        }

        Response::redirect("/locutor?id={$roundId}", "Status da rodada atualizado com sucesso.");
    }

    public function saveWinner(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $prizeIndex = (int)($_POST['prize_index'] ?? 1);
        $winnerName = trim($_POST['winner_name'] ?? '');
        $sellerName = trim($_POST['seller_name'] ?? '');
        $continueNext = !empty($_POST['continue_next']);

        if (empty($winnerName)) {
            self::jsonResponse(['success' => false, 'error' => 'Informe o nome do ganhador da cartela.'], 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            self::jsonResponse(['success' => false, 'error' => 'Rodada não encontrada.'], 404);
        }

        $fieldWinner = "winner_{$prizeIndex}_name";
        $fieldSeller = "seller_{$prizeIndex}_name";

        // Se continuar para o próximo prêmio, status volta para IN_PROGRESS
        $newStatus = $continueNext ? 'IN_PROGRESS' : $round['status'];

        $stmtUp = $pdo->prepare("
            UPDATE rounds 
            SET {$fieldWinner} = ?, 
                {$fieldSeller} = ?,
                status = ?
            WHERE id = ?
        ");
        $stmtUp->execute([
            $winnerName,
            $sellerName ?: null,
            $newStatus,
            $roundId
        ]);

        AuditService::log('ROUND_WINNER_SET', 'rounds', $roundId, null, [
            'prize_index' => $prizeIndex,
            'winner_name' => $winnerName,
            'seller_name' => $sellerName,
            'continued' => $continueNext
        ]);

        self::jsonResponse([
            'success' => true,
            'prize_index' => $prizeIndex,
            'winner_name' => $winnerName,
            'seller_name' => $sellerName,
            'status' => $newStatus,
            'message' => "Ganhador do {$prizeIndex}º Prêmio registrado com sucesso!"
        ]);
    }

    public function clearNumbers(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $pdo = Database::getConnection();

        $stmtUp = $pdo->prepare("
            UPDATE rounds 
            SET called_numbers_json = '[]', 
                last_called_number = NULL, 
                last_called_at = NULL 
            WHERE id = ?
        ");
        $stmtUp->execute([$roundId]);

        AuditService::log('BINGO_NUMBERS_CLEAR', 'rounds', $roundId, null, []);

        self::jsonResponse(['success' => true, 'message' => 'Tabuleiro de pedras resetado com sucesso.']);
    }

    private static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
