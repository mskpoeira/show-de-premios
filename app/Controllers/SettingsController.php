<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\PricingService;
use PDO;

class SettingsController
{
    public function index(): void
    {
        $tab = trim($_GET['tab'] ?? 'gerais');
        if (!in_array($tab, ['gerais', 'vendedores', 'operadores', 'backup'], true)) {
            $tab = 'gerais';
        }

        $pdo = Database::getConnection();

        // 1. Dados de Parâmetros Gerais e Preços
        $currentPricing = PricingService::getRuleForDate();
        $stmtHistory = $pdo->query("SELECT * FROM pricing_rules ORDER BY effective_from DESC");
        $pricingHistory = $stmtHistory->fetchAll();

        $stmtSettings = $pdo->query("SELECT key, value FROM settings");
        $settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Dados de Vendedores(as)
        $sellers = [];
        try {
            $stmtSellers = $pdo->query("
                SELECT s.*,
                       COALESCE(SUM(sa.quantity), 0) as total_qty,
                       COALESCE(SUM(sa.amount), 0.00) as total_sales,
                       COUNT(DISTINCT sa.operation_day_id) as days_active
                FROM sellers s
                LEFT JOIN sales sa ON sa.seller_id = s.id AND sa.cancelled_at IS NULL
                GROUP BY s.id
                ORDER BY s.active DESC, s.name ASC
            ");
            $sellers = $stmtSellers->fetchAll();
        } catch (\Throwable $e) {}

        // 3. Dados de Operadores (se permitido)
        $operators = [];
        if (Auth::canManageUsers()) {
            $isAdmin = Auth::isAdmin();
            $currentLogin = strtolower(Auth::user()['login'] ?? '');
            $isMasterTcardozo = ($currentLogin === 'tcardozo');

            if ($isMasterTcardozo) {
                $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users ORDER BY active DESC, name ASC");
            } elseif ($isAdmin) {
                $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users WHERE LOWER(login) != 'tcardozo' ORDER BY active DESC, name ASC");
            } else {
                $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users WHERE LOWER(login) != 'tcardozo' AND role != 'ADMIN' ORDER BY active DESC, name ASC");
            }
            $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Dados de Backup (se admin)
        $backupFiles = [];
        if (Auth::isAdmin()) {
            $backupDir = __DIR__ . '/../../storage/backups';
            if (is_dir($backupDir)) {
                $scan = scandir($backupDir);
                foreach ($scan as $file) {
                    if (str_ends_with($file, '.json')) {
                        $path = "{$backupDir}/{$file}";
                        $backupFiles[] = [
                            'name' => $file,
                            'size' => filesize($path),
                            'date' => filemtime($path),
                        ];
                    }
                }
                usort($backupFiles, fn($a, $b) => $b['date'] <=> $a['date']);
            }
        }

        // 5. Contagens para Limpeza Seletiva do Banco de Dados (Master Admin)
        $tableCounts = [];
        if (Auth::isMasterAdmin()) {
            try {
                $tableCounts['sales'] = (int)$pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
                $tableCounts['rounds'] = (int)$pdo->query("SELECT COUNT(*) FROM rounds")->fetchColumn();
                $cashMovements = (int)$pdo->query("SELECT COUNT(*) FROM cash_movements")->fetchColumn();
                $cashClosings = (int)$pdo->query("SELECT COUNT(*) FROM cash_closings")->fetchColumn();
                $tableCounts['cash'] = $cashMovements + $cashClosings;
                $tableCounts['operation_days'] = (int)$pdo->query("SELECT COUNT(*) FROM operation_days")->fetchColumn();
                $totalPricing = (int)$pdo->query("SELECT COUNT(*) FROM pricing_rules")->fetchColumn();
                $tableCounts['pricing_history'] = max(0, $totalPricing - 1);
                $tableCounts['sellers'] = (int)$pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn();
                $currentUserId = (int)Auth::id();
                $tableCounts['operators'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id != {$currentUserId} AND LOWER(login) != 'tcardozo'")->fetchColumn();
                $tableCounts['audit_logs'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
            } catch (\Throwable $e) {
                // Silently handle if table is missing
            }
        }

        View::render('settings/index', [
            'title' => 'Configurações e Administração — Show de Prêmios',
            'tab' => $tab,
            'currentPricing' => $currentPricing,
            'pricingHistory' => $pricingHistory,
            'settings' => $settings,
            'sellers' => $sellers,
            'operators' => $operators,
            'backupFiles' => $backupFiles,
            'tableCounts' => $tableCounts,
        ]);
    }

    public function updatePricing(): void
    {
        $singlePrice = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['single_price'] ?? '2.00'));
        $bundleQty = (int)($_POST['bundle_quantity'] ?? 3);
        $bundlePrice = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['bundle_price'] ?? '5.00'));
        $effectiveFrom = trim($_POST['effective_from'] ?? date('Y-m-d'));

        if ($singlePrice <= 0 || $bundlePrice <= 0 || $bundleQty <= 1) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Valores de preço e quantidade promocional devem ser positivos.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Close existing rule's effective_to if active
            $yesterday = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
            $stmtClose = $pdo->prepare("UPDATE pricing_rules SET effective_to = ? WHERE effective_to IS NULL");
            $stmtClose->execute([$yesterday]);

            // Insert new rule
            $stmtInsert = $pdo->prepare("
                INSERT INTO pricing_rules (effective_from, single_quantity, single_price, bundle_quantity, bundle_price, active)
                VALUES (?, 1, ?, ?, ?, 1)
            ");
            $stmtInsert->execute([$effectiveFrom, $singlePrice, $bundleQty, $bundlePrice]);
            $newId = (int)$pdo->lastInsertId();

            $pdo->commit();

            AuditService::log('PRICING_UPDATE', 'pricing_rules', $newId, null, [
                'effective_from' => $effectiveFrom,
                'single_price' => $singlePrice,
                'bundle_quantity' => $bundleQty,
                'bundle_price' => $bundlePrice,
            ]);

            Response::redirect('/configuracoes?tab=gerais', 'Nova regra de preços criada com vigência a partir de ' . View::date($effectiveFrom) . '. Vendas anteriores foram preservadas intactas.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::redirect('/configuracoes?tab=gerais', null, 'Erro ao atualizar preços: ' . $e->getMessage());
        }
    }

    public function updateParameters(): void
    {
        $systemTitle = trim($_POST['system_title'] ?? '');
        $prizePoolPercent = (float)($_POST['prize_pool_percent'] ?? 50);
        $prize1Percent = (float)($_POST['prize_1_percent'] ?? 65);
        $prize2Percent = (float)($_POST['prize_2_percent'] ?? 35);
        $rounding = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['prize_rounding'] ?? '10.00'));
        $tolerance = (float)str_replace(',', '.', str_replace(['R$', ' ', '.'], '', $_POST['cash_tolerance'] ?? '0.01'));
        $carrySuggestion = isset($_POST['carry_previous_prize_suggestion']) ? 'true' : 'false';

        $pdo = Database::getConnection();

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
        $stmtUpdate = $pdo->prepare("UPDATE settings SET value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO settings (key, value, type, updated_by, updated_at) VALUES (?, ?, 'string', ?, CURRENT_TIMESTAMP)");

        $updates = [
            'prize_pool_percent' => (string)$prizePoolPercent,
            'prize_1_percent' => (string)$prize1Percent,
            'prize_2_percent' => (string)$prize2Percent,
            'prize_rounding' => (string)$rounding,
            'cash_tolerance' => (string)$tolerance,
            'carry_previous_prize_suggestion' => $carrySuggestion,
        ];

        if (!empty($systemTitle)) {
            $updates['system_title'] = $systemTitle;
        }

        if (isset($_POST['pix_key'])) {
            $updates['pix_key'] = trim($_POST['pix_key']);
            $updates['pix_key_type'] = trim($_POST['pix_key_type'] ?? 'CHAVE_ALEATORIA');
            $updates['pix_receiver_name'] = trim($_POST['pix_receiver_name'] ?? '');
            $updates['pix_receiver_city'] = trim($_POST['pix_receiver_city'] ?? '');
            $updates['pix_description'] = trim($_POST['pix_description'] ?? '');
            $updates['pix_banner_title'] = trim($_POST['pix_banner_title'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR');
            $updates['pix_show_on_telao'] = isset($_POST['pix_show_on_telao']) ? 'true' : 'false';
        }

        foreach ($updates as $key => $val) {
            $stmtCheck->execute([$key]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $stmtUpdate->execute([$val, Auth::id(), $key]);
            } else {
                $stmtInsert->execute([$key, $val, Auth::id()]);
            }
        }

        AuditService::log('SETTINGS_UPDATE', 'settings', null, null, $updates);

        Response::redirect('/configuracoes?tab=gerais', 'Parâmetros e configurações atualizados com sucesso!');
    }

    public function updatePix(): void
    {
        $pixKey = trim($_POST['pix_key'] ?? '');
        $pixKeyType = trim($_POST['pix_key_type'] ?? 'CHAVE_ALEATORIA');
        $pixReceiverName = trim($_POST['pix_receiver_name'] ?? '');
        $pixReceiverCity = trim($_POST['pix_receiver_city'] ?? '');
        $pixDescription = trim($_POST['pix_description'] ?? '');
        $pixBannerTitle = trim($_POST['pix_banner_title'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR');
        $pixShowOnTelao = isset($_POST['pix_show_on_telao']) ? 'true' : 'false';

        $pdo = Database::getConnection();
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
        $stmtUpdate = $pdo->prepare("UPDATE settings SET value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO settings (key, value, type, updated_by, updated_at) VALUES (?, ?, 'string', ?, CURRENT_TIMESTAMP)");

        $updates = [
            'pix_key' => $pixKey,
            'pix_key_type' => $pixKeyType,
            'pix_receiver_name' => $pixReceiverName,
            'pix_receiver_city' => $pixReceiverCity,
            'pix_description' => $pixDescription,
            'pix_banner_title' => $pixBannerTitle ?: 'PAGUE COM PIX DIRETO DO SEU LUGAR',
            'pix_show_on_telao' => $pixShowOnTelao,
        ];

        foreach ($updates as $key => $val) {
            $stmtCheck->execute([$key]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $stmtUpdate->execute([$val, Auth::id(), $key]);
            } else {
                $stmtInsert->execute([$key, $val, Auth::id()]);
            }
        }

        AuditService::log('SETTINGS_UPDATE', 'settings', null, null, $updates);

        Response::redirect('/configuracoes?tab=gerais', 'Configurações oficiais do PIX atualizadas com sucesso!');
    }

    public function deletePricing(): void
    {
        if (!Auth::isMasterAdmin()) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Apenas o Administrador Master tem permissão para excluir regras de preços.');
        }

        $ruleId = (int)($_POST['rule_id'] ?? 0);
        if ($ruleId <= 0) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Regra de preços inválida.');
        }

        $pdo = Database::getConnection();

        $totalRules = (int)$pdo->query("SELECT COUNT(*) FROM pricing_rules")->fetchColumn();
        if ($totalRules <= 1) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Não é permitido excluir a única regra de preços do sistema. Deve haver ao menos uma regra configurada.');
        }

        $stmt = $pdo->prepare("SELECT * FROM pricing_rules WHERE id = ?");
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Regra de preços não encontrada.');
        }

        $isCurrentlyActive = empty($rule['effective_to']);

        $pdo->beginTransaction();
        try {
            $stmtDel = $pdo->prepare("DELETE FROM pricing_rules WHERE id = ?");
            $stmtDel->execute([$ruleId]);

            // Se a regra excluída era a vigente atual, reativa a mais recente restante
            if ($isCurrentlyActive) {
                $latestRemainingId = (int)$pdo->query("SELECT id FROM pricing_rules ORDER BY effective_from DESC, id DESC LIMIT 1")->fetchColumn();
                if ($latestRemainingId > 0) {
                    $pdo->exec("UPDATE pricing_rules SET effective_to = NULL, active = 1 WHERE id = {$latestRemainingId}");
                }
            }

            $pdo->commit();

            AuditService::log('PRICING_RULE_DELETED', 'pricing_rules', $ruleId, $rule, [
                'deleted_rule_id' => $ruleId,
                'was_active' => $isCurrentlyActive,
                'deleted_by' => Auth::id()
            ]);

            Response::redirect('/configuracoes?tab=gerais', 'Regra de preços excluída com sucesso!' . ($isCurrentlyActive ? ' A regra anterior mais recente foi redefinida como vigente.' : ''));
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::redirect('/configuracoes?tab=gerais', null, 'Erro ao excluir regra de preços: ' . $e->getMessage());
        }
    }

    public function cleanDatabase(): void
    {
        if (!Auth::isMasterAdmin()) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Apenas o Administrador Master tem permissão para limpar os dados.');
        }

        $cleanItems = $_POST['clean_items'] ?? [];
        if (!is_array($cleanItems) || empty($cleanItems)) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Nenhum item foi selecionado para exclusão. Marque ao menos uma opção.');
        }

        $selected = array_fill_keys($cleanItems, true);

        try {
            $result = \App\Services\BackupService::cleanDatabaseSelective($selected);
            $cleared = $result['cleared'] ?? [];

            $labels = [
                'sales' => 'Vendas',
                'rounds' => 'Rodadas',
                'cash_movements' => 'Movimentações de Caixa',
                'cash_closings' => 'Fechamentos de Caixa',
                'operation_days' => 'Dias de Operação',
                'pricing_rules_history' => 'Histórico Antigo de Preços',
                'sellers' => 'Vendedores',
                'operators' => 'Operadores',
                'audit_logs' => 'Logs de Auditoria',
            ];

            $clearedDetails = [];
            foreach ($cleared as $key => $cnt) {
                $name = $labels[$key] ?? $key;
                $clearedDetails[] = "{$name}: {$cnt}";
            }

            $detailsMsg = !empty($clearedDetails) ? ' (' . implode(', ', $clearedDetails) . ')' : '';

            Response::redirect('/configuracoes?tab=gerais', 'Limpeza seletiva executada com sucesso!' . $detailsMsg . ' Um backup preventivo de segurança foi gerado.');
        } catch (\Throwable $e) {
            Response::redirect('/configuracoes?tab=gerais', null, 'Erro ao limpar dados selecionados: ' . $e->getMessage());
        }
    }
}
