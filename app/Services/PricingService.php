<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class PricingService
{
    public static function getRuleForDate(?string $date = null): array
    {
        $pdo = Database::getConnection();
        $date = $date ?: date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT * FROM pricing_rules 
            WHERE active = 1 
              AND effective_from <= ? 
              AND (effective_to IS NULL OR effective_to >= ?)
            ORDER BY effective_from DESC 
            LIMIT 1
        ");
        $stmt->execute([$date, $date]);
        $rule = $stmt->fetch();

        if (!$rule) {
            // Fallback default
            return [
                'id' => null,
                'single_quantity' => 1,
                'single_price' => 2.00,
                'bundle_quantity' => 3,
                'bundle_price' => 5.00,
            ];
        }

        return $rule;
    }

    public static function getActiveRule(?string $date = null): array
    {
        return self::getRuleForDate($date);
    }

    public static function calculate(int $quantity, ?array $rule = null): array
    {
        if ($quantity < 0) {
            $quantity = 0;
        }

        $rule = $rule ?: self::getRuleForDate();

        $bundleQty = (int)($rule['bundle_quantity'] ?? 3);
        $bundlePrice = (float)($rule['bundle_price'] ?? 5.00);
        $singlePrice = (float)($rule['single_price'] ?? 2.00);

        if ($bundleQty <= 0) {
            $bundleQty = 3;
        }

        $packages = intdiv($quantity, $bundleQty);
        $singles = $quantity % $bundleQty;

        $amount = ($packages * $bundlePrice) + ($singles * $singlePrice);

        return [
            'quantity' => $quantity,
            'amount' => round($amount, 2),
            'packages' => $packages,
            'singles' => $singles,
            'rule_id' => $rule['id'] ?? null,
            'snapshot' => [
                'single_price' => $singlePrice,
                'bundle_quantity' => $bundleQty,
                'bundle_price' => $bundlePrice,
                'formula' => "floor(qtd / {$bundleQty}) * {$bundlePrice} + (qtd % {$bundleQty}) * {$singlePrice}"
            ]
        ];
    }
}
