<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\PricingService;
use App\Services\PrizeSuggestionService;
use PDO;

class RoundController
{
    public function operate(): void
    {
        $roundId = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT r.*, d.operation_date, d.status as day_status 
            FROM rounds r
            JOIN operation_days d ON d.id = r.operation_day_id
            WHERE r.id = ?
        ");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            Response::redirect('/dia', null, 'Rodada não encontrada.');
        }

        // Active sellers + sellers with sales in this round
        $stmtSellers = $pdo->prepare("
            SELECT s.*, 
                   sa.id as sale_id,
                   COALESCE(sa.quantity, 0) as quantity,
                   COALESCE(sa.amount, 0.00) as amount
            FROM sellers s
            LEFT JOIN sales sa ON sa.seller_id = s.id AND sa.round_id = ? AND sa.cancelled_at IS NULL
            WHERE s.active = 1 OR sa.id IS NOT NULL
            ORDER BY s.name ASC
        ");
        $stmtSellers->execute([$roundId]);
        $sellers = $stmtSellers->fetchAll();

        // Round summary
        $totalSales = array_sum(array_column($sellers, 'amount'));
        $totalQty = array_sum(array_column($sellers, 'quantity'));
        $prizes = (float)$round['prize_1'] + (float)$round['prize_2'];
        $profit = $totalSales - $prizes;
        $margin = $totalSales > 0 ? ($profit / $totalSales) * 100 : 0.00;

        // Suggested next prize
        $suggestion = PrizeSuggestionService::calculateNextRoundSuggestion($totalSales);

        // Get active pricing rule
        $pricingRule = PricingService::getRuleForDate($round['operation_date']);

        // Override pricingRule if round has its own pricing
        if (!empty($round['single_price']) && (float)$round['single_price'] > 0) {
            $pricingRule['single_price'] = (float)$round['single_price'];
        }
        if (!empty($round['bundle_quantity']) && (int)$round['bundle_quantity'] > 0) {
            $pricingRule['bundle_quantity'] = (int)$round['bundle_quantity'];
        }
        if (!empty($round['bundle_price']) && (float)$round['bundle_price'] > 0) {
            $pricingRule['bundle_price'] = (float)$round['bundle_price'];
        }

        // Available card colors
        $cardColors = [];
        try {
            $cardColors = $pdo->query("SELECT * FROM card_colors ORDER BY id ASC")->fetchAll();
        } catch (\Throwable $e) {}
        if (empty($cardColors)) {
            $cardColors = [
                ['name' => 'Amarela', 'bg_color' => '#fef08a', 'text_color' => '#854d0e', 'border_color' => '#eab308'],
                ['name' => 'Azul', 'bg_color' => '#bae6fd', 'text_color' => '#0369a1', 'border_color' => '#38bdf8'],
                ['name' => 'Verde', 'bg_color' => '#bbf7d0', 'text_color' => '#15803d', 'border_color' => '#4ade80'],
                ['name' => 'Vermelha', 'bg_color' => '#fecaca', 'text_color' => '#b91c1c', 'border_color' => '#f87171'],
                ['name' => 'Rosa', 'bg_color' => '#fbcfe8', 'text_color' => '#be185d', 'border_color' => '#f472b6'],
                ['name' => 'Branca', 'bg_color' => '#ffffff', 'text_color' => '#1e293b', 'border_color' => '#cbd5e1'],
                ['name' => 'Papel Jornal', 'bg_color' => '#e2e8f0', 'text_color' => '#334155', 'border_color' => '#94a3b8'],
                ['name' => 'Laranja', 'bg_color' => '#fed7aa', 'text_color' => '#c2410c', 'border_color' => '#fb923c'],
                ['name' => 'Lilás', 'bg_color' => '#e9d5ff', 'text_color' => '#7e22ce', 'border_color' => '#c084fc'],
            ];
        }

        View::render('rounds/operate', [
            'title' => "Rodada {$round['round_number']} — " . View::date($round['operation_date']),
            'round' => $round,
            'sellers' => $sellers,
            'totalSales' => $totalSales,
            'totalQty' => $totalQty,
            'totalPrizes' => $prizes,
            'profit' => $profit,
            'margin' => $margin,
            'suggestion' => $suggestion,
            'pricingRule' => $pricingRule,
            'cardColors' => $cardColors,
        ]);
    }

    public function create(): void
    {
        $dayId = (int)($_POST['operation_day_id'] ?? 0);
        $prizesCount = (int)($_POST['prizes_count'] ?? 2);
        if ($prizesCount < 1) $prizesCount = 1;
        if ($prizesCount > 3) $prizesCount = 3;

        // Process Prize 1 (pode ser texto/nome ou valor numérico)
        $p1Raw = trim($_POST['prize_1'] ?? '');
        $p1Title = trim($_POST['prize_1_title'] ?? '');
        $p1Amount = 0.00;
        if (!empty($p1Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p1Raw));
            if (is_numeric($cleaned)) {
                $p1Amount = (float)$cleaned;
            } elseif (empty($p1Title)) {
                $p1Title = $p1Raw;
            }
        }

        // Process Prize 2 (pode ser texto/nome ou valor numérico)
        $p2Raw = trim($_POST['prize_2'] ?? '');
        $p2Title = trim($_POST['prize_2_title'] ?? '');
        $p2Amount = 0.00;
        if ($prizesCount >= 2 && !empty($p2Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p2Raw));
            if (is_numeric($cleaned)) {
                $p2Amount = (float)$cleaned;
            } elseif (empty($p2Title)) {
                $p2Title = $p2Raw;
            }
        }

        // Process Prize 3
        $p3Raw = trim($_POST['prize_3'] ?? '');
        $p3Title = trim($_POST['prize_3_title'] ?? '');
        $p3Amount = 0.00;
        if ($prizesCount >= 3 && !empty($p3Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p3Raw));
            if (is_numeric($cleaned)) {
                $p3Amount = (float)$cleaned;
            } elseif (empty($p3Title)) {
                $p3Title = $p3Raw;
            }
        }

        $roundName = trim($_POST['round_name'] ?? '');
        $cardColor = trim($_POST['card_color'] ?? 'Amarela');
        $notes = trim($_POST['notes'] ?? '');

        $pdo = Database::getConnection();

        // Salva nova cor caso ainda não exista
        if (!empty($cardColor)) {
            try {
                $stmtColor = $pdo->prepare("SELECT id FROM card_colors WHERE LOWER(name) = LOWER(?)");
                $stmtColor->execute([$cardColor]);
                if (!$stmtColor->fetch()) {
                    $stmtAddColor = $pdo->prepare("INSERT INTO card_colors (name, bg_color, text_color, border_color) VALUES (?, '#e2e8f0', '#1e293b', '#94a3b8')");
                    $stmtAddColor->execute([$cardColor]);
                }
            } catch (\Throwable $e) {}
        }

        // Check if day is open
        $stmtDay = $pdo->prepare("SELECT * FROM operation_days WHERE id = ?");
        $stmtDay->execute([$dayId]);
        $day = $stmtDay->fetch();

        if (!$day || $day['status'] !== 'OPEN') {
            Response::redirect('/dia?id=' . $dayId, null, 'O dia precisa estar aberto para criar rodadas.');
        }

        // Check if another round is currently OPEN or IN_PROGRESS in this day
        $stmtOpen = $pdo->prepare("SELECT id, round_number FROM rounds WHERE operation_day_id = ? AND status IN ('OPEN', 'IN_PROGRESS')");
        $stmtOpen->execute([$dayId]);
        $alreadyOpen = $stmtOpen->fetch();
        if ($alreadyOpen) {
            Response::redirect('/rodada?id=' . $alreadyOpen['id'], null, "A Rodada {$alreadyOpen['round_number']} ainda está aberta/em andamento. Feche-a antes de iniciar uma nova.");
        }

        // Next round number
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(round_number), 0) + 1 FROM rounds WHERE operation_day_id = ?");
        $stmtMax->execute([$dayId]);
        $nextNumber = (int)$stmtMax->fetchColumn();
        $roundNumber = !empty($_POST['round_number']) ? (int)$_POST['round_number'] : $nextNumber;

        $stmt = $pdo->prepare("
            INSERT INTO rounds (
                operation_day_id, round_number, round_name, card_color, prizes_count, status, 
                prize_1, prize_2, prize_3, prize_1_title, prize_2_title, prize_3_title, notes, opened_at
            )
            VALUES (?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $dayId, 
            $roundNumber, 
            $roundName ?: null, 
            $cardColor ?: 'Amarela', 
            $prizesCount, 
            $p1Amount, 
            $p2Amount, 
            $p3Amount, 
            $p1Title ?: null, 
            $p2Title ?: null, 
            $p3Title ?: null, 
            $notes
        ]);
        $newRoundId = (int)$pdo->lastInsertId();

        AuditService::log('ROUND_CREATE', 'rounds', $newRoundId, null, [
            'day_id' => $dayId,
            'round_number' => $roundNumber,
            'round_name' => $roundName,
            'card_color' => $cardColor,
            'prizes_count' => $prizesCount,
            'prize_1' => $p1Amount,
            'prize_1_title' => $p1Title,
            'prize_2' => $p2Amount,
            'prize_2_title' => $p2Title,
            'prize_3' => $p3Amount,
            'prize_3_title' => $p3Title,
        ]);

        Response::redirect('/rodada?id=' . $newRoundId, "Rodada {$roundNumber} iniciada com sucesso!");
    }

    public function saveSales(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $amounts = $_POST['amounts'] ?? []; // seller_id => valor direto em R$
        $quantities = $_POST['quantities'] ?? []; // seller_id => quantity
        $prizesCount = (int)($_POST['prizes_count'] ?? 2);
        if ($prizesCount < 1) $prizesCount = 1;
        if ($prizesCount > 3) $prizesCount = 3;

        // Process Prize 1 (pode ser texto/nome ou valor numérico)
        $p1Raw = trim($_POST['prize_1'] ?? '');
        $p1Title = trim($_POST['prize_1_title'] ?? '');
        $p1Amount = 0.00;
        if (!empty($p1Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p1Raw));
            if (is_numeric($cleaned)) {
                $p1Amount = (float)$cleaned;
            } elseif (empty($p1Title)) {
                $p1Title = $p1Raw;
            }
        }

        // Process Prize 2
        $p2Raw = trim($_POST['prize_2'] ?? '');
        $p2Title = trim($_POST['prize_2_title'] ?? '');
        $p2Amount = 0.00;
        if ($prizesCount >= 2 && !empty($p2Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p2Raw));
            if (is_numeric($cleaned)) {
                $p2Amount = (float)$cleaned;
            } elseif (empty($p2Title)) {
                $p2Title = $p2Raw;
            }
        }

        // Process Prize 3
        $p3Raw = trim($_POST['prize_3'] ?? '');
        $p3Title = trim($_POST['prize_3_title'] ?? '');
        $p3Amount = 0.00;
        if ($prizesCount >= 3 && !empty($p3Raw)) {
            $cleaned = str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $p3Raw));
            if (is_numeric($cleaned)) {
                $p3Amount = (float)$cleaned;
            } elseif (empty($p3Title)) {
                $p3Title = $p3Raw;
            }
        }

        $winner1Name = trim($_POST['winner_1_name'] ?? $_POST['winner_name'] ?? '');
        $seller1Name = trim($_POST['seller_1_name'] ?? '');
        $winner2Name = trim($_POST['winner_2_name'] ?? '');
        $seller2Name = trim($_POST['seller_2_name'] ?? '');
        $winner3Name = trim($_POST['winner_3_name'] ?? '');
        $seller3Name = trim($_POST['seller_3_name'] ?? '');

        $roundNumber = (int)($_POST['round_number'] ?? 0);
        $roundName = trim($_POST['round_name'] ?? '');
        $cardColor = trim($_POST['card_color'] ?? '');

        $pdo = Database::getConnection();

        // Cadastra cor caso seja nova
        if (!empty($cardColor)) {
            try {
                $stmtColor = $pdo->prepare("SELECT id FROM card_colors WHERE LOWER(name) = LOWER(?)");
                $stmtColor->execute([$cardColor]);
                if (!$stmtColor->fetch()) {
                    $stmtAddColor = $pdo->prepare("INSERT INTO card_colors (name, bg_color, text_color, border_color) VALUES (?, '#e2e8f0', '#1e293b', '#94a3b8')");
                    $stmtAddColor->execute([$cardColor]);
                }
            } catch (\Throwable $e) {}
        }

        $stmt = $pdo->prepare("
            SELECT r.*, d.status as day_status, d.operation_date 
            FROM rounds r 
            JOIN operation_days d ON d.id = r.operation_day_id 
            WHERE r.id = ?
        ");
        $round = $stmt->fetch();

        if (!$round || !in_array($round['status'], ['OPEN', 'IN_PROGRESS']) || $round['day_status'] !== 'OPEN') {
            Response::redirect('/rodada?id=' . $roundId, null, 'Esta rodada não está aberta para edição.');
        }

        // Suporte para alterar o valor da rodada (preço avulso e combo específicos)
        $roundSinglePriceRaw = trim($_POST['single_price'] ?? '');
        $roundBundleQtyRaw = trim($_POST['bundle_quantity'] ?? '');
        $roundBundlePriceRaw = trim($_POST['bundle_price'] ?? '');

        $roundSinglePrice = !empty($roundSinglePriceRaw) ? (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $roundSinglePriceRaw)) : null;
        $roundBundleQty = !empty($roundBundleQtyRaw) ? (int)$roundBundleQtyRaw : null;
        $roundBundlePrice = !empty($roundBundlePriceRaw) ? (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $roundBundlePriceRaw)) : null;

        $pricingRule = PricingService::getRuleForDate($round['operation_date']);
        if ($roundSinglePrice !== null && $roundSinglePrice > 0) {
            $pricingRule['single_price'] = $roundSinglePrice;
        }
        if ($roundBundleQty !== null && $roundBundleQty > 0) {
            $pricingRule['bundle_quantity'] = $roundBundleQty;
        }
        if ($roundBundlePrice !== null && $roundBundlePrice > 0) {
            $pricingRule['bundle_price'] = $roundBundlePrice;
        }
        $singlePrice = (float)($pricingRule['single_price'] ?? 2.00);

        $pdo->beginTransaction();

        try {
            // Atualizar cabeçalho da rodada, premiações, títulos, ganhadores, vendedores e mudar status para IN_PROGRESS
            $finalRoundNumber = $roundNumber > 0 ? $roundNumber : (int)$round['round_number'];
            $stmtUpRound = $pdo->prepare("
                UPDATE rounds SET 
                    status = 'IN_PROGRESS',
                    round_number = ?,
                    round_name = ?,
                    card_color = ?,
                    prizes_count = ?,
                    single_price = ?,
                    bundle_quantity = ?,
                    bundle_price = ?,
                    prize_1 = ?, 
                    prize_1_title = ?,
                    prize_2 = ?, 
                    prize_2_title = ?,
                    prize_3 = ?, 
                    prize_3_title = ?,
                    winner_name = ?,
                    winner_1_name = ?,
                    seller_1_name = ?,
                    winner_2_name = ?,
                    seller_2_name = ?,
                    winner_3_name = ?,
                    seller_3_name = ?
                WHERE id = ?
            ");
            $stmtUpRound->execute([
                $finalRoundNumber,
                $roundName ?: null,
                $cardColor ?: ($round['card_color'] ?? 'Amarela'),
                $prizesCount,
                $roundSinglePrice,
                $roundBundleQty,
                $roundBundlePrice,
                $p1Amount,
                $p1Title ?: null,
                $p2Amount,
                $p2Title ?: null,
                $p3Amount,
                $p3Title ?: null,
                $winner1Name ?: null,
                $winner1Name ?: null,
                $seller1Name ?: null,
                $winner2Name ?: null,
                $seller2Name ?: null,
                $winner3Name ?: null,
                $seller3Name ?: null,
                $roundId
            ]);

            // Salvar vendas de cada vendedor com suporte a valor direto em R$ ignorando cartelas
            $stmtCheckSale = $pdo->prepare("SELECT id, quantity, amount FROM sales WHERE round_id = ? AND seller_id = ? AND cancelled_at IS NULL");
            $stmtUpdateSale = $pdo->prepare("UPDATE sales SET quantity = ?, amount = ?, pricing_rule_id = ?, pricing_snapshot_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtInsertSale = $pdo->prepare("
                INSERT INTO sales (operation_day_id, round_id, seller_id, quantity, amount, pricing_rule_id, pricing_snapshot_json, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $allSellerIds = array_unique(array_merge(array_keys($amounts), array_keys($quantities)));

            foreach ($allSellerIds as $sellerId) {
                $sellerId = (int)$sellerId;
                if ($sellerId <= 0) continue;

                $amtRaw = $amounts[$sellerId] ?? '';
                $parsedAmt = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', (string)$amtRaw));
                $qty = isset($quantities[$sellerId]) ? (int)$quantities[$sellerId] : 0;
                if ($qty < 0) $qty = 0;

                // Lançamento com valor em R$ informado diretamente
                if ($parsedAmt > 0) {
                    $amount = $parsedAmt;
                    if ($qty <= 0 && $singlePrice > 0) {
                        $qty = (int)round($amount / $singlePrice);
                    }
                    $snapshotJson = json_encode([
                        'direct_amount' => true,
                        'amount' => $amount,
                        'quantity' => $qty,
                    ], JSON_UNESCAPED_UNICODE);
                } elseif ($qty > 0) {
                    $calc = PricingService::calculate($qty, $pricingRule);
                    $amount = $calc['amount'];
                    $snapshotJson = json_encode($calc['snapshot'], JSON_UNESCAPED_UNICODE);
                } else {
                    $amount = 0.00;
                    $qty = 0;
                    $snapshotJson = json_encode([], JSON_UNESCAPED_UNICODE);
                }

                $stmtCheckSale->execute([$roundId, $sellerId]);
                $existing = $stmtCheckSale->fetch();

                if ($existing) {
                    $stmtUpdateSale->execute([$qty, $amount, $pricingRule['id'] ?? null, $snapshotJson, $existing['id']]);
                } else {
                    if ($amount > 0 || $qty > 0) {
                        $stmtInsertSale->execute([
                            $round['operation_day_id'],
                            $roundId,
                            $sellerId,
                            $qty,
                            $amount,
                            $pricingRule['id'] ?? null,
                            $snapshotJson,
                            Auth::id()
                        ]);
                    }
                }
            }

            $pdo->commit();

            AuditService::log('ROUND_SALES_SAVE', 'rounds', $roundId, null, [
                'round_number' => $finalRoundNumber,
                'status' => 'IN_PROGRESS',
                'prizes_count' => $prizesCount,
                'prize_1' => $p1Amount,
                'prize_1_title' => $p1Title,
                'winner_1_name' => $winner1Name,
                'seller_1_name' => $seller1Name,
                'prize_2' => $p2Amount,
                'prize_2_title' => $p2Title,
                'winner_2_name' => $winner2Name,
                'seller_2_name' => $seller2Name,
            ]);

            $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                   || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
                   || isset($_POST['_ajax']) || isset($_GET['_ajax']);

            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Vendas e premiações salvas com sucesso! Rodada em andamento.',
                    'round_id' => $roundId,
                    'status' => 'IN_PROGRESS'
                ]);
                exit;
            }

            Response::redirect('/rodada?id=' . $roundId, 'Vendas, prêmios e ganhadores salvos com sucesso! Rodada em andamento.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            Response::redirect('/rodada?id=' . $roundId, null, 'Erro ao salvar rodada: ' . $e->getMessage());
        }
    }

    public function close(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $prize1 = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['prize_1'] ?? '0'));
        $prize2 = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['prize_2'] ?? '0'));

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round || !in_array($round['status'], ['OPEN', 'IN_PROGRESS'])) {
            Response::redirect('/rodada?id=' . $roundId, null, 'Esta rodada já está fechada.');
        }

        // Calculate total sales
        $stmtSales = $pdo->prepare("SELECT COALESCE(SUM(amount), 0.00) FROM sales WHERE round_id = ? AND cancelled_at IS NULL");
        $stmtSales->execute([$roundId]);
        $totalSales = (float)$stmtSales->fetchColumn();

        // Calculate suggestion for next round
        $suggestion = PrizeSuggestionService::calculateNextRoundSuggestion($totalSales);

        $winnerName = trim($_POST['winner_name'] ?? ($round['winner_name'] ?? ''));

        $stmtClose = $pdo->prepare("
            UPDATE rounds 
            SET status = 'CLOSED', 
                prize_1 = ?, 
                prize_2 = ?, 
                winner_name = ?,
                suggested_total_next = ?, 
                suggested_prize_1_next = ?, 
                suggested_prize_2_next = ?, 
                closed_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmtClose->execute([
            $prize1,
            $prize2,
            $winnerName ?: null,
            $suggestion['total'],
            $suggestion['prize_1'],
            $suggestion['prize_2'],
            $roundId
        ]);

        AuditService::log('ROUND_CLOSE', 'rounds', $roundId, null, [
            'total_sales' => $totalSales,
            'prize_1' => $prize1,
            'prize_2' => $prize2,
            'suggested_total' => $suggestion['total']
        ]);

        Response::redirect('/rodada?id=' . $roundId, "Rodada {$round['round_number']} fechada com sucesso!");
    }

    public function reopen(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $reason = trim($_POST['reopen_reason'] ?? '');

        if (empty($reason)) {
            Response::redirect('/rodada?id=' . $roundId, null, 'É obrigatório informar o motivo para reabrir a rodada.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE rounds SET status = 'OPEN', closed_at = NULL WHERE id = ?");
        $stmt->execute([$roundId]);

        AuditService::log('ROUND_REOPEN', 'rounds', $roundId, null, ['reason' => $reason]);

        Response::redirect('/rodada?id=' . $roundId, 'Rodada reaberta com sucesso.');
    }

    public function changeStatus(): void
    {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['OPEN', 'IN_PROGRESS', 'PAUSED', 'CHECKING', 'CLOSED'];

        if (!in_array($newStatus, $allowed, true)) {
            Response::redirect('/rodada?id=' . $roundId, null, 'Status de rodada inválido.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$roundId]);
        $round = $stmt->fetch();

        if (!$round) {
            Response::redirect('/dia', null, 'Rodada não encontrada.');
        }

        $closedAt = ($newStatus === 'CLOSED') ? date('Y-m-d H:i:s') : null;
        $stmtUpdate = $pdo->prepare("UPDATE rounds SET status = ?, closed_at = ? WHERE id = ?");
        $stmtUpdate->execute([$newStatus, $closedAt, $roundId]);

        AuditService::log('ROUND_STATUS_CHANGE', 'rounds', $roundId, ['old' => $round['status']], ['new' => $newStatus]);

        $statusLabels = [
            'OPEN' => 'Aberta (Vendas)',
            'IN_PROGRESS' => 'Em andamento (Cantoria)',
            'PAUSED' => 'Pausada',
            'CHECKING' => 'Em conferência',
            'CLOSED' => 'Fechada'
        ];
        $msg = "Status da rodada alterado para: " . ($statusLabels[$newStatus] ?? $newStatus);

        $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (!empty($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
               || isset($_POST['_ajax']);

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'status' => $newStatus, 'message' => $msg]);
            exit;
        }

        Response::redirect('/rodada?id=' . $roundId, $msg);
    }
}

