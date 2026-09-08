<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\PricingService;
use App\Services\PrizeSuggestionService;

class UtilitiesController
{
    public function cashCounter(): void
    {
        $pdo = Database::getConnection();
        $stmtDay = $pdo->query("SELECT id, operation_date, status, initial_cash FROM operation_days WHERE status = 'OPEN' ORDER BY operation_date DESC LIMIT 1");
        $activeDay = $stmtDay->fetch();

        View::render('utilities/cash_counter', [
            'title' => 'Contador de Cédulas e Moedas — Show de Prêmios',
            'activeDay' => $activeDay ?: null,
        ]);
    }

    public function simulator(): void
    {
        $pricingRule = PricingService::getActiveRule();

        $pdo = Database::getConnection();
        $stmtSettings = $pdo->query("SELECT key, value FROM settings");
        $settings = $stmtSettings ? $stmtSettings->fetchAll(\PDO::FETCH_KEY_PAIR) : [];

        View::render('utilities/simulator', [
            'title' => 'Simulador de Premiações & Vendas — Show de Prêmios',
            'pricingRule' => $pricingRule,
            'settings' => $settings,
        ]);
    }
}
