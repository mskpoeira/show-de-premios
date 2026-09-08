<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\CashService;
use App\Services\ReportService;
use PDO;

class DayController
{
    public function current(): void
    {
        $pdo = Database::getConnection();

        // Get requested day ID from GET, or active OPEN day, or latest day
        $dayId = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($dayId) {
            $stmt = $pdo->prepare("SELECT * FROM operation_days WHERE id = ?");
            $stmt->execute([$dayId]);
            $day = $stmt->fetch();
        } else {
            $stmt = $pdo->query("SELECT * FROM operation_days WHERE status = 'OPEN' ORDER BY operation_date DESC LIMIT 1");
            $day = $stmt->fetch();

            if (!$day) {
                // If no day open, get latest
                $stmt = $pdo->query("SELECT * FROM operation_days ORDER BY operation_date DESC LIMIT 1");
                $day = $stmt->fetch();
            }
        }

        if (!$day) {
            View::render('day/no_day', [
                'title' => 'Dia de Operação — Show de Prêmios'
            ]);
            return;
        }

        $dayReport = ReportService::getDayReport($day['id']);

        // Check if there is an active OPEN or IN_PROGRESS round
        $stmtOpenRound = $pdo->prepare("SELECT * FROM rounds WHERE operation_day_id = ? AND status IN ('OPEN', 'IN_PROGRESS') LIMIT 1");
        $stmtOpenRound->execute([$day['id']]);
        $openRound = $stmtOpenRound->fetch();

        // Next round number
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(round_number), 0) + 1 FROM rounds WHERE operation_day_id = ?");
        $stmtMax->execute([$day['id']]);
        $nextRoundNumber = (int)$stmtMax->fetchColumn();

        // Available card colors
        $cardColors = [];
        try {
            $cardColors = $pdo->query("SELECT * FROM card_colors ORDER BY id ASC")->fetchAll();
        } catch (\Throwable $e) {}
        if (empty($cardColors)) {
            $cardColors = [
                ['name' => 'Amarela', 'bg_color' => '#fef08a', 'text_color' => '#854d0e', 'border_color' => '#eab308'],
                ['name' => 'Azul', 'bg_color' => '#bae6fd', 'text_color' => '#0369a1', 'border_color' => '#38bdf8'],
                ['name' => 'Verde', 'bg_color' => '#bbf7d0', 'text_color' => '#15803d', 'border_color' => '#4ade80'],
                ['name' => 'Vermelha', 'bg_color' => '#fecaca', 'text_color' => '#b91c1c', 'border_color' => '#f87171'],
                ['name' => 'Rosa', 'bg_color' => '#fbcfe8', 'text_color' => '#be185d', 'border_color' => '#f472b6'],
                ['name' => 'Branca', 'bg_color' => '#ffffff', 'text_color' => '#1e293b', 'border_color' => '#cbd5e1'],
                ['name' => 'Papel Jornal', 'bg_color' => '#e2e8f0', 'text_color' => '#334155', 'border_color' => '#94a3b8'],
                ['name' => 'Laranja', 'bg_color' => '#fed7aa', 'text_color' => '#c2410c', 'border_color' => '#fb923c'],
                ['name' => 'Lilás', 'bg_color' => '#e9d5ff', 'text_color' => '#7e22ce', 'border_color' => '#c084fc'],
            ];
        }

        View::render('day/index', [
            'title' => 'Dia ' . View::date($day['operation_date']) . ' — Show de Prêmios',
            'day' => $day,
            'report' => $dayReport,
            'openRound' => $openRound,
            'nextRoundNumber' => $nextRoundNumber,
            'cardColors' => $cardColors,
        ]);
    }

    public function open(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Suggest current date or '2026-09-06'
            $today = date('Y-m-d');
            $defaultDate = $today >= '2026-09-06' ? $today : '2026-09-06';

            View::render('day/open', [
                'title' => 'Abrir Novo Dia — Show de Prêmios',
                'defaultDate' => $defaultDate,
            ]);
            return;
        }

        $date = trim($_POST['operation_date'] ?? '');
        $initialCash = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['initial_cash'] ?? '0'));
        $notes = trim($_POST['notes'] ?? '');

        if (empty($date)) {
            Response::redirect('/dias/abrir', null, 'Informe a data de operação.');
        }

        // Must be >= 2026-09-06
        if ($date < '2026-09-06') {
            Response::redirect('/dias/abrir', null, 'O sistema não aceita operações anteriores a 06/09/2026.');
        }

        $pdo = Database::getConnection();

        // Check uniqueness
        $stmtCheck = $pdo->prepare("SELECT id FROM operation_days WHERE operation_date = ?");
        $stmtCheck->execute([$date]);
        if ($stmtCheck->fetch()) {
            Response::redirect('/dias/abrir', null, "Já existe um dia cadastrado para " . View::date($date) . ".");
        }

        $stmt = $pdo->prepare("
            INSERT INTO operation_days (operation_date, status, initial_cash, responsible_user_id, notes, opened_at)
            VALUES (?, 'OPEN', ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$date, $initialCash, Auth::id(), $notes]);
        $dayId = (int)$pdo->lastInsertId();

        AuditService::log('DAY_OPEN', 'operation_days', $dayId, null, [
            'date' => $date,
            'initial_cash' => $initialCash
        ]);

        Response::redirect('/dia?id=' . $dayId, "Dia " . View::date($date) . " aberto com sucesso!");
    }

    public function close(): void
    {
        $dayId = (int)($_POST['operation_day_id'] ?? 0);
        $countedCash = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['counted_cash'] ?? '0'));
        $justification = trim($_POST['justification'] ?? '');

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM operation_days WHERE id = ?");
        $stmt->execute([$dayId]);
        $day = $stmt->fetch();

        if (!$day || $day['status'] !== 'OPEN') {
            Response::redirect('/dia?id=' . $dayId, null, 'Este dia não está aberto para fechamento.');
        }

        // Check if there are open or in-progress rounds
        $stmtOpenRounds = $pdo->prepare("SELECT COUNT(*) FROM rounds WHERE operation_day_id = ? AND status IN ('OPEN', 'IN_PROGRESS')");
        $stmtOpenRounds->execute([$dayId]);
        if ((int)$stmtOpenRounds->fetchColumn() > 0) {
            Response::redirect('/dia?id=' . $dayId, null, 'Não é possível fechar o dia com rodadas ainda em andamento ou abertas. Feche todas as rodadas primeiro.');
        }

        // Calculate cash
        $cashData = CashService::calculateDayCash($dayId);
        $expectedCash = $cashData['expected_cash'];
        $diff = round($countedCash - $expectedCash, 2);
        $tolerance = CashService::getTolerance();

        if (abs($diff) > $tolerance && empty($justification)) {
            Response::redirect('/dia?id=' . $dayId, null, "Divergência de caixa (" . View::money($diff) . ") acima da tolerância permitida. É obrigatório fornecer uma justificativa para fechar o dia.");
        }

        $pdo->beginTransaction();

        try {
            // Update day
            $stmtUp = $pdo->prepare("UPDATE operation_days SET status = 'CLOSED', closed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUp->execute([$dayId]);

            // Insert or update cash_closings
            $stmtCheckClose = $pdo->prepare("SELECT id FROM cash_closings WHERE operation_day_id = ?");
            $stmtCheckClose->execute([$dayId]);
            $existingCloseId = $stmtCheckClose->fetchColumn();

            $status = abs($diff) <= $tolerance ? 'OK' : 'DIVERGENCE';

            if ($existingCloseId) {
                $stmtC = $pdo->prepare("
                    UPDATE cash_closings 
                    SET expected_cash = ?, counted_cash = ?, difference = ?, status = ?, justification = ?, responsible_user_id = ?, closed_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmtC->execute([$expectedCash, $countedCash, $diff, $status, $justification, Auth::id(), $existingCloseId]);
            } else {
                $stmtC = $pdo->prepare("
                    INSERT INTO cash_closings (operation_day_id, expected_cash, counted_cash, difference, status, justification, responsible_user_id, closed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtC->execute([$dayId, $expectedCash, $countedCash, $diff, $status, $justification, Auth::id()]);
            }

            $pdo->commit();

            AuditService::log('DAY_CLOSE', 'operation_days', $dayId, null, [
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'difference' => $diff,
                'status' => $status
            ]);

            Response::redirect('/dia?id=' . $dayId, "Dia " . View::date($day['operation_date']) . " fechado com sucesso!");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::redirect('/dia?id=' . $dayId, null, "Erro ao fechar dia: " . $e->getMessage());
        }
    }

    public function reopen(): void
    {
        $dayId = (int)($_POST['operation_day_id'] ?? 0);
        $reason = trim($_POST['reopen_reason'] ?? '');

        if (empty($reason)) {
            Response::redirect('/dia?id=' . $dayId, null, 'É obrigatório registrar o motivo da reabertura do dia.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE operation_days SET status = 'OPEN', closed_at = NULL WHERE id = ?");
        $stmt->execute([$dayId]);

        AuditService::log('DAY_REOPEN', 'operation_days', $dayId, null, ['reason' => $reason]);

        Response::redirect('/dia?id=' . $dayId, 'Dia reaberto com sucesso.');
    }
}
