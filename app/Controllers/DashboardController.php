<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\ReportService;
use PDO;

class DashboardController
{
    public function index()
    {
        $tab = trim($_GET['tab'] ?? 'visao');
        if (!in_array($tab, ['visao', 'auditoria'], true)) {
            $tab = 'visao';
        }

        $pdo = Database::getConnection();

        // 1. Dados Globais e Dias
        $stmtDays = $pdo->query("SELECT id, operation_date, status FROM operation_days ORDER BY operation_date DESC");
        $days = $stmtDays->fetchAll();

        $stmt = $pdo->query("SELECT * FROM operation_days WHERE status = 'OPEN' ORDER BY operation_date DESC LIMIT 1");
        $currentOpenDay = $stmt->fetch();

        // Filtro de dia selecionado para detalhamento
        $selectedDayId = isset($_GET['day_id']) ? (int)$_GET['day_id'] : ($currentOpenDay['id'] ?? $days[0]['id'] ?? 0);
        $dayReport = $selectedDayId ? ReportService::getDayReport($selectedDayId) : null;

        // Filtro de período
        $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $periodRanking = ReportService::getSellerRanking($startDate, $endDate);

        // Dias recentes com métricas
        $stmt = $pdo->query("
            SELECT d.*, 
                   COALESCE(SUM(s.quantity), 0) as total_qty,
                   COALESCE(SUM(s.amount), 0.00) as total_sales,
                   (SELECT COALESCE(SUM(prize_1 + prize_2 + prize_3), 0.00) FROM rounds WHERE operation_day_id = d.id AND status != 'CANCELLED') as total_prizes,
                   (SELECT COUNT(*) FROM rounds WHERE operation_day_id = d.id AND status != 'CANCELLED') as rounds_count
            FROM operation_days d
            LEFT JOIN sales s ON s.operation_day_id = d.id AND s.cancelled_at IS NULL
            GROUP BY d.id
            ORDER BY d.operation_date DESC
            LIMIT 15
        ");
        $recentDays = $stmt->fetchAll();

        // KPIs Totais
        $stmtKpi = $pdo->query("
            SELECT 
                COALESCE(SUM(s.quantity), 0) as total_qty,
                COALESCE(SUM(s.amount), 0.00) as total_sales
            FROM sales s
            WHERE s.cancelled_at IS NULL
        ");
        $kpiSales = $stmtKpi->fetch();

        $stmtPrizes = $pdo->query("SELECT COALESCE(SUM(prize_1 + prize_2 + prize_3), 0.00) as total_prizes FROM rounds WHERE status != 'CANCELLED'");
        $totalPrizes = (float)$stmtPrizes->fetchColumn();

        $totalSales = (float)($kpiSales['total_sales'] ?? 0);
        $totalQty = (int)($kpiSales['total_qty'] ?? 0);
        $totalProfit = $totalSales - $totalPrizes;
        $totalMargin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0.00;

        $stmtSellers = $pdo->query("SELECT COUNT(*) FROM sellers WHERE active = 1");
        $activeSellersCount = (int)$stmtSellers->fetchColumn();

        // Gráficos
        $chartDays = array_reverse(array_slice($recentDays, 0, 10));
        $chartLabels = [];
        $chartSales = [];
        $chartPrizes = [];
        $chartProfit = [];

        foreach ($chartDays as $cd) {
            $chartLabels[] = View::date($cd['operation_date']);
            $sales = (float)$cd['total_sales'];
            $prizes = (float)$cd['total_prizes'];
            $chartSales[] = $sales;
            $chartPrizes[] = $prizes;
            $chartProfit[] = round($sales - $prizes, 2);
        }

        // 2. Dados de Auditoria
        $auditLogs = [];
        $auditPage = 1;
        $auditTotalPages = 1;
        $auditTotalLogs = 0;

        if (Auth::isAdmin() || Auth::isMasterAdmin()) {
            $limit = 50;
            $auditPage = max(1, (int)($_GET['page'] ?? 1));
            $offset = ($auditPage - 1) * $limit;

            $stmtCount = $pdo->query("SELECT COUNT(*) FROM audit_logs");
            $auditTotalLogs = (int)$stmtCount->fetchColumn();
            $auditTotalPages = ceil($auditTotalLogs / $limit);

            $stmtAudit = $pdo->prepare("
                SELECT a.*, u.name as user_name, u.login as user_login
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmtAudit->bindValue(1, $limit, PDO::PARAM_INT);
            $stmtAudit->bindValue(2, $offset, PDO::PARAM_INT);
            $stmtAudit->execute();
            $auditLogs = $stmtAudit->fetchAll();
        }

        View::render('dashboard/index', [
            'title' => 'Painel Gerencial — Show de Prêmios',
            'tab' => $tab,
            'currentOpenDay' => $currentOpenDay,
            'days' => $days,
            'selectedDayId' => $selectedDayId,
            'dayReport' => $dayReport,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'recentDays' => $recentDays,
            'totalSales' => $totalSales,
            'totalQty' => $totalQty,
            'totalPrizes' => $totalPrizes,
            'totalProfit' => $totalProfit,
            'totalMargin' => $totalMargin,
            'activeSellersCount' => $activeSellersCount,
            'periodRanking' => $periodRanking,
            'chartLabelsJson' => json_encode($chartLabels),
            'chartSalesJson' => json_encode($chartSales),
            'chartPrizesJson' => json_encode($chartPrizes),
            'chartProfitJson' => json_encode($chartProfit),
            'auditLogs' => $auditLogs,
            'auditPage' => $auditPage,
            'auditTotalPages' => $auditTotalPages,
            'auditTotalLogs' => $auditTotalLogs,
        ]);
    }
}
