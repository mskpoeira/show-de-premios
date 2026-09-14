<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\CashService;
use App\Services\PricingService;
use App\Services\ReportService;
use PDO;

class CashController
{
    public function index(): void
    {
        $dayId = (int)($_GET['day_id'] ?? $_GET['id'] ?? 0);
        $tab = trim((string)($_GET['tab'] ?? 'fluxo'));
        if ($tab === 'dia' || !in_array($tab, ['fluxo', 'contador', 'simulador'], true)) {
            $tab = 'fluxo';
        }

        $pdo = Database::getConnection();

        if (!$dayId) {
            $stmt = $pdo->query("SELECT id FROM operation_days WHERE status = 'OPEN' ORDER BY operation_date DESC LIMIT 1");
            $dayId = (int)($stmt->fetchColumn() ?: 0);
            if (!$dayId) {
                $stmt = $pdo->query("SELECT id FROM operation_days ORDER BY operation_date DESC LIMIT 1");
                $dayId = (int)($stmt->fetchColumn() ?: 0);
            }
        }

        $day = null;
        if ($dayId) {
            $stmt = $pdo->prepare("SELECT * FROM operation_days WHERE id = ?");
            $stmt->execute([$dayId]);
            $day = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$day) {
            View::render('day/no_day', [
                'title' => 'Caixa & Operação — Show de Prêmios'
            ]);
            return;
        }

        $cashData = CashService::calculateDayCash((int)$day['id']);

        $stmtMv = $pdo->prepare("
            SELECT cm.*, u.name as user_name
            FROM cash_movements cm
            LEFT JOIN users u ON u.id = cm.created_by
            WHERE cm.operation_day_id = ?
            ORDER BY cm.created_at DESC
        ");
        $stmtMv->execute([(int)$day['id']]);
        $movements = $stmtMv->fetchAll(PDO::FETCH_ASSOC);

        $sellers = [];
        try {
            $sellers = $pdo->query("SELECT id, name, nickname FROM sellers WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[CashController sellers] ' . $e->getMessage());
        }

        $dayReport = ReportService::getDayReport((int)$day['id']);

        $stmtOpenRound = $pdo->prepare("SELECT * FROM rounds WHERE operation_day_id = ? AND status IN ('OPEN', 'IN_PROGRESS') LIMIT 1");
        $stmtOpenRound->execute([(int)$day['id']]);
        $openRound = $stmtOpenRound->fetch(PDO::FETCH_ASSOC);

        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(round_number), 0) + 1 FROM rounds WHERE operation_day_id = ?");
        $stmtMax->execute([(int)$day['id']]);
        $nextRoundNumber = (int)$stmtMax->fetchColumn();

        $cardColors = [];
        try {
            $cardColors = $pdo->query("SELECT * FROM card_colors ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[CashController card colors] ' . $e->getMessage());
        }

        $pricingRule = PricingService::getActiveRule();
        $stmtSettings = $pdo->query("SELECT key, value FROM settings");
        $settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

        $allDays = $pdo->query("SELECT id, operation_date, status FROM operation_days ORDER BY operation_date DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

        View::render('cash/index', [
            'title' => 'Caixa & Operação — ' . View::date((string)$day['operation_date']),
            'tab' => $tab,
            'day' => $day,
            'cash' => $cashData,
            'movements' => $movements,
            'sellers' => $sellers,
            'dayReport' => $dayReport,
            'openRound' => $openRound,
            'nextRoundNumber' => $nextRoundNumber,
            'cardColors' => $cardColors,
            'pricingRule' => $pricingRule,
            'settings' => $settings,
            'allDays' => $allDays,
        ]);
    }

    public function addMovement(): void
    {
        $dayId = (int)($_POST['operation_day_id'] ?? 0);
        $type = trim((string)($_POST['type'] ?? 'WITHDRAWAL'));
        $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH')));
        $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
        $amount = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', (string)($_POST['amount'] ?? '0')));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!in_array($paymentMethod, ['CASH', 'PIX', 'DEBIT', 'CREDIT'], true)) {
            $paymentMethod = 'CASH';
        }

        if ($amount <= 0) {
            Response::redirect('/caixa?day_id=' . $dayId . '&tab=fluxo', null, 'O valor da movimentação deve ser maior que zero.');
        }

        if ($reason === '') {
            Response::redirect('/caixa?day_id=' . $dayId . '&tab=fluxo', null, 'Informe o motivo da movimentação de caixa.');
        }

        if (!in_array($type, ['WITHDRAWAL', 'INFLOW', 'OUTFLOW'], true)) {
            $type = 'WITHDRAWAL';
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO cash_movements (operation_day_id, type, amount, reason, payment_method, seller_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$dayId, $type, $amount, $reason, $paymentMethod, $sellerId, Auth::id()]);
        $movementId = (int)$pdo->lastInsertId();

        AuditService::log('CASH_MOVEMENT', 'cash_movements', $movementId, null, [
            'type' => $type,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'seller_id' => $sellerId,
            'reason' => $reason
        ]);

        Response::redirect('/caixa?day_id=' . $dayId . '&tab=fluxo', 'Movimentação de caixa registrada com sucesso.');
    }
}
