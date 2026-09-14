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
        $tab=trim((string)($_GET['tab'] ?? 'gerais'));
        if (!in_array($tab,['gerais','planejamento','vendedores','operadores','backup'],true)) $tab='gerais';
        if ($tab==='backup' && !Auth::isMaster()) $tab='gerais';

        $pdo=Database::getConnection();
        $currentPricing=PricingService::getRuleForDate();
        $pricingHistory=$pdo->query('SELECT * FROM pricing_rules ORDER BY effective_from DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $settings=$pdo->query('SELECT key,value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $event=$pdo->query("SELECT * FROM events WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
        $batches=[];$prizes=[];
        if ($event) {
            $stmt=$pdo->prepare('SELECT * FROM event_batches WHERE event_id=? ORDER BY id');$stmt->execute([(int)$event['id']]);$batches=$stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt=$pdo->prepare('SELECT * FROM prizes WHERE event_id=? ORDER BY order_num,id');$stmt->execute([(int)$event['id']]);$prizes=$stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sellers=$pdo->query("SELECT s.*,COALESCE(SUM(CASE WHEN sa.cancelled_at IS NULL THEN sa.quantity ELSE 0 END),0) AS total_qty,COALESCE(SUM(CASE WHEN sa.cancelled_at IS NULL THEN sa.amount ELSE 0 END),0) AS total_sales FROM sellers s LEFT JOIN sales sa ON sa.seller_id=s.id GROUP BY s.id ORDER BY s.active DESC,s.name")->fetchAll(PDO::FETCH_ASSOC);

        $operators=[];
        if (Auth::canManageUsers()) {
            $sql=Auth::isMaster()
                ? "SELECT id,name,login,role,active,created_at,updated_at FROM users ORDER BY CASE WHEN UPPER(role)='MASTER' THEN 0 ELSE 1 END,active DESC,name"
                : "SELECT id,name,login,role,active,created_at,updated_at FROM users WHERE UPPER(role)<>'MASTER' ORDER BY active DESC,name";
            $operators=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $backupFiles=[];
        if (Auth::isMaster()) {
            $dir=__DIR__.'/../../storage/backups';
            if (is_dir($dir)) {
                foreach(scandir($dir) ?: [] as $file) {
                    $path=$dir.'/'.$file;
                    if (str_ends_with($file,'.json') && is_file($path)) $backupFiles[]=['name'=>$file,'size'=>filesize($path),'date'=>filemtime($path)];
                }
                usort($backupFiles,fn(array $a,array $b):int=>$b['date']<=>$a['date']);
            }
        }

        $tableCounts=[];
        if (Auth::isMaster()) {
            foreach(['sales','rounds','cash_movements','cash_closings','operation_days','sellers','audit_logs','orders','tickets','buyers'] as $table) {
                try{$tableCounts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();}catch(\Throwable $e){$tableCounts[$table]=0;}
            }
            $tableCounts['cash']=$tableCounts['cash_movements']+$tableCounts['cash_closings'];
            $tableCounts['digital_orders']=$tableCounts['orders'];
            $totalPricing=(int)$pdo->query('SELECT COUNT(*) FROM pricing_rules')->fetchColumn();
            $tableCounts['pricing_history']=max(0,$totalPricing-1);
            $currentId=(int)Auth::id();
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM users WHERE id<>? AND UPPER(role)<>'MASTER'");$stmt->execute([$currentId]);$tableCounts['operators']=(int)$stmt->fetchColumn();
        }

        View::render('settings/index',compact('tab','event','batches','prizes','currentPricing','pricingHistory','settings','sellers','operators','backupFiles','tableCounts'));
    }

    public function updatePricing(): void
    {
        $single=$this->money($_POST['single_price'] ?? '2.00');
        $bundleQty=(int)($_POST['bundle_quantity'] ?? 3);
        $bundle=$this->money($_POST['bundle_price'] ?? '5.00');
        $effective=trim((string)($_POST['effective_from'] ?? date('Y-m-d')));
        if ($single<=0 || $bundle<=0 || $bundleQty<2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$effective)) Response::redirect('/configuracoes?tab=gerais',null,'Regra de preços inválida.');

        $pdo=Database::getConnection();$pdo->beginTransaction();
        try {
            $dayBefore=(new \DateTimeImmutable($effective))->modify('-1 day')->format('Y-m-d');
            $pdo->prepare('UPDATE pricing_rules SET effective_to=? WHERE effective_to IS NULL AND effective_from<?')->execute([$dayBefore,$effective]);
            $stmt=$pdo->prepare('INSERT INTO pricing_rules (effective_from,single_quantity,single_price,bundle_quantity,bundle_price,active) VALUES (?,1,?,?,?,1)');
            $stmt->execute([$effective,$single,$bundleQty,$bundle]);$id=(int)$pdo->lastInsertId();
            $pdo->commit();
            AuditService::log('PRICING_UPDATE','pricing_rules',$id,null,['effective_from'=>$effective,'single_price'=>$single,'bundle_quantity'=>$bundleQty,'bundle_price'=>$bundle]);
            Response::redirect('/configuracoes?tab=gerais','Nova regra de preços criada; vendas anteriores permanecem com seu snapshot.');
        } catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[Pricing] '.$e->getMessage());Response::redirect('/configuracoes?tab=gerais',null,'Não foi possível atualizar os preços.');}
    }

    public function updateParameters(): void
    {
        $updates=[
            'prize_pool_percent'=>(string)(float)($_POST['prize_pool_percent'] ?? 50),
            'prize_1_percent'=>(string)(float)($_POST['prize_1_percent'] ?? 65),
            'prize_2_percent'=>(string)(float)($_POST['prize_2_percent'] ?? 35),
            'prize_rounding'=>(string)$this->money($_POST['prize_rounding'] ?? '10'),
            'cash_tolerance'=>(string)$this->money($_POST['cash_tolerance'] ?? '0.01'),
            'carry_previous_prize_suggestion'=>isset($_POST['carry_previous_prize_suggestion'])?'true':'false',
        ];
        $title=trim((string)($_POST['system_title'] ?? ''));if($title!=='')$updates['system_title']=$title;
        $this->saveSettings($updates);
        AuditService::log('SETTINGS_UPDATE','settings',null,null,array_keys($updates));
        Response::redirect('/configuracoes?tab=gerais','Parâmetros atualizados com sucesso.');
    }

    public function updatePix(): void
    {
        $updates=[
            'pix_key'=>trim((string)($_POST['pix_key'] ?? '')),
            'pix_key_type'=>trim((string)($_POST['pix_key_type'] ?? 'CHAVE_ALEATORIA')),
            'pix_receiver_name'=>trim((string)($_POST['pix_receiver_name'] ?? '')),
            'pix_receiver_city'=>trim((string)($_POST['pix_receiver_city'] ?? 'Ubatuba')),
            'pix_description'=>trim((string)($_POST['pix_description'] ?? 'Show de Prêmios')),
            'pix_banner_title'=>trim((string)($_POST['pix_banner_title'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR')) ?: 'PAGUE COM PIX DIRETO DO SEU LUGAR',
            'pix_show_on_telao'=>isset($_POST['pix_show_on_telao'])?'true':'false',
        ];
        $this->saveSettings($updates);
        AuditService::log('PIX_SETTINGS_UPDATE','settings',null,null,['updated_by'=>Auth::id(),'key_type'=>$updates['pix_key_type'],'show_on_telao'=>$updates['pix_show_on_telao']]);
        Response::redirect('/configuracoes?tab=gerais','Configurações do PIX atualizadas.');
    }

    public function deletePricing(): void
    {
        if (!Auth::isMaster()) Response::redirect('/configuracoes?tab=gerais',null,'Somente Master pode excluir regras de preços.');
        $id=(int)($_POST['rule_id'] ?? 0);if($id<=0)Response::redirect('/configuracoes?tab=gerais',null,'Regra inválida.');
        $pdo=Database::getConnection();
        if((int)$pdo->query('SELECT COUNT(*) FROM pricing_rules')->fetchColumn()<=1)Response::redirect('/configuracoes?tab=gerais',null,'Deve existir ao menos uma regra de preços.');
        $stmt=$pdo->prepare('SELECT * FROM pricing_rules WHERE id=?');$stmt->execute([$id]);$rule=$stmt->fetch(PDO::FETCH_ASSOC);if(!$rule)Response::redirect('/configuracoes?tab=gerais',null,'Regra não encontrada.');
        $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM pricing_rules WHERE id=?')->execute([$id]);if(empty($rule['effective_to'])){$latest=(int)$pdo->query('SELECT id FROM pricing_rules ORDER BY effective_from DESC,id DESC LIMIT 1')->fetchColumn();if($latest)$pdo->exec("UPDATE pricing_rules SET effective_to=NULL,active=1 WHERE id={$latest}");}$pdo->commit();AuditService::log('PRICING_RULE_DELETED','pricing_rules',$id,$rule,['deleted_by'=>Auth::id()]);Response::redirect('/configuracoes?tab=gerais','Regra excluída com sucesso.');}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Response::redirect('/configuracoes?tab=gerais',null,'Falha ao excluir a regra.');}
    }

    public function cleanDatabase(): void
    {
        if (!Auth::isMaster()) Response::redirect('/configuracoes?tab=gerais',null,'Somente Master pode limpar dados.');
        $items=$_POST['clean_items'] ?? [];if(!is_array($items)||!$items)Response::redirect('/configuracoes?tab=backup',null,'Selecione ao menos um conjunto de dados.');
        try{$result=\App\Services\BackupService::cleanDatabaseSelective(array_fill_keys($items,true));$total=array_sum(array_map('intval',$result['cleared']??[]));Response::redirect('/configuracoes?tab=backup',"Limpeza seletiva concluída ({$total} registro(s)). Snapshot preventivo preservado.");}catch(\Throwable $e){error_log('[Clean DB] '.$e->getMessage());Response::redirect('/configuracoes?tab=backup',null,'A limpeza foi interrompida com segurança.');}
    }

    public function updatePlanning(): void
    {
        if (!Auth::isAdmin()) Response::redirect('/configuracoes',null,'Acesso não autorizado.');
        $pdo=Database::getConnection();
        $eventId=(int)($_POST['event_id'] ?? 0);
        $name=trim((string)($_POST['name'] ?? ''));
        $date=trim((string)($_POST['event_date'] ?? ''));
        $time=trim((string)($_POST['event_time'] ?? '20:00'));
        $location=trim((string)($_POST['location'] ?? 'Ubatuba/SP'));
        $prefix=strtoupper(trim((string)($_POST['ticket_prefix'] ?? 'JDA')));
        $modality=strtoupper(trim((string)($_POST['modality'] ?? 'HYBRID')));
        $single=(float)($_POST['single_price'] ?? 2.00);
        $bundleQty=(int)($_POST['bundle_qty'] ?? 3);
        $bundle=(float)($_POST['bundle_price'] ?? 5.00);
        $tieRule=strtoupper(trim((string)($_POST['tie_rule'] ?? 'SPLIT')));

        if($eventId<=0||$name===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^\d{2}:\d{2}$/',$time))Response::redirect('/configuracoes?tab=planejamento',null,'Dados do evento inválidos.');
        if(!preg_match('/^[A-Z]{3}$/',$prefix))Response::redirect('/configuracoes?tab=planejamento',null,'O prefixo deve conter exatamente 3 letras.');
        if(!in_array($modality,['DIGITAL_ONLY','PHYSICAL_ONLY','HYBRID'],true))$modality='HYBRID';
        if($single<=0||$bundle<=0||$bundleQty<2)Response::redirect('/configuracoes?tab=planejamento',null,'Preços inválidos.');
        if(!in_array($tieRule,['SPLIT','SHARE','MANUAL'],true))$tieRule='SPLIT';

        $lock=$pdo->prepare('SELECT COALESCE(SUM(total_generated),0) FROM event_batches WHERE event_id=?');$lock->execute([$eventId]);$generated=(int)$lock->fetchColumn();
        if($generated>0){$old=$pdo->prepare('SELECT ticket_prefix FROM events WHERE id=?');$old->execute([$eventId]);$prefix=(string)($old->fetchColumn()?:$prefix);}

        $stmt=$pdo->prepare('UPDATE events SET name=?,event_date=?,event_time=?,location=?,modality=?,ticket_prefix=?,single_price=?,bundle_qty=?,bundle_price=?,tie_rule=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $stmt->execute([$name,$date,$time,$location,$modality,$prefix,$single,$bundleQty,$bundle,$tieRule,$eventId]);
        AuditService::log('EVENT_SETTINGS_UPDATE','events',$eventId,null,['name'=>$name,'date'=>$date,'modality'=>$modality,'prefix'=>$prefix,'prefix_locked'=>$generated>0,'prices'=>[$single,$bundleQty,$bundle]]);
        Response::redirect('/configuracoes?tab=planejamento','Planejamento atualizado com sucesso.'.($generated>0?' O prefixo permaneceu bloqueado após emissão de cartelas.':''));
    }

    private function saveSettings(array $updates): void
    {
        $pdo=Database::getConnection();$check=$pdo->prepare('SELECT COUNT(*) FROM settings WHERE key=?');$update=$pdo->prepare('UPDATE settings SET value=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE key=?');$insert=$pdo->prepare("INSERT INTO settings (key,value,type,updated_by,updated_at) VALUES (?,?,'string',?,CURRENT_TIMESTAMP)");
        foreach($updates as $key=>$value){$check->execute([$key]);if((int)$check->fetchColumn()>0)$update->execute([(string)$value,Auth::id(),$key]);else$insert->execute([$key,(string)$value,Auth::id()]);}
    }

    private function money(mixed $raw): float
    {
        $s=trim((string)$raw);$s=str_replace(['R$',' '],'',$s);if(str_contains($s,',')){$s=str_replace('.','',$s);$s=str_replace(',','.',$s);}return round((float)preg_replace('/[^0-9.-]/','',$s),2);
    }
}
