<?php

namespace App\Services;

use App\Core\Database;

class PrizeSuggestionService
{
    public static function roundToMultiple(float $value, float $multiple = 10.0): float
    {
        if ($multiple <= 0) {
            return round($value, 2);
        }
        return round($value / $multiple) * $multiple;
    }

    public static function getSettings(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT key, value FROM settings WHERE key IN (
            'prize_pool_percent', 'prize_1_percent', 'prize_2_percent', 'prize_rounding', 'carry_previous_prize_suggestion'
        )");
        $rows = $stmt->fetchAll();

        $settings = [
            'prize_pool_percent' => 50,
            'prize_1_percent' => 65,
            'prize_2_percent' => 35,
            'prize_rounding' => 10.00,
            'carry_previous_prize_suggestion' => true,
        ];

        foreach ($rows as $row) {
            $k = $row['key'];
            $v = $row['value'];
            if ($k === 'carry_previous_prize_suggestion') {
                $settings[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
            } else {
                $settings[$k] = (float)$v;
            }
        }

        return $settings;
    }

    public static function calculateNextRoundSuggestion(float $roundSalesAmount): array
    {
        $settings = self::getSettings();

        $poolPercent = $settings['prize_pool_percent'] / 100;
        $p1Percent   = $settings['prize_1_percent'] / 100;
        $rounding    = $settings['prize_rounding'];

        $rawPool = $roundSalesAmount * $poolPercent;
        $totalSuggestion = self::roundToMultiple($rawPool, $rounding);

        if ($totalSuggestion <= 0) {
            return [
                'total' => 0.0,
                'prize_1' => 0.0,
                'prize_2' => 0.0,
            ];
        }

        $rawPrize1 = $totalSuggestion * $p1Percent;
        $prize1 = self::roundToMultiple($rawPrize1, $rounding);

        // Prize 2 is remaining so that prize_1 + prize_2 == totalSuggestion
        $prize2 = $totalSuggestion - $prize1;

        // Ensure 1st prize is >= 2nd prize and no negative prizes
        if ($prize2 < 0) {
            $prize1 = $totalSuggestion;
            $prize2 = 0.0;
        } elseif ($prize1 < $prize2) {
            // Swap or balance
            $temp = $prize1;
            $prize1 = $prize2;
            $prize2 = $temp;
        }

        return [
            'total' => round($totalSuggestion, 2),
            'prize_1' => round($prize1, 2),
            'prize_2' => round($prize2, 2),
        ];
    }
}
