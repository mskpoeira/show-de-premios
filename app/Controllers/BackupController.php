<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\BackupService;

class BackupController
{
    public function index(): void
    {
        $backupDir = __DIR__ . '/../../storage/backups';
        $files = [];

        if (is_dir($backupDir)) {
            $scan = scandir($backupDir);
            foreach ($scan as $file) {
                if (str_ends_with($file, '.json')) {
                    $path = "{$backupDir}/{$file}";
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($path),
                        'date' => filemtime($path),
                    ];
                }
            }
            usort($files, fn($a, $b) => $b['date'] <=> $a['date']);
        }

        View::render('backup/index', [
            'title' => 'Backups do Sistema — Show de Prêmios',
            'files' => $files,
        ]);
    }

    public function export(): void
    {
        AuditService::log('BACKUP_EXPORT', 'system');
        BackupService::exportBackupFile();
    }

    public function restore(): void
    {
        if (empty($_FILES['backup_file']['tmp_name'])) {
            Response::redirect('/configuracoes?tab=backup', null, 'Selecione um arquivo de backup JSON válido.');
        }

        $content = file_get_contents($_FILES['backup_file']['tmp_name']);

        try {
            BackupService::restoreFromJson($content);
            Response::redirect('/configuracoes?tab=backup', 'Backup restaurado com sucesso! Foi gerado um backup prévio de segurança antes da restauração.');
        } catch (\Throwable $e) {
            Response::redirect('/configuracoes?tab=backup', null, 'Erro ao restaurar backup: ' . $e->getMessage());
        }
    }
}
