<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class CashService
{
    public static function getTolerance(): float
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'cash_tolerance'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : 0.01;
    }

    public static function calculateDayCash(int $dayId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT initial_cash FROM operation_days WHERE id = ?");
        $stmt->execute([$dayId]);
        $initialCash = (float)($stmt->fetchColumn() ?: 0.00);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0.00)
            FROM sales
            WHERE operation_day_id = ? AND cancelled_at IS NULL
        ");
        $stmt->execute([$dayId]);
        $totalSales = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(COALESCE(prize_1,0) + COALESCE(prize_2,0) + COALESCE(prize_3,0)), 0.00)
            FROM rounds
            WHERE operation_day_id = ? AND status != 'CANCELLED'
        ");
        $stmt->execute([$dayId]);
        $totalPrizes = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT type, COALESCE(SUM(amount), 0.00) as total
            FROM cash_movements
            WHERE operation_day_id = ?
            GROUP BY type
        ");
        $stmt->execute([$dayId]);
        $movements = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $withdrawals   = (float)($movements['WITHDRAWAL'] ?? 0.00);
        $otherInflows  = (float)($movements['INFLOW'] ?? 0.00);
        $otherOutflows = (float)($movements['OUTFLOW'] ?? 0.00);

        $expectedCash = $initialCash + $totalSales + $otherInflows - $totalPrizes - $withdrawals - $otherOutflows;

        $stmtMvMethod = $pdo->prepare("
            SELECT COALESCE(payment_method, 'CASH') as p_method, type, COALESCE(SUM(amount), 0.00) as total
            FROM cash_movements
            WHERE operation_day_id = ?
            GROUP BY payment_method, type
        ");
        $stmtMvMethod->execute([$dayId]);
        $mvRows = $stmtMvMethod->fetchAll(PDO::FETCH_ASSOC);

        $methodsBreakdown = [
            'CASH'   => self::emptyMethod('Dinheiro em Espécie', '💵'),
            'PIX'    => self::emptyMethod('PIX', '⚡'),
            'DEBIT'  => self::emptyMethod('Cartão de Débito', '💳'),
            'CREDIT' => self::emptyMethod('Cartão de Crédito', '💳'),
        ];
        $methodsBreakdown['CASH']['inflows'] = $initialCash;

        foreach ($mvRows as $row) {
            $method = strtoupper((string)($row['p_method'] ?: 'CASH'));
            $info = $methodsBreakdown[$method] ?? self::emptyMethod($method, '💰');
            if ((string)$row['type'] === 'INFLOW') {
                $info['inflows'] += (float)$row['total'];
            } else {
                $info['outflows'] += (float)$row['total'];
            }
            $methodsBreakdown[$method] = $info;
        }

        try {
            $stmtSalesMethod = $pdo->prepare("
                SELECT COALESCE(payment_method, 'CASH') as p_method, COALESCE(SUM(amount), 0.00) as total
                FROM sales
                WHERE operation_day_id = ? AND cancelled_at IS NULL
                GROUP BY payment_method
            ");
            $stmtSalesMethod->execute([$dayId]);
            foreach ($stmtSalesMethod->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $method = strtoupper((string)($row['p_method'] ?: 'CASH'));
                $info = $methodsBreakdown[$method] ?? self::emptyMethod($method, '💰');
                $info['sales'] += (float)$row['total'];
                $methodsBreakdown[$method] = $info;
            }
        } catch (\Throwable $e) {
            error_log('[CashService sales breakdown] ' . $e->getMessage());
        }

        $methodsBreakdown['CASH']['outflows'] += $totalPrizes;
        foreach ($methodsBreakdown as $key => $info) {
            $info['total'] = round($info['inflows'] + $info['sales'] - $info['outflows'], 2);
            $methodsBreakdown[$key] = $info;
        }

        $stmt = $pdo->prepare("SELECT * FROM cash_closings WHERE operation_day_id = ?");
        $stmt->execute([$dayId]);
        $closing = $stmt->fetch(PDO::FETCH_ASSOC);
        $closingData = is_array($closing) ? $closing : [];

        $countedCash = array_key_exists('counted_cash', $closingData) && $closingData['counted_cash'] !== null
            ? (float)$closingData['counted_cash']
            : null;
        $difference = $countedCash !== null ? round($countedCash - $expectedCash, 2) : null;
        $tolerance = self::getTolerance();

        $status = 'PENDING';
        if ($countedCash !== null) {
            $status = abs((float)$difference) <= $tolerance ? 'OK' : 'DIVERGENCE';
        }

        return [
            'initial_cash'   => round($initialCash, 2),
            'total_sales'    => round($totalSales, 2),
            'total_prizes'   => round($totalPrizes, 2),
            'withdrawals'    => round($withdrawals, 2),
            'other_inflows'  => round($otherInflows, 2),
            'other_outflows' => round($otherOutflows, 2),
            'expected_cash'  => round($expectedCash, 2),
            'counted_cash'   => $countedCash !== null ? round($countedCash, 2) : null,
            'counted_money'  => isset($closingData['counted_money']) ? (float)$closingData['counted_money'] : null,
            'counted_pix'    => isset($closingData['counted_pix']) ? (float)$closingData['counted_pix'] : null,
            'counted_debit'  => isset($closingData['counted_debit']) ? (float)$closingData['counted_debit'] : null,
            'counted_credit' => isset($closingData['counted_credit']) ? (float)$closingData['counted_credit'] : null,
            'methods'        => $methodsBreakdown,
            'difference'     => $difference,
            'tolerance'      => $tolerance,
            'status'         => $status,
            'justification'  => $closingData['justification'] ?? null,
            'closed_at'      => $closingData['closed_at'] ?? null,
        ];
    }

    private static function emptyMethod(string $name, string $icon): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'inflows' => 0.0,
            'outflows' => 0.0,
            'sales' => 0.0,
            'total' => 0.0,
        ];
    }
}
