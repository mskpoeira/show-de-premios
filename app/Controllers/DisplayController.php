<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\PixService;
use App\Services\PricingService;
use PDO;

class DisplayController
{
    public function index(): void
    {
        View::render('display/telao', self::publicData(), false);
    }

    public function status(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['status' => 'success', 'data' => self::publicData()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    private static function publicData(): array
    {
        $pdo = Database::getConnection();

        $eventStmt = $pdo->query("SELECT id,name,event_date,event_time,location,status FROM events WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1");
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $day = $pdo->query("SELECT * FROM operation_days ORDER BY CASE WHEN status='OPEN' THEN 0 ELSE 1 END,operation_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
        $round = null;
        if ($day) {
            $stmt = $pdo->prepare("SELECT * FROM rounds WHERE operation_day_id=? ORDER BY CASE WHEN status IN ('IN_PROGRESS','CHECKING','PAUSED','OPEN') THEN 0 ELSE 1 END,round_number DESC LIMIT 1");
            $stmt->execute([(int)$day['id']]);
            $round = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $draw = null;
        $stones = [];
        $prize = null;
        $publicWinners = [];

        if ($event) {
            if ($round) {
                $stmt = $pdo->prepare("SELECT * FROM draws WHERE event_id=? AND round_id=? ORDER BY id DESC LIMIT 1");
                $stmt->execute([(int)$event['id'], (int)$round['id']]);
                $draw = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!$draw) {
                $stmt = $pdo->prepare("SELECT * FROM draws WHERE event_id=? ORDER BY CASE WHEN status IN ('IN_PROGRESS','CHECKING','OPEN') THEN 0 ELSE 1 END,id DESC LIMIT 1");
                $stmt->execute([(int)$event['id']]);
                $draw = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($draw) {
                $stmt = $pdo->prepare('SELECT number_value,letter,call_order,called_at FROM draw_stones WHERE draw_id=? ORDER BY call_order');
                $stmt->execute([(int)$draw['id']]);
                $stones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($draw['prize_id'])) {
                    $stmt = $pdo->prepare('SELECT id,title,description,value,order_num FROM prizes WHERE id=? LIMIT 1');
                    $stmt->execute([(int)$draw['prize_id']]);
                    $prize = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                $winnerSql = "
                    SELECT wc.id, wc.winner_type, wc.status, wc.prize_id,
                           t.ticket_number, p.title AS prize_title, p.value AS prize_value
                    FROM winner_claims wc
                    LEFT JOIN tickets t ON t.id = wc.ticket_id
                    LEFT JOIN prizes p ON p.id = wc.prize_id
                    WHERE wc.draw_id = ?
                      AND wc.status IN ('PENDING', 'HOMOLOGATED')
                ";
                $winnerParams = [(int)$draw['id']];
                if (!empty($draw['prize_id'])) {
                    $winnerSql .= ' AND wc.prize_id = ?';
                    $winnerParams[] = (int)$draw['prize_id'];
                }
                $winnerSql .= " ORDER BY CASE WHEN wc.status='HOMOLOGATED' THEN 0 ELSE 1 END, wc.id ASC";

                $stmt = $pdo->prepare($winnerSql);
                $stmt->execute($winnerParams);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $winner) {
                    $ticketCode = trim((string)($winner['ticket_number'] ?? ''));
                    if ($ticketCode === '') {
                        $ticketCode = 'FÍSICA-' . str_pad((string)(int)$winner['id'], 4, '0', STR_PAD_LEFT);
                    }

                    $publicWinners[] = [
                        'ticket_code' => $ticketCode,
                        'prize_title' => (string)($winner['prize_title'] ?? $prize['title'] ?? 'Prêmio'),
                        'prize_value_formatted' => View::money((float)($winner['prize_value'] ?? $prize['value'] ?? 0)),
                        'status' => (string)$winner['status'],
                        'status_label' => $winner['status'] === 'HOMOLOGATED' ? 'HOMOLOGADA' : 'EM CONFERÊNCIA',
                    ];
                }
            }
        }

        $pricing = PricingService::getRuleForDate($day['operation_date'] ?? $event['event_date'] ?? null);
        $pix = PixService::getConfig();
        $pixEnabled = (bool)($pix['show_on_telao'] ?? false) && trim((string)($pix['key'] ?? '')) !== '';

        return [
            'system_title' => View::systemTitle(),
            'event' => $event ? [
                'name' => $event['name'],
                'date' => $event['event_date'],
                'time' => $event['event_time'],
                'location' => $event['location'],
            ] : null,
            'day_date' => $day ? View::date((string)$day['operation_date']) : null,
            'round' => $round ? [
                'id' => (int)$round['id'],
                'round_number' => (int)$round['round_number'],
                'round_name' => $round['round_name'] ?: ('Rodada ' . (int)$round['round_number']),
                'card_color' => $round['card_color'] ?: null,
            ] : null,
            'round_status' => $draw['status'] ?? $round['status'] ?? 'NO_ROUND',
            'prize' => $prize ? [
                'id' => (int)$prize['id'],
                'order_num' => (int)$prize['order_num'],
                'title' => $prize['title'],
                'description' => $prize['description'],
                'value' => (float)$prize['value'],
                'value_formatted' => View::money((float)$prize['value']),
            ] : null,
            'winners' => $publicWinners,
            'has_winner' => $publicWinners !== [],
            'called_numbers' => array_map(static fn(array $stone): int => (int)$stone['number_value'], $stones),
            'called_stones' => array_map(static fn(array $stone): array => [
                'number' => (int)$stone['number_value'],
                'letter' => $stone['letter'],
                'order' => (int)$stone['call_order'],
            ], $stones),
            'last_called_number' => $draw && $draw['last_called_number'] !== null ? (int)$draw['last_called_number'] : null,
            'last_letter' => $draw['last_called_letter'] ?? null,
            'total_called' => count($stones),
            'single_price' => (float)$pricing['single_price'],
            'bundle_qty' => (int)$pricing['bundle_quantity'],
            'bundle_price' => (float)$pricing['bundle_price'],
            'pix' => [
                'show' => $pixEnabled,
                'banner' => $pix['banner_title'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR',
                'key' => $pixEnabled ? (string)$pix['key'] : '',
                'receiver' => $pixEnabled ? (string)($pix['receiver'] ?? '') : '',
                'payload' => $pixEnabled ? (string)($pix['payload'] ?? '') : '',
            ],
            'server_time' => date('H:i:s'),
            // Privacidade: não são enviados nome, CPF, telefone, buyer_id, vendedor ou dados pessoais do claim.
        ];
    }
}
