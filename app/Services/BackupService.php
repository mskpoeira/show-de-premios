<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\StrictPDO;
use Database\Migrations;
use PDO;

class BackupService
{
    private const AUTO_INTERVAL = 900; // 15 min
    private const AUTO_RETENTION = 672; // 7 dias a cada 15 min

    private static array $tables = [
        'users','sellers','pricing_rules','operation_days','rounds','sales','cash_movements','cash_closings','settings','card_colors','audit_logs',
        'events','event_batches','prizes','buyers','orders','order_items','payments','tickets','ticket_numbers','ticket_prints','ticket_validations',
        'ticket_access_logs','game_rules','draws','draw_stones','ticket_game_state','ticket_scores','winner_claims','migration_history',
        // legado preservado durante a transição
        'called_numbers','winner_events',
    ];

    public static function autoBackupIfNeeded(): bool
    {
        $dir=self::backupDir();
        $marker=$dir.'/.last_auto_backup';
        $now=time();
        $last=is_file($marker)?(int)@file_get_contents($marker):0;
        if ($last>0 && ($now-$last)<self::AUTO_INTERVAL) return false;

        try {
            self::writeSnapshot('auto_backup_'.date('Y-m-d_His').'.json');
            file_put_contents($marker,(string)$now,LOCK_EX);
            self::pruneAutomaticBackups();
            return true;
        } catch (\Throwable $e) {
            error_log('[Backup auto] '.$e->getMessage());
            return false;
        }
    }

    public static function createBackupData(): array
    {
        $pdo=Database::getConnection();
        $tables=[];
        foreach(self::$tables as $table) {
            if (!self::tableExists($pdo,$table)) continue;
            $stmt=$pdo->query("SELECT * FROM {$table}");
            $tables[$table]=$stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'app'=>'Show de Prêmios',
            'schema_version'=>Migrations::VERSION,
            'database_driver'=>Database::getDriver(),
            'exported_at'=>date('c'),
            'tables'=>$tables,
        ];
    }

    public static function exportBackupFile(): void
    {
        $data=self::createBackupData();
        $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json===false) throw new \RuntimeException('Falha ao serializar backup.');
        $filename='backup_showdepremios_'.date('Y-m-d_His').'.json';
        file_put_contents(self::backupDir().'/'.$filename,$json,LOCK_EX);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-store');
        echo $json;
        exit;
    }

    public static function restoreFromJson(string $jsonString): bool
    {
        if (!Auth::isMaster()) throw new \RuntimeException('Restauração permitida apenas ao Administrador Master.');
        if (strlen($jsonString)>50*1024*1024) throw new \RuntimeException('Arquivo de backup excede 50 MB.');

        $backup=json_decode($jsonString,true,512,JSON_THROW_ON_ERROR);
        if (($backup['app'] ?? null)!=='Show de Prêmios' || !is_array($backup['tables'] ?? null)) {
            throw new \RuntimeException('Arquivo de backup inválido para o Show de Prêmios.');
        }

        $pdo=Database::getConnection();
        $driver=Database::getDriver();
        self::writeSnapshot('pre_restore_'.date('Y-m-d_His').'.json');

        self::setForeignKeys($pdo,$driver,false);
        $pdo->beginTransaction();
        try {
            $existing=array_values(array_filter(self::$tables,fn(string $t):bool=>self::tableExists($pdo,$t)));
            foreach(array_reverse($existing) as $table) {
                if (!array_key_exists($table,$backup['tables'])) continue;
                $pdo->exec("DELETE FROM {$table}");
            }

            foreach($existing as $table) {
                $rows=$backup['tables'][$table] ?? null;
                if (!is_array($rows) || $rows===[]) continue;
                $columns=array_map('strval',array_keys($rows[0]));
                if (!$columns) continue;
                foreach($columns as $column) {
                    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$column)) throw new \RuntimeException('Coluna inválida no backup.');
                }
                $columnSql=implode(',',array_map(fn(string $c):string=>'"'.str_replace('"','""',$c).'"',$columns));
                if ($driver==='mysql') $columnSql=implode(',',array_map(fn(string $c):string=>'`'.str_replace('`','``',$c).'`',$columns));
                $placeholders=implode(',',array_fill(0,count($columns),'?'));
                $stmt=$pdo->prepare("INSERT INTO {$table} ({$columnSql}) VALUES ({$placeholders})");
                foreach($rows as $row) {
                    $values=[];
                    foreach($columns as $column) $values[]=$row[$column] ?? null;
                    $stmt->execute($values);
                }
            }

            $pdo->commit();
            self::setForeignKeys($pdo,$driver,true);
            AuditService::log('BACKUP_RESTORE','system',null,null,['status'=>'success','schema_version'=>$backup['schema_version'] ?? null]);
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::setForeignKeys($pdo,$driver,true);
            throw $e;
        }
    }

    public static function cleanDatabaseKeepUsersAndSellers(): array
    {
        if (!Auth::isMaster()) throw new \RuntimeException('Limpeza permitida apenas ao Administrador Master.');
        self::writeSnapshot('pre_clean_'.date('Y-m-d_His').'.json');
        $pdo=Database::getConnection();
        $driver=Database::getDriver();
        $tables=['winner_claims','winner_events','ticket_scores','ticket_game_state','draw_stones','called_numbers','draws','ticket_access_logs','ticket_validations','ticket_prints','ticket_numbers','order_items','payments','tickets','orders','buyers','sales','cash_movements','cash_closings','rounds','operation_days','audit_logs'];
        return self::cleanTables($pdo,$driver,$tables,'SYSTEM_RESET');
    }

    public static function cleanDatabaseSelective(array $selected): array
    {
        if (!Auth::isMaster()) throw new \RuntimeException('Limpeza seletiva permitida apenas ao Administrador Master.');
        self::writeSnapshot('pre_selective_clean_'.date('Y-m-d_His').'.json');
        $pdo=Database::getConnection();
        $driver=Database::getDriver();
        $cleared=[];
        self::setForeignKeys($pdo,$driver,false);
        $pdo->beginTransaction();
        try {
            $groups=[
                'sales'=>['sales'],
                'rounds'=>['winner_claims','winner_events','ticket_scores','ticket_game_state','draw_stones','called_numbers','draws','rounds'],
                'cash'=>['cash_movements','cash_closings'],
                'operation_days'=>['operation_days'],
                'digital_orders'=>['ticket_access_logs','ticket_validations','ticket_prints','ticket_numbers','order_items','payments','tickets','orders','buyers'],
                'audit_logs'=>['audit_logs'],
            ];
            foreach($groups as $key=>$tables) {
                if (empty($selected[$key])) continue;
                foreach($tables as $table) {
                    if (!self::tableExists($pdo,$table)) continue;
                    $count=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                    $pdo->exec("DELETE FROM {$table}");
                    $cleared[$table]=$count;
                }
            }

            if (!empty($selected['pricing_history']) && self::tableExists($pdo,'pricing_rules')) {
                $latest=(int)($pdo->query("SELECT id FROM pricing_rules ORDER BY CASE WHEN effective_to IS NULL THEN 0 ELSE 1 END,effective_from DESC,id DESC LIMIT 1")->fetchColumn() ?: 0);
                if ($latest>0) {
                    $count=(int)$pdo->query("SELECT COUNT(*) FROM pricing_rules WHERE id<>{$latest}")->fetchColumn();
                    $pdo->exec("DELETE FROM pricing_rules WHERE id<>{$latest}");
                    $pdo->exec("UPDATE pricing_rules SET effective_to=NULL,active=1 WHERE id={$latest}");
                    $cleared['pricing_rules_history']=$count;
                }
            }
            if (!empty($selected['sellers']) && self::tableExists($pdo,'sellers')) {
                $count=(int)$pdo->query('SELECT COUNT(*) FROM sellers')->fetchColumn();
                $pdo->exec('DELETE FROM sellers');
                $cleared['sellers']=$count;
            }
            if (!empty($selected['operators']) && self::tableExists($pdo,'users')) {
                $currentId=(int)Auth::id();
                $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE id<>? AND UPPER(role)<>'MASTER'");
                $stmt->execute([$currentId]);
                $count=(int)$stmt->fetchColumn();
                $stmt=$pdo->prepare("DELETE FROM users WHERE id<>? AND UPPER(role)<>'MASTER'");
                $stmt->execute([$currentId]);
                $cleared['operators']=$count;
            }

            $pdo->commit();
            self::setForeignKeys($pdo,$driver,true);
            AuditService::log('SYSTEM_SELECTIVE_RESET','system',null,null,['cleared_items'=>$cleared,'timezone'=>'America/Sao_Paulo']);
            return ['success'=>true,'cleared'=>$cleared];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::setForeignKeys($pdo,$driver,true);
            throw $e;
        }
    }

    private static function cleanTables(StrictPDO $pdo,string $driver,array $tables,string $auditAction): array
    {
        self::setForeignKeys($pdo,$driver,false);
        $pdo->beginTransaction();
        $cleared=[];
        try {
            foreach($tables as $table) {
                if (!self::tableExists($pdo,$table)) continue;
                $count=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                $pdo->exec("DELETE FROM {$table}");
                $cleared[$table]=$count;
            }
            $pdo->commit();
            self::setForeignKeys($pdo,$driver,true);
            AuditService::log($auditAction,'system',null,null,['cleared_tables'=>$cleared,'timezone'=>'America/Sao_Paulo']);
            return ['success'=>true,'cleared'=>$cleared];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::setForeignKeys($pdo,$driver,true);
            throw $e;
        }
    }

    private static function writeSnapshot(string $filename): string
    {
        $json=json_encode(self::createBackupData(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json===false) throw new \RuntimeException('Falha ao serializar backup.');
        $path=self::backupDir().'/'.$filename;
        if (file_put_contents($path,$json,LOCK_EX)===false) throw new \RuntimeException('Falha ao gravar backup.');
        @chmod($path,0640);
        return $path;
    }

    private static function backupDir(): string
    {
        $dir=__DIR__.'/../../storage/backups';
        if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new \RuntimeException('Não foi possível criar diretório de backup.');
        return $dir;
    }

    private static function pruneAutomaticBackups(): void
    {
        $files=glob(self::backupDir().'/auto_backup_*.json') ?: [];
        if (count($files)<=self::AUTO_RETENTION) return;
        usort($files,fn(string $a,string $b):int=>filemtime($a)<=>filemtime($b));
        foreach(array_slice($files,0,count($files)-self::AUTO_RETENTION) as $file) @unlink($file);
    }

    private static function tableExists(StrictPDO $pdo,string $table): bool
    {
        try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (\Throwable $e) { return false; }
    }

    private static function setForeignKeys(StrictPDO $pdo,string $driver,bool $enabled): void
    {
        try {
            if ($driver==='sqlite') $pdo->exec('PRAGMA foreign_keys = '.($enabled?'ON':'OFF'));
            elseif ($driver==='mysql') $pdo->exec('SET FOREIGN_KEY_CHECKS = '.($enabled?'1':'0'));
        } catch (\Throwable $e) {}
    }
}
