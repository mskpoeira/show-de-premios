<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ReportService
{
    public static function getDayReport(int $dayId): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM operation_days WHERE id = ?");
        $stmt->execute([$dayId]);
        $day = $stmt->fetch();

        if (!$day) {
            return null;
        }

        // Rounds
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   COALESCE(SUM(s.quantity), 0) as total_qty,
                   COALESCE(SUM(s.amount), 0.00) as total_sales
            FROM rounds r
            LEFT JOIN sales s ON s.round_id = r.id AND s.cancelled_at IS NULL
            WHERE r.operation_day_id = ?
            GROUP BY r.id
            ORDER BY r.round_number ASC
        ");
        $stmt->execute([$dayId]);
        $rounds = $stmt->fetchAll();

        $roundsData = [];
        $daySalesTotal = 0.00;
        $dayQtyTotal = 0;
        $dayPrizesTotal = 0.00;

        foreach ($rounds as $r) {
            $sales = (float)$r['total_sales'];
            $qty = (int)$r['total_qty'];
            $prizes = (float)$r['prize_1'] + (float)$r['prize_2'];
            $profit = $sales - $prizes;
            $margin = $sales > 0 ? ($profit / $sales) * 100 : 0.00;

            $daySalesTotal += $sales;
            $dayQtyTotal += $qty;
            $dayPrizesTotal += $prizes;

            $roundsData[] = array_merge($r, [
                'total_sales' => $sales,
                'total_qty' => $qty,
                'total_prizes' => $prizes,
                'profit' => $profit,
                'margin' => $margin,
            ]);
        }

        // Sales by seller
        $stmt = $pdo->prepare("
            SELECT se.id as seller_id, se.name as seller_name, se.nickname,
                   COALESCE(SUM(s.quantity), 0) as total_qty,
                   COALESCE(SUM(s.amount), 0.00) as total_amount
            FROM sales s
            JOIN sellers se ON se.id = s.seller_id
            WHERE s.operation_day_id = ? AND s.cancelled_at IS NULL
            GROUP BY se.id
            ORDER BY total_amount DESC, total_qty DESC
        ");
        $stmt->execute([$dayId]);
        $sellersSales = $stmt->fetchAll();

        // Cash
        $cashData = CashService::calculateDayCash($dayId);

        $dayProfit = $daySalesTotal - $dayPrizesTotal;
        $dayMargin = $daySalesTotal > 0 ? ($dayProfit / $daySalesTotal) * 100 : 0.00;

        return [
            'day' => $day,
            'summary' => [
                'total_sales' => $daySalesTotal,
                'total_qty' => $dayQtyTotal,
                'total_prizes' => $dayPrizesTotal,
                'profit' => $dayProfit,
                'margin' => $dayMargin,
                'rounds_count' => count($roundsData),
            ],
            'rounds' => $roundsData,
            'sellers' => $sellersSales,
            'cash' => $cashData,
        ];
    }

    public static function getPeriodReport(string $startDate, string $endDate): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT d.id, d.operation_date, d.status,
                   COALESCE(SUM(s.quantity), 0) as total_qty,
                   COALESCE(SUM(s.amount), 0.00) as total_sales,
                   (SELECT COALESCE(SUM(prize_1 + prize_2), 0.00) FROM rounds WHERE operation_day_id = d.id AND status != 'CANCELLED') as total_prizes,
                   (SELECT COUNT(*) FROM rounds WHERE operation_day_id = d.id AND status != 'CANCELLED') as rounds_count
            FROM operation_days d
            LEFT JOIN sales s ON s.operation_day_id = d.id AND s.cancelled_at IS NULL
            WHERE d.operation_date BETWEEN ? AND ?
            GROUP BY d.id
            ORDER BY d.operation_date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $days = $stmt->fetchAll();

        $totalSales = 0.00;
        $totalQty = 0;
        $totalPrizes = 0.00;
        $daysData = [];

        foreach ($days as $d) {
            $sales = (float)$d['total_sales'];
            $qty = (int)$d['total_qty'];
            $prizes = (float)$d['total_prizes'];
            $profit = $sales - $prizes;
            $margin = $sales > 0 ? ($profit / $sales) * 100 : 0.00;

            $totalSales += $sales;
            $totalQty += $qty;
            $totalPrizes += $prizes;

            $daysData[] = array_merge($d, [
                'sales' => $sales,
                'qty' => $qty,
                'prizes' => $prizes,
                'profit' => $profit,
                'margin' => $margin,
            ]);
        }

        $totalProfit = $totalSales - $totalPrizes;
        $totalMargin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0.00;

        // Seller rankings for period
        $ranking = self::getSellerRanking($startDate, $endDate);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_sales' => $totalSales,
                'total_qty' => $totalQty,
                'total_prizes' => $totalPrizes,
                'profit' => $totalProfit,
                'margin' => $totalMargin,
                'days_count' => count($daysData),
            ],
            'days' => $daysData,
            'ranking' => $ranking,
        ];
    }

    public static function getSellerRanking(?string $startDate = null, ?string $endDate = null): array
    {
        $pdo = Database::getConnection();

        $where = "WHERE s.cancelled_at IS NULL";
        $params = [];

        if ($startDate && $endDate) {
            $where .= " AND d.operation_date BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        }

        $sql = "
            SELECT se.id as seller_id, se.name as seller_name, se.nickname,
                   COALESCE(SUM(s.quantity), 0) as total_qty,
                   COALESCE(SUM(s.amount), 0.00) as total_amount,
                   COUNT(DISTINCT s.operation_day_id) as days_count
            FROM sellers se
            JOIN sales s ON s.seller_id = se.id
            JOIN operation_days d ON d.id = s.operation_day_id
            {$where}
            GROUP BY se.id
            ORDER BY total_amount DESC, total_qty DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $grandTotal = array_sum(array_column($rows, 'total_amount'));

        $ranking = [];
        $pos = 1;
        foreach ($rows as $row) {
            $amount = (float)$row['total_amount'];
            $daysCount = (int)$row['days_count'];
            $share = $grandTotal > 0 ? ($amount / $grandTotal) * 100 : 0.00;
            $avgDaily = $daysCount > 0 ? $amount / $daysCount : 0.00;

            $ranking[] = [
                'position' => $pos++,
                'seller_id' => $row['seller_id'],
                'seller_name' => $row['seller_name'],
                'nickname' => $row['nickname'],
                'total_qty' => (int)$row['total_qty'],
                'total_amount' => $amount,
                'share' => $share,
                'days_count' => $daysCount,
                'avg_daily' => $avgDaily,
            ];
        }

        return $ranking;
    }

    public static function getSellerStatement(int $sellerId, ?int $dayId = null): array
    {
        $pdo = Database::getConnection();

        $stmtSeller = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
        $sellerIdInt = (int)$sellerId;
        $stmtSeller->execute([$sellerIdInt]);
        $seller = $stmtSeller->fetch();

        if (!$seller) {
            throw new \Exception("Vendedor não encontrado.");
        }

        $sql = "
            SELECT s.*, r.round_number, d.operation_date, d.status as day_status
            FROM sales s
            JOIN rounds r ON r.id = s.round_id
            JOIN operation_days d ON d.id = s.operation_day_id
            WHERE s.seller_id = ? AND s.cancelled_at IS NULL
        ";
        $params = [$sellerIdInt];

        if ($dayId) {
            $sql .= " AND s.operation_day_id = ? ";
            $params[] = $dayId;
        }

        $sql .= " ORDER BY d.operation_date DESC, r.round_number ASC ";

        $stmtSales = $pdo->prepare($sql);
        $stmtSales->execute($params);
        $sales = $stmtSales->fetchAll();

        $totalQty = 0;
        $totalAmount = 0.00;
        $totalPackages = 0;
        $totalSingles = 0;

        foreach ($sales as $sale) {
            $qty = (int)$sale['quantity'];
            $totalQty += $qty;
            $totalAmount += (float)$sale['amount'];
            $totalPackages += intdiv($qty, 3);
            $totalSingles += ($qty % 3);
        }

        return [
            'seller' => $seller,
            'sales' => $sales,
            'summary' => [
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'total_packages' => $totalPackages,
                'total_singles' => $totalSingles,
                'rounds_count' => count($sales),
            ]
        ];
    }

    public static function getCashStatement(int $dayId): array
    {
        $dayReport = self::getDayReport($dayId);
        $pdo = Database::getConnection();

        // Fetch user who opened and who closed
        $stmtUsers = $pdo->prepare("
            SELECT d.*, u.name as opener_name, cu.name as closer_name, cc.counted_cash, cc.difference, cc.status as closing_status, cc.justification, cc.closed_at as closing_time
            FROM operation_days d
            LEFT JOIN users u ON u.id = d.responsible_user_id
            LEFT JOIN cash_closings cc ON cc.operation_day_id = d.id
            LEFT JOIN users cu ON cu.id = cc.responsible_user_id
            WHERE d.id = ?
        ");
        $stmtUsers->execute([$dayId]);
        $dayFull = $stmtUsers->fetch();

        return [
            'day' => $dayFull ?: $dayReport['day'],
            'summary' => $dayReport['summary'],
            'rounds' => $dayReport['rounds'],
            'cash_movements' => $dayReport['cash_movements'],
        ];
    }

    public static function exportCsv(array $data, array $headers, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel pt-BR
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, $headers, ';');

        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }
}
