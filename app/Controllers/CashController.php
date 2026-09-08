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

class CashController
{
    public function index(): void
    {
        $dayId = (int)($_GET['day_id'] ?? $_GET['id'] ?? 0);
        $tab = trim($_GET['tab'] ?? 'fluxo');
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
            $day = $stmt->fetch();
        }

        if (!$day) {
            // Se ainda não houver nenhum dia cadastrado
            View::render('day/no_day', [
                'title' => 'Caixa & Operação — Show de Prêmios'
            ]);
            return;
        }

        // 1. Dados de Fluxo e Movimentação de Caixa
        $cashData = CashService::calculateDayCash($day['id']);

        $stmtMv = $pdo->prepare("
            SELECT cm.*, u.name as user_name 
            FROM cash_movements cm 
            LEFT JOIN users u ON u.id = cm.created_by 
            WHERE cm.operation_day_id = ? 
            ORDER BY cm.created_at DESC
        ");
        $stmtMv->execute([$day['id']]);
        $movements = $stmtMv->fetchAll();

        // 2. Vendedores ativos
        $sellers = [];
        try {
            $sellers = $pdo->query("SELECT id, name, nickname FROM sellers WHERE active = 1 ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {}

        // 3. Dados do Dia e Rodadas
        $dayReport = ReportService::getDayReport($day['id']);

        $stmtOpenRound = $pdo->prepare("SELECT * FROM rounds WHERE operation_day_id = ? AND status IN ('OPEN', 'IN_PROGRESS') LIMIT 1");
        $stmtOpenRound->execute([$day['id']]);
        $openRound = $stmtOpenRound->fetch();

        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(round_number), 0) + 1 FROM rounds WHERE operation_day_id = ?");
        $stmtMax->execute([$day['id']]);
        $nextRoundNumber = (int)$stmtMax->fetchColumn();

        $cardColors = [];
        try {
            $cardColors = $pdo->query("SELECT * FROM card_colors ORDER BY id ASC")->fetchAll();
        } catch (\Throwable $e) {}

        // 4. Regras de Preço e Configurações para Simulador
        $pricingRule = PricingService::getActiveRule();
        $stmtSettings = $pdo->query("SELECT key, value FROM settings");
        $settings = $stmtSettings ? $stmtSettings->fetchAll(\PDO::FETCH_KEY_PAIR) : [];

        // 5. Todos os dias para filtro seletor de dia
        $allDays = $pdo->query("SELECT id, operation_date, status FROM operation_days ORDER BY operation_date DESC LIMIT 30")->fetchAll();

        View::render('cash/index', [
            'title' => 'Caixa & Operação — ' . View::date($day['operation_date']),
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
        $type = trim($_POST['type'] ?? 'WITHDRAWAL');
        $paymentMethod = strtoupper(trim($_POST['payment_method'] ?? 'CASH'));
        $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
        $amount = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['amount'] ?? '0'));
        $reason = trim($_POST['reason'] ?? '');

        if (!in_array($paymentMethod, ['CASH', 'PIX', 'DEBIT', 'CREDIT'], true)) {
            $paymentMethod = 'CASH';
        }

        if ($amount <= 0) {
            Response::redirect('/caixa?day_id=' . $dayId . '&tab=fluxo', null, 'O valor da movimentação deve ser maior que zero.');
        }

        if (empty($reason)) {
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
        $mvId = (int)$pdo->lastInsertId();

        AuditService::log('CASH_MOVEMENT', 'cash_movements', $mvId, null, [
            'type' => $type,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'seller_id' => $sellerId,
            'reason' => $reason
        ]);

        Response::redirect('/caixa?day_id=' . $dayId . '&tab=fluxo', 'Movimentação de caixa registrada com sucesso.');
    }
}
