<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\BackupService;

class BackupController
{
    public function index(): void
    {
        if (!Auth::isMaster()) Response::redirect('/painel',null,'Acesso restrito ao Administrador Master.');
        $dir=__DIR__.'/../../storage/backups';
        $files=[];
        if (is_dir($dir)) {
            foreach(scandir($dir) ?: [] as $file) {
                if (!str_ends_with($file,'.json')) continue;
                $path=$dir.'/'.$file;
                if (!is_file($path)) continue;
                $files[]=['name'=>$file,'size'=>filesize($path),'date'=>filemtime($path)];
            }
            usort($files,fn(array $a,array $b):int=>$b['date']<=>$a['date']);
        }
        View::render('backup/index',['title'=>'Backups do Sistema — Show de Prêmios','files'=>$files]);
    }

    public function export(): void
    {
        if (!Auth::isMaster()) Response::redirect('/painel',null,'Acesso restrito ao Administrador Master.');
        AuditService::log('BACKUP_EXPORT','system',null,null,['exported_by'=>Auth::id()]);
        BackupService::exportBackupFile();
    }

    public function restore(): void
    {
        if (!Auth::isMaster()) Response::redirect('/painel',null,'Acesso restrito ao Administrador Master.');
        if (($_POST['confirm_restore'] ?? '')!=='RESTaurar'.' AGORA') {
            Response::redirect('/configuracoes?tab=backup',null,'Confirmação de restauração inválida. Digite exatamente: RESTaurar AGORA');
        }
        if (empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
            Response::redirect('/configuracoes?tab=backup',null,'Selecione um arquivo JSON de backup válido.');
        }
        if ((int)($_FILES['backup_file']['size'] ?? 0)>50*1024*1024) {
            Response::redirect('/configuracoes?tab=backup',null,'O arquivo excede o limite de 50 MB.');
        }
        $name=strtolower((string)($_FILES['backup_file']['name'] ?? ''));
        if (!str_ends_with($name,'.json')) {
            Response::redirect('/configuracoes?tab=backup',null,'A restauração aceita somente arquivo .json.');
        }

        $content=file_get_contents((string)$_FILES['backup_file']['tmp_name']);
        if ($content===false) Response::redirect('/configuracoes?tab=backup',null,'Não foi possível ler o arquivo enviado.');

        try {
            BackupService::restoreFromJson($content);
            Response::redirect('/configuracoes?tab=backup','Backup restaurado com sucesso. Um snapshot prévio foi preservado.');
        } catch (\Throwable $e) {
            error_log('[Backup restore] '.$e->getMessage());
            Response::redirect('/configuracoes?tab=backup',null,'A restauração falhou e foi interrompida com segurança. Consulte os logs administrativos.');
        }
    }
}
