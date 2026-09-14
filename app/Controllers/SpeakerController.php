<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\GameEngineService;
use App\Services\PricingService;
use PDO;

class SpeakerController
{
    public static function getLetterForNumber(int $num): string
    {
        return GameEngineService::getBingoLetter($num);
    }

    public static function generateSpeakerToken(int $roundId, string $date = ''): string
    {
        $secret = trim((string)(getenv('APP_KEY') ?: getenv('SESSION_SECRET') ?: ''));
        if ($secret === '' || strlen($secret) < 24 || $roundId <= 0 || $date === '') {
            return '';
        }
        return hash_hmac('sha256', "speaker:v1:{$roundId}:{$date}", $secret);
    }

    public static function validateSpeakerToken(int $roundId, string $token): bool
    {
        if ($roundId <= 0 || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT d.operation_date FROM rounds r JOIN operation_days d ON d.id=r.operation_day_id WHERE r.id=? LIMIT 1");
            $stmt->execute([$roundId]);
            $date = (string)($stmt->fetchColumn() ?: '');
            if ($date === '') return false;
            $expected = self::generateSpeakerToken($roundId, $date);
            return $expected !== '' && hash_equals($expected, strtolower($token));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(): void
    {
        $pdo = Database::getConnection();
        $round = $this->findRound($pdo, (int)($_GET['id'] ?? $_GET['round_id'] ?? 0));
        if (!$round) {
            View::render('speaker/waiting', ['title'=>'Locutor — Aguardando Rodada'], false);
            return;
        }

        $event = $this->activeEvent($pdo);
        if (!$event) {
            View::render('speaker/waiting', ['title'=>'Locutor — Aguardando Evento'], false);
            return;
        }

        $draw = GameEngineService::getOrCreateActiveDraw((int)$event['id'], (int)$round['id']);
        $intelligence = GameEngineService::getGameIntelligence((int)$draw['id']);
        $calledNumbers = array_map(
            static fn(array $stone): int => (int)$stone['number_value'],
            $intelligence['called_numbers'] ?? []
        );

        // O draw é a fonte canônica; os campos de round abaixo são apenas para compatibilidade visual da tela antiga.
        $round['status'] = $draw['status'];
        $round['last_called_number'] = $draw['last_called_number'];
        $round['last_called_at'] = !empty($intelligence['last_5_called'][0]['called_at']) ? $intelligence['last_5_called'][0]['called_at'] : null;
        $round['called_numbers_json'] = json_encode($calledNumbers);
        $round['draw_id'] = (int)$draw['id'];
        $round['prize_id'] = (int)($draw['prize_id'] ?? 0);

        $pricingRule = PricingService::getRuleForDate((string)$round['operation_date']);
        $singlePrice = (!empty($round['single_price']) && (float)$round['single_price'] > 0) ? (float)$round['single_price'] : (float)$pricingRule['single_price'];
        $bundleQty = (!empty($round['bundle_quantity']) && (int)$round['bundle_quantity'] > 0) ? (int)$round['bundle_quantity'] : (int)$pricingRule['bundle_quantity'];
        $bundlePrice = (!empty($round['bundle_price']) && (float)$round['bundle_price'] > 0) ? (float)$round['bundle_price'] : (float)$pricingRule['bundle_price'];

        $sellers = [];
        try {
            $sellers = $pdo->query("SELECT id,name,nickname FROM sellers WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        View::render('speaker/index', [
            'title'=>"Locutor & Sorteio — Rodada {$round['round_number']}",
            'round'=>$round,
            'draw'=>$draw,
            'intelligence'=>$intelligence,
            'calledNumbers'=>$calledNumbers,
            'sellers'=>$sellers,
            'singlePrice'=>$singlePrice,
            'bundleQty'=>$bundleQty,
            'bundlePrice'=>$bundlePrice,
        ]);
    }

    public function callNumber(): void
    {
        $roundId=(int)($_POST['round_id'] ?? 0);
        $number=(int)($_POST['number'] ?? 0);
        try {
            [$drawId,$eventId]=$this->resolveDrawForRound($roundId);
            $userId=Auth::check() ? Auth::id() : null;
            $result=GameEngineService::callNumber($drawId,$number,$userId);
            AuditService::log('BINGO_NUMBER_CALL','draws',$drawId,null,[
                'round_id'=>$roundId,'event_id'=>$eventId,'number'=>$number,'order'=>$result['call_order'],'source'=>'LOCUTOR'
            ]);
            $intel=$result['intelligence'];
            self::jsonResponse([
                'success'=>true,
                'number'=>$number,
                'letter'=>$result['letter'],
                'called_numbers'=>array_map(static fn(array $s): int => (int)$s['number_value'],$intel['called_numbers'] ?? []),
                'total_called'=>(int)($intel['summary']['total_called'] ?? 0),
                'status'=>$result['requires_homologation'] ? 'CHECKING' : 'IN_PROGRESS',
                'candidate_winners'=>$result['candidate_winners'],
                'requires_homologation'=>$result['requires_homologation'],
                'last_called_at'=>$intel['last_5_called'][0]['called_at'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            self::jsonResponse(['success'=>false,'error'=>$e->getMessage()],409);
        }
    }

    public function undoNumber(): void
    {
        $roundId=(int)($_POST['round_id'] ?? 0);
        try {
            [$drawId]=$this->resolveDrawForRound($roundId);
            $result=GameEngineService::undoLastNumber($drawId,Auth::check()?Auth::id():null);
            $intel=$result['intelligence'];
            $stones=$intel['called_numbers'] ?? [];
            $last=$stones ? $stones[array_key_last($stones)] : null;
            AuditService::log('BINGO_NUMBER_UNDO','draws',$drawId,null,['round_id'=>$roundId,'removed_number'=>$result['removed_number'],'source'=>'LOCUTOR']);
            self::jsonResponse([
                'success'=>true,
                'removed_number'=>$result['removed_number'],
                'called_numbers'=>array_map(static fn(array $s): int => (int)$s['number_value'],$stones),
                'last_called_number'=>$last['number_value'] ?? null,
                'last_letter'=>$last['letter'] ?? '',
                'total_called'=>(int)($intel['summary']['total_called'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            self::jsonResponse(['success'=>false,'error'=>$e->getMessage()],409);
        }
    }

    public function updateStatus(): void
    {
        $roundId=(int)($_POST['round_id'] ?? 0);
        $newStatus=strtoupper(trim((string)($_POST['status'] ?? '')));
        if (!in_array($newStatus,['OPEN','IN_PROGRESS','PAUSED','CHECKING','CLOSED'],true)) {
            self::jsonResponse(['success'=>false,'error'=>'Status inválido.'],422);
        }

        try {
            [$drawId]=$this->resolveDrawForRound($roundId);
            $pdo=Database::getConnection();

            if ($newStatus==='CLOSED') {
                $pending=$pdo->prepare("SELECT COUNT(*) FROM winner_claims WHERE draw_id=? AND status='PENDING'");
                $pending->execute([$drawId]);
                if ((int)$pending->fetchColumn()>0) {
                    self::jsonResponse(['success'=>false,'error'=>'Existe conferência pendente. O resultado deve ser homologado por Master/Admin antes do encerramento.'],409);
                }
                // O locutor/token nunca homologa nem encerra resultado premiado.
                if (!Auth::check() || !Auth::isAdmin()) {
                    self::jsonResponse(['success'=>false,'error'=>'Somente Master/Admin pode encerrar definitivamente o sorteio.'],403);
                }
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE draws SET status=?,finished_at=CASE WHEN ?='CLOSED' THEN CURRENT_TIMESTAMP ELSE finished_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$newStatus,$newStatus,$drawId]);
            $pdo->prepare("UPDATE rounds SET status=?,closed_at=CASE WHEN ?='CLOSED' THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id=?")
                ->execute([$newStatus,$newStatus,$roundId]);
            $pdo->commit();
            AuditService::log('ROUND_STATUS_CHANGE','draws',$drawId,null,['round_id'=>$roundId,'new_status'=>$newStatus,'source'=>'LOCUTOR']);
            self::jsonResponse(['success'=>true,'status'=>$newStatus,'message'=>"Status alterado para {$newStatus}."]);
        } catch (\Throwable $e) {
            self::jsonResponse(['success'=>false,'error'=>$e->getMessage()],409);
        }
    }

    /**
     * Compatibilidade com a tela do locutor: registra somente uma REIVINDICAÇÃO FÍSICA pendente.
     * Nunca homologa nem premia automaticamente.
     */
    public function saveWinner(): void
    {
        $roundId=(int)($_POST['round_id'] ?? 0);
        $prizeIndex=max(1,(int)($_POST['prize_index'] ?? 1));
        $winnerName=trim((string)($_POST['winner_name'] ?? ''));
        $sellerName=trim((string)($_POST['seller_name'] ?? ''));

        try {
            [$drawId,$eventId]=$this->resolveDrawForRound($roundId);
            $pdo=Database::getConnection();
            $prizeStmt=$pdo->prepare('SELECT id FROM prizes WHERE event_id=? AND order_num=? AND active=1 LIMIT 1');
            $prizeStmt->execute([$eventId,$prizeIndex]);
            $prizeId=(int)($prizeStmt->fetchColumn() ?: 0);
            if ($prizeId<=0) {
                // mantém o prêmio atual como fallback seguro
                $prizeId=(int)($pdo->query('SELECT prize_id FROM draws WHERE id='.(int)$drawId)->fetchColumn() ?: 0);
            }
            if ($prizeId<=0) throw new \RuntimeException('Prêmio não localizado.');

            $claim=GameEngineService::registerPhysicalWinner(
                $drawId,$prizeId,Auth::check()?(int)Auth::id():0,
                $winnerName !== '' ? $winnerName : 'PORTADOR DA CARTELA',
                null,null,$sellerName !== '' ? 'Vendedor informado: '.$sellerName : null
            );
            AuditService::log('PHYSICAL_WINNER_CLAIM','draws',$drawId,null,[
                'round_id'=>$roundId,'prize_id'=>$prizeId,'claim_id'=>$claim['claim_id'],'source'=>'LOCUTOR','checked_by'=>Auth::id()
            ]);
            self::jsonResponse([
                'success'=>true,
                'status'=>'CHECKING',
                'message'=>'Ganhador físico registrado para conferência. O resultado aguarda homologação de Master/Admin.',
                'claim_id'=>$claim['claim_id'],
            ]);
        } catch (\Throwable $e) {
            self::jsonResponse(['success'=>false,'error'=>$e->getMessage()],409);
        }
    }

    public function clearNumbers(): void
    {
        // A limpeza destrutiva do histórico não pertence ao token de locutor.
        if (!Auth::check() || !Auth::isAdmin()) {
            self::jsonResponse(['success'=>false,'error'=>'Somente Master/Admin pode reiniciar um sorteio, e apenas quando não houver resultado homologado.'],403);
        }

        $roundId=(int)($_POST['round_id'] ?? 0);
        try {
            [$drawId]=$this->resolveDrawForRound($roundId);
            $pdo=Database::getConnection();
            $hom=$pdo->prepare("SELECT COUNT(*) FROM winner_claims WHERE draw_id=? AND status='HOMOLOGATED'");
            $hom->execute([$drawId]);
            if ((int)$hom->fetchColumn()>0) throw new \RuntimeException('Sorteio com resultado homologado não pode ser limpo.');

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM winner_claims WHERE draw_id=? AND status='PENDING'")->execute([$drawId]);
            $pdo->prepare('DELETE FROM draw_stones WHERE draw_id=?')->execute([$drawId]);
            $pdo->prepare("DELETE FROM ticket_game_state WHERE draw_id=?")->execute([$drawId]);
            $pdo->prepare("UPDATE draws SET status='OPEN',total_numbers_called=0,last_called_number=NULL,last_called_letter=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$drawId]);
            $pdo->prepare("UPDATE rounds SET status='OPEN',called_numbers_json='[]',last_called_number=NULL,last_called_at=NULL WHERE id=?")->execute([$roundId]);
            $pdo->commit();
            AuditService::log('BINGO_NUMBERS_CLEAR','draws',$drawId,null,['round_id'=>$roundId,'cleared_by'=>Auth::id()]);
            self::jsonResponse(['success'=>true,'message'=>'Sorteio reiniciado por administrador.']);
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            self::jsonResponse(['success'=>false,'error'=>$e->getMessage()],409);
        }
    }

    private function resolveDrawForRound(int $roundId): array
    {
        if ($roundId<=0) throw new \InvalidArgumentException('Rodada inválida.');
        $pdo=Database::getConnection();
        $roundStmt=$pdo->prepare('SELECT id FROM rounds WHERE id=? LIMIT 1');
        $roundStmt->execute([$roundId]);
        if (!$roundStmt->fetchColumn()) throw new \RuntimeException('Rodada não encontrada.');
        $event=$this->activeEvent($pdo);
        if (!$event) throw new \RuntimeException('Nenhum evento ativo.');
        $draw=GameEngineService::getOrCreateActiveDraw((int)$event['id'],$roundId);
        return [(int)$draw['id'],(int)$event['id']];
    }

    private function activeEvent(PDO $pdo): ?array
    {
        $stmt=$pdo->query("SELECT * FROM events WHERE status='ACTIVE' ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findRound(PDO $pdo, int $roundId): ?array
    {
        if ($roundId>0) {
            $stmt=$pdo->prepare("SELECT r.*,d.status AS day_status,d.operation_date FROM rounds r JOIN operation_days d ON d.id=r.operation_day_id WHERE r.id=? LIMIT 1");
            $stmt->execute([$roundId]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        $stmt=$pdo->query("SELECT r.*,d.status AS day_status,d.operation_date FROM rounds r JOIN operation_days d ON d.id=r.operation_day_id ORDER BY CASE WHEN d.status='OPEN' AND r.status IN ('IN_PROGRESS','CHECKING','PAUSED','OPEN') THEN 0 ELSE 1 END,d.operation_date DESC,r.round_number DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function jsonResponse(array $data, int $statusCode=200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }
}
