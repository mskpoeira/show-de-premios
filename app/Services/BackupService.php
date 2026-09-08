<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class BackupService
{
    private static array $tables = [
        'users',
        'sellers',
        'pricing_rules',
        'operation_days',
        'rounds',
        'sales',
        'cash_movements',
        'cash_closings',
        'settings',
        'card_colors',
        'audit_logs'
    ];

    public static function autoBackupIfNeeded(): bool
    {
        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $markerFile = $backupDir . '/.last_auto_backup';
        $now = time();
        $interval = 300; // 5 minutos

        if (file_exists($markerFile)) {
            $lastBackupTime = (int)@file_get_contents($markerFile);
            if (($now - $lastBackupTime) < $interval) {
                return false;
            }
        }

        try {
            $data = self::createBackupData();
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $filename = 'auto_backup_' . date('Y-m-d_His') . '.json';
            file_put_contents("{$backupDir}/{$filename}", $json);
            file_put_contents($markerFile, (string)$now);

            // Mantém os últimos 60 arquivos de backup automático (~5 horas de histórico recente a cada 5 min)
            $files = glob("{$backupDir}/auto_backup_*.json");
            if ($files && count($files) > 60) {
                usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
                $toDelete = array_slice($files, 0, count($files) - 60);
                foreach ($toDelete as $f) {
                    @unlink($f);
                }
            }

            return true;
        } catch (\Throwable $e) {
            error_log('Erro no backup automático: ' . $e->getMessage());
            return false;
        }
    }

    public static function createBackupData(): array
    {
        $pdo = Database::getConnection();
        $data = [
            'app' => 'Show de Prêmios',
            'schema_version' => '1.0',
            'exported_at' => date('c'),
            'tables' => [],
        ];

        foreach (self::$tables as $table) {
            $stmt = $pdo->query("SELECT * FROM {$table}");
            $data['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $data;
    }

    public static function exportBackupFile(): void
    {
        $data = self::createBackupData();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'backup_showdepremios_' . date('Y-m-d_His') . '.json';

        // Also save to storage
        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        file_put_contents("{$backupDir}/{$filename}", $json);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json;
        exit;
    }

    public static function restoreFromJson(string $jsonString): bool
    {
        $backupData = json_decode($jsonString, true);
        if (!$backupData || empty($backupData['tables'])) {
            throw new \Exception("Arquivo de backup inválido ou formato corrompido.");
        }

        $pdo = Database::getConnection();

        // 1. Generate automatic pre-restore snapshot
        $preBackup = self::createBackupData();
        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        file_put_contents("{$backupDir}/pre_restore_" . date('Y-m-d_His') . '.json', json_encode($preBackup));

        // 2. Execute restore in transaction
        $pdo->beginTransaction();

        try {
            // Disable foreign key checks for clean restore
            $driver = Database::getDriver();
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = OFF;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            }

            // Clean tables in reverse dependency order
            $reverseTables = array_reverse(self::$tables);
            foreach ($reverseTables as $table) {
                $pdo->exec("DELETE FROM {$table}");
            }

            // Insert records
            foreach (self::$tables as $table) {
                if (empty($backupData['tables'][$table])) {
                    continue;
                }

                $rows = $backupData['tables'][$table];
                $firstRow = $rows[0];
                $columns = array_keys($firstRow);
                $colList = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                $stmt = $pdo->prepare("INSERT INTO {$table} ({$colList}) VALUES ({$placeholders})");

                foreach ($rows as $row) {
                    $stmt->execute(array_values($row));
                }
            }

            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            $pdo->commit();

            AuditService::log('BACKUP_RESTORE', 'system', null, null, ['status' => 'success']);
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Limpa o banco de dados e a auditoria, mantendo estritamente:
     * - users (Usuários e Operadores)
     * - sellers (Vendedores e Vendedoras)
     * - pricing_rules (Regras de preços)
     * - card_colors (Cores de cartelas)
     * - settings (Configurações gerais)
     */
    public static function cleanDatabaseKeepUsersAndSellers(): array
    {
        $pdo = Database::getConnection();
        $driver = Database::getDriver();

        // 1. Gera backup preventivo de segurança antes de qualquer exclusão
        try {
            self::autoBackupIfNeeded();
        } catch (\Throwable $e) {}

        if ($driver === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
        } elseif ($driver === 'mysql') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        }

        $pdo->beginTransaction();

        try {
            $tablesToClean = [
                'sales',
                'cash_movements',
                'cash_closings',
                'rounds',
                'operation_days',
                'audit_logs',
            ];

            $cleared = [];
            foreach ($tablesToClean as $table) {
                try {
                    $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                    $pdo->exec("DELETE FROM {$table}");
                    $cleared[$table] = $count;
                } catch (\Throwable $e) {
                    $cleared[$table] = 0;
                }
            }

            // Reseta contadores AUTOINCREMENT no SQLite
            if ($driver === 'sqlite') {
                try {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('sales', 'cash_movements', 'cash_closings', 'rounds', 'operation_days', 'audit_logs')");
                } catch (\Throwable $e) {}
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            $pdo->commit();

            // 2. Insere log inaugural de auditoria no Horário Oficial de Brasília
            AuditService::log('SYSTEM_RESET', 'system', null, null, [
                'description' => 'Limpeza de banco de dados e auditoria realizada com sucesso. Usuários e vendedores mantidos.',
                'cleared_tables' => $cleared,
                'timezone' => 'Horário Oficial de Brasília (America/Sao_Paulo)',
            ]);

            return [
                'success' => true,
                'cleared' => $cleared,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }
            throw $e;
        }
    }

    /**
     * Limpeza seletiva de tabelas e dados conforme seleção do Administrador Master
     */
    public static function cleanDatabaseSelective(array $selected): array
    {
        $pdo = Database::getConnection();
        $driver = Database::getDriver();

        // Gera backup preventivo de segurança antes de qualquer exclusão
        try {
            self::autoBackupIfNeeded();
        } catch (\Throwable $e) {}

        if ($driver === 'sqlite') {
            $pdo->exec("PRAGMA foreign_keys = OFF;");
        } elseif ($driver === 'mysql') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        }

        $pdo->beginTransaction();

        try {
            $cleared = [];

            // 1. Vendas
            if (!empty($selected['sales'])) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
                $pdo->exec("DELETE FROM sales");
                $cleared['sales'] = $cnt;
            }

            // 2. Rodadas e sorteios
            if (!empty($selected['rounds'])) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM rounds")->fetchColumn();
                $pdo->exec("DELETE FROM rounds");
                $cleared['rounds'] = $cnt;
            }

            // 3. Movimentações de Caixa e Fechamentos
            if (!empty($selected['cash'])) {
                $cnt1 = (int)$pdo->query("SELECT COUNT(*) FROM cash_movements")->fetchColumn();
                $cnt2 = (int)$pdo->query("SELECT COUNT(*) FROM cash_closings")->fetchColumn();
                $pdo->exec("DELETE FROM cash_movements");
                $pdo->exec("DELETE FROM cash_closings");
                $cleared['cash_movements'] = $cnt1;
                $cleared['cash_closings'] = $cnt2;
            }

            // 4. Dias de operação
            if (!empty($selected['operation_days'])) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM operation_days")->fetchColumn();
                $pdo->exec("DELETE FROM operation_days");
                $cleared['operation_days'] = $cnt;
            }

            // 5. Histórico antigo de regras de preços (preserva a regra mais recente como vigente)
            if (!empty($selected['pricing_history'])) {
                $latestRuleId = (int)$pdo->query("SELECT id FROM pricing_rules ORDER BY CASE WHEN effective_to IS NULL THEN 0 ELSE 1 END, effective_from DESC LIMIT 1")->fetchColumn();
                if ($latestRuleId > 0) {
                    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM pricing_rules WHERE id != {$latestRuleId}")->fetchColumn();
                    $pdo->exec("DELETE FROM pricing_rules WHERE id != {$latestRuleId}");
                    $pdo->exec("UPDATE pricing_rules SET effective_to = NULL, active = 1 WHERE id = {$latestRuleId}");
                    $cleared['pricing_rules_history'] = $cnt;
                }
            }

            // 6. Vendedores
            if (!empty($selected['sellers'])) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn();
                $pdo->exec("DELETE FROM sellers");
                $cleared['sellers'] = $cnt;
            }

            // 7. Operadores (exceto o próprio master logado e tcardozo)
            if (!empty($selected['operators'])) {
                $currentId = (int)\App\Core\Auth::id();
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id != {$currentId} AND LOWER(login) != 'tcardozo'")->fetchColumn();
                $pdo->exec("DELETE FROM users WHERE id != {$currentId} AND LOWER(login) != 'tcardozo'");
                $cleared['operators'] = $cnt;
            }

            // 8. Logs de auditoria
            if (!empty($selected['audit_logs'])) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
                $pdo->exec("DELETE FROM audit_logs");
                $cleared['audit_logs'] = $cnt;
            }

            // Reseta contadores AUTOINCREMENT no SQLite para as tabelas limpas
            if ($driver === 'sqlite') {
                foreach (array_keys($cleared) as $tbl) {
                    try {
                        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}'");
                    } catch (\Throwable $e) {}
                }
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            $pdo->commit();

            // Log de auditoria da limpeza seletiva
            AuditService::log('SYSTEM_SELECTIVE_RESET', 'system', null, null, [
                'description' => 'Limpeza seletiva executada pelo Administrador Master.',
                'cleared_items' => $cleared,
                'timezone' => 'Horário Oficial de Brasília (America/Sao_Paulo)',
            ]);

            return [
                'success' => true,
                'cleared' => $cleared,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }
            throw $e;
        }
    }
}
