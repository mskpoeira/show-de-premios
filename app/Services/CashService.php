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

        // 1. Initial cash
        $stmt = $pdo->prepare("SELECT initial_cash FROM operation_days WHERE id = ?");
        $stmt->execute([$dayId]);
        $initialCash = (float)($stmt->fetchColumn() ?: 0.00);

        // 2. Total sales
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0.00) 
            FROM sales 
            WHERE operation_day_id = ? AND cancelled_at IS NULL
        ");
        $stmt->execute([$dayId]);
        $totalSales = (float)$stmt->fetchColumn();

        // 3. Prizes paid
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(prize_1 + prize_2), 0.00) 
            FROM rounds 
            WHERE operation_day_id = ? AND status != 'CANCELLED'
        ");
        $stmt->execute([$dayId]);
        $totalPrizes = (float)$stmt->fetchColumn();

        // 4. Cash movements
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

        // Expected cash
        $expectedCash = $initialCash + $totalSales + $otherInflows - $totalPrizes - $withdrawals - $otherOutflows;

        // Breakdown por método de pagamento (CASH, PIX, DEBIT, CREDIT)
        $stmtMvMethod = $pdo->prepare("
            SELECT COALESCE(payment_method, 'CASH') as p_method, type, COALESCE(SUM(amount), 0.00) as total 
            FROM cash_movements 
            WHERE operation_day_id = ? 
            GROUP BY payment_method, type
        ");
        $stmtMvMethod->execute([$dayId]);
        $mvRows = $stmtMvMethod->fetchAll(PDO::FETCH_ASSOC);

        $methodsBreakdown = [
            'CASH'   => ['name' => 'Dinheiro em Espécie', 'icon' => '💵', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00],
            'PIX'    => ['name' => 'PIX', 'icon' => '⚡', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00],
            'DEBIT'  => ['name' => 'Cartão de Débito', 'icon' => '💳', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00],
            'CREDIT' => ['name' => 'Cartão de Crédito', 'icon' => '💳', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00],
        ];

        // Adiciona fundo de troco inicial ao dinheiro
        $methodsBreakdown['CASH']['inflows'] += $initialCash;

        foreach ($mvRows as $r) {
            $m = strtoupper($r['p_method'] ?: 'CASH');
            if (!isset($methodsBreakdown[$m])) {
                $methodsBreakdown[$m] = ['name' => $m, 'icon' => '💰', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00];
            }
            if ($r['type'] === 'INFLOW') {
                $methodsBreakdown[$m]['inflows'] += (float)$r['total'];
            } else {
                $methodsBreakdown[$m]['outflows'] += (float)$r['total'];
            }
        }

        // Vendas por método
        try {
            $stmtSalesMethod = $pdo->prepare("
                SELECT COALESCE(payment_method, 'CASH') as p_method, COALESCE(SUM(amount), 0.00) as total 
                FROM sales 
                WHERE operation_day_id = ? AND cancelled_at IS NULL 
                GROUP BY payment_method
            ");
            $stmtSalesMethod->execute([$dayId]);
            foreach ($stmtSalesMethod->fetchAll(PDO::FETCH_ASSOC) as $sr) {
                $sm = strtoupper($sr['p_method'] ?: 'CASH');
                if (!isset($methodsBreakdown[$sm])) {
                    $methodsBreakdown[$sm] = ['name' => $sm, 'icon' => '💰', 'inflows' => 0.00, 'outflows' => 0.00, 'sales' => 0.00, 'total' => 0.00];
                }
                $methodsBreakdown[$sm]['sales'] += (float)$sr['total'];
            }
        } catch (\Throwable $e) {}

        // Prêmios são pagos preferencialmente em dinheiro físico
        $methodsBreakdown['CASH']['outflows'] += $totalPrizes;

        foreach ($methodsBreakdown as $k => &$info) {
            $info['total'] = round($info['inflows'] + $info['sales'] - $info['outflows'], 2);
        }
        unset($info);

        // Current closing if exists
        $stmt = $pdo->prepare("SELECT * FROM cash_closings WHERE operation_day_id = ?");
        $stmt->execute([$dayId]);
        $closing = $stmt->fetch();

        $countedCash = $closing ? (float)$closing['counted_cash'] : null;
        $difference = $countedCash !== null ? round($countedCash - $expectedCash, 2) : null;
        $tolerance = self::getTolerance();

        $status = 'PENDING';
        if ($countedCash !== null) {
            $status = abs($difference) <= $tolerance ? 'OK' : 'DIVERGENCE';
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
            'counted_money'  => isset($closing['counted_money']) && $closing['counted_money'] !== null ? (float)$closing['counted_money'] : null,
            'counted_pix'    => isset($closing['counted_pix']) && $closing['counted_pix'] !== null ? (float)$closing['counted_pix'] : null,
            'counted_debit'  => isset($closing['counted_debit']) && $closing['counted_debit'] !== null ? (float)$closing['counted_debit'] : null,
            'counted_credit' => isset($closing['counted_credit']) && $closing['counted_credit'] !== null ? (float)$closing['counted_credit'] : null,
            'methods'        => $methodsBreakdown,
            'difference'     => $difference,
            'tolerance'      => $tolerance,
            'status'         => $status,
            'justification'  => $closing['justification'] ?? null,
            'closed_at'      => $closing['closed_at'] ?? null,
        ];
    }
}
