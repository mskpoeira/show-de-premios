<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\PixService;
use App\Services\PricingService;

class DisplayController
{
    public function index(): void
    {
        $data = self::getCurrentDisplayData();
        View::render('display/telao', $data, false);
    }

    public function status(): void
    {
        $data = self::getCurrentDisplayData();
        $round = $data['round'];
        $prizesCount = $round ? (int)($round['prizes_count'] ?? 2) : 2;
        if ($prizesCount < 1) $prizesCount = 1;
        
        $p1 = (float)($round['prize_1'] ?? 0);
        $p2 = (float)($round['prize_2'] ?? 0);
        $p3 = (float)($round['prize_3'] ?? 0);
        $totalPrizes = $p1 + $p2 + $p3;

        $roundNumber = $round ? (int)$round['round_number'] : 1;
        $roundName = $round && !empty($round['round_name']) ? trim($round['round_name']) : ('RODADA ' . $roundNumber);
        $cardColor = !empty($round['card_color']) ? trim((string)$round['card_color']) : 'Amarela';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'day_date' => $data['day'] ? View::date($data['day']['operation_date']) : null,
                'round_number' => $roundNumber,
                'round_name' => $roundName,
                'card_color' => $cardColor,
                'prizes_count' => $prizesCount,
                'round_status' => $round ? $round['status'] : 'NO_ROUND',
                'prize_1' => View::money($p1),
                'prize_1_title' => $round ? ($round['prize_1_title'] ?? '') : '',
                'prize_2' => View::money($p2),
                'prize_2_title' => $round ? ($round['prize_2_title'] ?? '') : '',
                'prize_3' => View::money($p3),
                'prize_3_title' => $round ? ($round['prize_3_title'] ?? '') : '',
                'total_prizes' => View::money($totalPrizes),
                'winner_1_name' => $round ? ($round['winner_1_name'] ?? $round['winner_name'] ?? '') : '',
                'seller_1_name' => $round ? ($round['seller_1_name'] ?? '') : '',
                'winner_2_name' => $round ? ($round['winner_2_name'] ?? '') : '',
                'seller_2_name' => $round ? ($round['seller_2_name'] ?? '') : '',
                'winner_3_name' => $round ? ($round['winner_3_name'] ?? '') : '',
                'seller_3_name' => $round ? ($round['seller_3_name'] ?? '') : '',
                'winner_name' => $round ? ($round['winner_1_name'] ?? $round['winner_name'] ?? '') : '',
                'server_time' => date('H:i:s'),
                'single_price' => View::money($data['pricingRule']['single_price']),
                'bundle_qty' => (int)$data['pricingRule']['bundle_quantity'],
                'bundle_price' => View::money($data['pricingRule']['bundle_price']),
                'system_title' => $data['systemTitle'],
                'pix_key' => $data['pixKey'],
                'pix_receiver' => $data['pixReceiver'],
                'pix_description' => $data['pixDescription'] ?? '',
                'pix_banner_title' => $data['pixBannerTitle'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR',
                'pix_show_on_telao' => $data['pixShowOnTelao'],
                'pix_payload' => $data['pixPayload'] ?? '',
                'called_numbers' => !empty($round['called_numbers_json']) ? (json_decode($round['called_numbers_json'], true) ?: []) : [],
                'last_called_number' => !empty($round['last_called_number']) ? (int)$round['last_called_number'] : null,
                'last_called_at' => $round['last_called_at'] ?? null,
                'last_letter' => !empty($round['last_called_number']) ? SpeakerController::getLetterForNumber((int)$round['last_called_number']) : '',
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function getCurrentDisplayData(): array
    {
        $pdo = Database::getConnection();

        // 1. Get open day or latest day
        $stmtDay = $pdo->query("SELECT * FROM operation_days ORDER BY CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END, operation_date DESC LIMIT 1");
        $day = $stmtDay->fetch();

        $round = null;
        if ($day) {
            // Find active round or latest round of the day
            $stmtRound = $pdo->prepare("
                SELECT * FROM rounds 
                WHERE operation_day_id = ? 
                ORDER BY CASE WHEN status IN ('IN_PROGRESS', 'CHECKING', 'PAUSED', 'OPEN') THEN 0 ELSE 1 END, round_number DESC 
                LIMIT 1
            ");
            $stmtRound->execute([$day['id']]);
            $round = $stmtRound->fetch();
        }

        if (!$round) {
            $stmtRoundFallback = $pdo->query("SELECT * FROM rounds ORDER BY id DESC LIMIT 1");
            $round = $stmtRoundFallback->fetch() ?: null;
        }

        $pricingRule = PricingService::getRuleForDate($day ? $day['operation_date'] : null);
        $systemTitle = View::systemTitle();

        // PIX settings padronizadas pelo Banco Central do Brasil (BCB)
        $pixConfig = PixService::getConfig();

        return [
            'day' => $day ?: null,
            'round' => $round ?: null,
            'pricingRule' => $pricingRule,
            'systemTitle' => $systemTitle,
            'pixKey' => $pixConfig['key'],
            'pixReceiver' => $pixConfig['receiver'],
            'pixDescription' => $pixConfig['description'],
            'pixBannerTitle' => $pixConfig['banner_title'],
            'pixShowOnTelao' => $pixConfig['show_on_telao'],
            'pixPayload' => $pixConfig['payload'],
        ];
    }
}
