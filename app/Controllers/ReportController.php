<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\ReportService;

class ReportController
{
    public function index(): void
    {
        $pdo = Database::getConnection();

        // Get available days
        $stmtDays = $pdo->query("SELECT id, operation_date, status FROM operation_days ORDER BY operation_date DESC");
        $days = $stmtDays->fetchAll();

        // Filters
        $tab = $_GET['tab'] ?? 'diario';
        $dayId = isset($_GET['day_id']) ? (int)$_GET['day_id'] : ($days[0]['id'] ?? 0);
        $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end'] ?? date('Y-m-d');

        $dayReport = $dayId ? ReportService::getDayReport($dayId) : null;
        $periodReport = ReportService::getPeriodReport($startDate, $endDate);
        $ranking = ReportService::getSellerRanking($startDate, $endDate);

        View::render('reports/index', [
            'title' => 'Relatórios — Show de Prêmios',
            'tab' => $tab,
            'days' => $days,
            'selectedDayId' => $dayId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dayReport' => $dayReport,
            'periodReport' => $periodReport,
            'ranking' => $ranking,
        ]);
    }

    public function managerial(): void
    {
        $dayId = (int)($_GET['day_id'] ?? 0);
        $startDate = $_GET['start'] ?? null;
        $endDate = $_GET['end'] ?? null;

        if ($dayId) {
            $report = ReportService::getDayReport($dayId);
            $title = "Relatório Gerencial — " . View::date($report['day']['operation_date']);
        } else {
            $startDate = $startDate ?: '2026-09-06';
            $endDate = $endDate ?: date('Y-m-d');
            $report = ReportService::getPeriodReport($startDate, $endDate);
            $title = "Relatório Gerencial — " . View::date($startDate) . " a " . View::date($endDate);
        }

        View::render('reports/managerial', [
            'title' => $title,
            'report' => $report,
            'dayId' => $dayId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function sellerStatement(): void
    {
        $sellerId = (int)($_GET['seller_id'] ?? 0);
        $dayId = isset($_GET['day_id']) ? (int)$_GET['day_id'] : null;

        if (!$sellerId) {
            Response::redirect('/vendedores', null, 'Selecione um vendedor para emitir o extrato.');
        }

        try {
            $data = ReportService::getSellerStatement($sellerId, $dayId);
            View::render('reports/seller_statement', [
                'title' => 'Extrato do Vendedor — ' . View::e($data['seller']['name']),
                'statement' => $data,
                'selectedDayId' => $dayId,
            ]);
        } catch (\Throwable $e) {
            Response::redirect('/vendedores', null, $e->getMessage());
        }
    }

    public function cashStatement(): void
    {
        $dayId = (int)($_GET['day_id'] ?? 0);

        if (!$dayId) {
            Response::redirect('/caixa', null, 'Selecione um dia para emitir o termo de caixa.');
        }

        try {
            $data = ReportService::getCashStatement($dayId);
            View::render('reports/cash_statement', [
                'title' => 'Termo de Fechamento de Caixa — ' . View::date($data['day']['operation_date']),
                'statement' => $data,
            ]);
        } catch (\Throwable $e) {
            Response::redirect('/caixa', null, $e->getMessage());
        }
    }

    public function exportCsv(): void
    {
        $type = $_GET['type'] ?? 'vendas';
        $pdo = Database::getConnection();

        if ($type === 'vendas') {
            $stmt = $pdo->query("
                SELECT d.operation_date, r.round_number, se.name as seller_name, s.quantity, s.amount, s.created_at
                FROM sales s
                JOIN operation_days d ON d.id = s.operation_day_id
                JOIN rounds r ON r.id = s.round_id
                JOIN sellers se ON se.id = s.seller_id
                WHERE s.cancelled_at IS NULL
                ORDER BY d.operation_date DESC, r.round_number ASC
            ");
            $rows = $stmt->fetchAll();

            $data = array_map(function($r) {
                return [
                    View::date($r['operation_date']),
                    $r['round_number'],
                    $r['seller_name'],
                    $r['quantity'],
                    number_format((float)$r['amount'], 2, ',', '.'),
                    $r['created_at'],
                ];
            }, $rows);

            ReportService::exportCsv($data, ['Data', 'Rodada', 'Vendedor(a)', 'Quantidade Vendida', 'Valor (R$)', 'Registrado Em'], 'vendas_show_de_premios.csv');
        } elseif ($type === 'rodadas') {
            $stmt = $pdo->query("
                SELECT d.operation_date, r.round_number, r.prize_1, r.prize_2, r.status,
                       COALESCE(SUM(s.quantity), 0) as total_qty,
                       COALESCE(SUM(s.amount), 0.00) as total_sales
                FROM rounds r
                JOIN operation_days d ON d.id = r.operation_day_id
                LEFT JOIN sales s ON s.round_id = r.id AND s.cancelled_at IS NULL
                GROUP BY r.id
                ORDER BY d.operation_date DESC, r.round_number ASC
            ");
            $rows = $stmt->fetchAll();

            $data = array_map(function($r) {
                $sales = (float)$r['total_sales'];
                $prizes = (float)$r['prize_1'] + (float)$r['prize_2'];
                $profit = $sales - $prizes;
                $margin = $sales > 0 ? ($profit / $sales) * 100 : 0.00;

                return [
                    View::date($r['operation_date']),
                    $r['round_number'],
                    $r['total_qty'],
                    number_format($sales, 2, ',', '.'),
                    number_format((float)$r['prize_1'], 2, ',', '.'),
                    number_format((float)$r['prize_2'], 2, ',', '.'),
                    number_format($profit, 2, ',', '.'),
                    number_format($margin, 2, ',', '.') . '%',
                    $r['status'],
                ];
            }, $rows);

            ReportService::exportCsv($data, ['Data', 'Rodada', 'Qtd Vendida', 'Total Vendas (R$)', '1º Prêmio (R$)', '2º Prêmio (R$)', 'Lucro (R$)', 'Margem (%)', 'Status'], 'rodadas_show_de_premios.csv');
        }
    }
}
