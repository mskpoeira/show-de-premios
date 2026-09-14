<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\GameEngineService;
use PDO;

class DrawController
{
    public function index(): void
    {
        $pdo = Database::getConnection();
        $stmtEvent = $pdo->query("SELECT * FROM events WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1");
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            View::render('draw/no_event', []);
            return;
        }

        $draw = GameEngineService::getOrCreateActiveDraw((int)$event['id']);
        $stmtPrizes = $pdo->prepare('SELECT * FROM prizes WHERE event_id=? AND active=1 ORDER BY order_num');
        $stmtPrizes->execute([(int)$event['id']]);

        View::render('draw/index', [
            'event'=>$event,
            'draw'=>$draw,
            'intelligence'=>GameEngineService::getGameIntelligence((int)$draw['id']),
            'prizes'=>$stmtPrizes->fetchAll(PDO::FETCH_ASSOC),
            'user'=>Auth::user(),
        ]);
    }

    public function call(): void
    {
        $this->jsonHeader();
        $user = Auth::user();
        if (!$user || !Auth::canOperateDraw()) $this->json(['success'=>false,'message'=>'Não autorizado.'],403);

        $number=(int)($_POST['number'] ?? 0);
        $drawId=(int)($_POST['draw_id'] ?? 0);
        try {
            $result=GameEngineService::callNumber($drawId,$number,(int)$user['id']);
            AuditService::log('BINGO_NUMBER_CALL','draws',$drawId,null,['number'=>$number,'letter'=>$result['letter'],'order'=>$result['call_order']]);
            $this->json([
                'success'=>true,
                'call'=>['number'=>$number,'letter'=>$result['letter'],'order'=>$result['call_order']],
                'candidate_winners'=>$result['candidate_winners'],
                'requires_homologation'=>$result['requires_homologation'],
                'intelligence'=>$result['intelligence'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],409);
        }
    }

    public function undo(): void
    {
        $this->jsonHeader();
        $user=Auth::user();
        $drawId=(int)($_POST['draw_id'] ?? 0);
        try {
            $result=GameEngineService::undoLastNumber($drawId,$user ? (int)$user['id'] : null);
            AuditService::log('BINGO_NUMBER_UNDO','draws',$drawId,null,['removed_number'=>$result['removed_number'],'user_id'=>$user['id'] ?? null]);
            $this->json(['success'=>true]+$result);
        } catch (\Throwable $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],409);
        }
    }

    public function intelligenceJson(): void
    {
        $this->jsonHeader();
        $drawId=(int)($_GET['draw_id'] ?? 0);
        if ($drawId<=0) {
            $pdo=Database::getConnection();
            $eventId=(int)($pdo->query("SELECT id FROM events WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
            if ($eventId>0) $drawId=(int)GameEngineService::getOrCreateActiveDraw($eventId)['id'];
        }
        if ($drawId<=0) $this->json(['error'=>'Nenhum sorteio ativo.'],404);
        $this->json(GameEngineService::getGameIntelligence($drawId));
    }

    public function winnerContact(): void
    {
        $this->jsonHeader();
        $user=Auth::user();
        if (!$user) $this->json(['success'=>false],403);
        $buyerId=(int)($_POST['buyer_id'] ?? 0);
        $ticketId=(int)($_POST['ticket_id'] ?? 0);
        $type=strtoupper(trim((string)($_POST['type'] ?? 'WHATSAPP')));
        $action=$type==='CALL'?'TICKET_CALL_WINNER':'TICKET_WHATSAPP_WINNER';
        AuditService::logBuyerAccess((int)$user['id'],(string)$user['name'],(string)$user['role'],$buyerId?:null,$ticketId?:null,$action,'PANEL','SUCCESS');
        $this->json(['success'=>true,'logged'=>true,'action'=>$action]);
    }

    public function changePrize(): void
    {
        $this->jsonHeader();
        $drawId=(int)($_POST['draw_id'] ?? 0);
        $prizeId=(int)($_POST['prize_id'] ?? 0);
        if ($drawId<=0 || $prizeId<=0) $this->json(['success'=>false,'message'=>'Sorteio ou prêmio inválido.'],422);

        $pdo=Database::getConnection();
        $pending=$pdo->prepare("SELECT COUNT(*) FROM winner_claims WHERE draw_id=? AND status='PENDING'");
        $pending->execute([$drawId]);
        if ((int)$pending->fetchColumn()>0) $this->json(['success'=>false,'message'=>'Homologue ou descarte a conferência pendente antes de trocar o prêmio.'],409);

        $valid=$pdo->prepare('SELECT COUNT(*) FROM prizes p JOIN draws d ON d.event_id=p.event_id WHERE d.id=? AND p.id=? AND p.active=1');
        $valid->execute([$drawId,$prizeId]);
        if ((int)$valid->fetchColumn()===0) $this->json(['success'=>false,'message'=>'Prêmio não pertence ao evento ativo.'],422);

        $pdo->prepare("UPDATE draws SET prize_id=?,status='IN_PROGRESS',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$prizeId,$drawId]);
        AuditService::log('DRAW_PRIZE_CHANGE','draws',$drawId,null,['prize_id'=>$prizeId]);
        $this->json(['success'=>true,'intelligence'=>GameEngineService::getGameIntelligence($drawId)]);
    }

    public function registerPhysicalWinner(): void
    {
        $this->jsonHeader();
        $user=Auth::user();
        if (!$user || !Auth::canOperateDraw()) $this->json(['success'=>false,'message'=>'Não autorizado.'],403);

        $drawId=(int)($_POST['draw_id'] ?? 0);
        $prizeId=(int)($_POST['prize_id'] ?? 0);
        $name=trim((string)($_POST['name'] ?? ''));
        $phone=trim((string)($_POST['phone'] ?? ''));
        $cpf=preg_replace('/\D/','',(string)($_POST['cpf'] ?? ''));
        $note=trim((string)($_POST['note'] ?? ''));

        try {
            $result=GameEngineService::registerPhysicalWinner($drawId,$prizeId,(int)$user['id'],$name ?: 'PORTADOR DA CARTELA',$phone ?: null,$cpf ?: null,$note ?: null);
            AuditService::log('PHYSICAL_WINNER_CLAIM','draws',$drawId,null,['claim_id'=>$result['claim_id'],'prize_id'=>$prizeId,'call_order'=>$result['call_order'],'checked_by'=>(int)$user['id']]);
            $this->json(['success'=>true,'message'=>'Ganhador de cartela física conferido manualmente e aguardando homologação.']+$result);
        } catch (\Throwable $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],409);
        }
    }

    public function homologate(): void
    {
        $this->jsonHeader();
        $user=Auth::user();
        if (!$user || !Auth::isAdmin()) $this->json(['success'=>false,'message'=>'Somente Master/Admin pode homologar o resultado.'],403);
        $drawId=(int)($_POST['draw_id'] ?? 0);
        $continueNext=!empty($_POST['continue_next']);
        try {
            $result=GameEngineService::homologate($drawId,(int)$user['id'],$continueNext);
            AuditService::log('DRAW_RESULT_HOMOLOGATED','draws',$drawId,null,$result+['homologated_by'=>(int)$user['id']]);
            $this->json(['success'=>true,'message'=>'Resultado homologado oficialmente.']+$result);
        } catch (\Throwable $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],409);
        }
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    private function json(array $data, int $status=200): never
    {
        http_response_code($status);
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }
}
