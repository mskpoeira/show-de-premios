<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Services\TicketService;
$csrfToken = Csrf::getToken();
?>

<div class="content-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div><h1 style="margin:0;font-size:1.75rem;color:#0f172a;font-weight:800;">🎟️ Gerenciamento de Cartelas Digitais</h1><p style="margin:.25rem 0 0;color:#64748b;">Inventário digital, lotes, validação e segunda via.</p></div>
    <?php if (Auth::isAdmin()): ?><button onclick="openBatchModal()" class="btn btn-primary">➕ Gerar Lote Digital</button><?php endif; ?>
</div>

<div class="card" style="background:#fff;padding:1.25rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.5rem;">
<form action="/cartelas" method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
    <div><label>Buscar:</label><input type="text" name="search" value="<?= View::e($search) ?>" class="form-control" placeholder="Cartela, comprador, CPF ou código"></div>
    <div><label>Situação:</label><select name="status" class="form-control"><option value="">Todas</option><?php foreach(['VALID'=>'Válida','AWARDED'=>'Premiada','RESERVED'=>'Reservada','PENDING'=>'Pendente','CANCELLED'=>'Cancelada','INVALID'=>'Invalidada'] as $value=>$label): ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div><label>Lote:</label><select name="batch_id" class="form-control"><option value="0">Todos</option><?php foreach($batches as $batch): ?><option value="<?= (int)$batch['id'] ?>" <?= $batchId===(int)$batch['id']?'selected':'' ?>><?= View::e($batch['batch_code']) ?> (<?= View::e($batch['prefix']) ?>)</option><?php endforeach; ?></select></div>
    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
</form>
</div>

<div class="card" style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;overflow:auto;">
<table style="width:100%;border-collapse:collapse;font-size:.9rem;">
<thead><tr style="background:#f1f5f9;"><th>Cartela</th><th>Código</th><th>Lote</th><th>Comprador</th><th>Telefone</th><th>Status</th><th>Impressões</th><th>Ações</th></tr></thead>
<tbody>
<?php if(empty($tickets)): ?><tr><td colspan="8" style="padding:2rem;text-align:center;">Nenhuma cartela encontrada.</td></tr><?php endif; ?>
<?php foreach($tickets as $ticket): ?>
<tr style="border-bottom:1px solid #f1f5f9;">
    <td style="padding:.75rem;font-weight:700;color:#0284c7;"><?= View::e($ticket['ticket_number']) ?></td>
    <td><code><?= View::e($ticket['check_code']) ?></code></td><td><?= View::e($ticket['batch_code']) ?></td><td><?= View::e($ticket['buyer_name'] ?: 'Sem comprador') ?></td><td><?= View::e(TicketService::maskPhone($ticket['buyer_phone'] ?? null)) ?></td><td><?= View::e($ticket['status']) ?></td><td><?= (int)$ticket['print_count'] ?>x</td>
    <td style="white-space:nowrap;"><a href="/cartelas/imprimir/<?= View::e($ticket['secure_token']) ?>" target="_blank">🖨️</a> <a href="/v/<?= View::e($ticket['secure_token']) ?>" target="_blank">🔍</a><?php if(Auth::isOperator() && $ticket['status']==='VALID'): ?> <button type="button" onclick="invalidateTicket(<?= (int)$ticket['id'] ?>,<?= json_encode($ticket['ticket_number']) ?>)" style="border:0;background:none;color:#dc2626;cursor:pointer;">🚫 Invalidar</button><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>

<?php if($totalPages>1): ?><div style="padding:1rem;text-align:center;">Página <?= (int)$page ?> de <?= (int)$totalPages ?></div><?php endif; ?>

<script>
const ticketCsrf = <?= json_encode($csrfToken) ?>;
function invalidateTicket(id, number){const reason=prompt('Motivo da invalidação da cartela '+number+':');if(!reason)return;const body=new URLSearchParams({ticket_id:String(id),reason,_csrf_token:ticketCsrf});fetch('/cartelas/invalidar',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token':ticketCsrf},body}).then(r=>r.json()).then(d=>{alert(d.message||'Operação concluída.');if(d.success)location.reload();});}
function openBatchModal(){const qty=prompt('Quantidade de cartelas digitais a gerar:','100');if(!qty)return;const batchId=<?= json_encode((int)($batches[0]['id'] ?? 0)) ?>;if(!batchId){alert('Nenhum lote disponível.');return;}const body=new URLSearchParams({batch_id:String(batchId),quantity:String(qty),_csrf_token:ticketCsrf});fetch('/cartelas/gerar-lote',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token':ticketCsrf},body}).then(r=>r.json()).then(d=>{alert(d.message||'Operação concluída.');if(d.success)location.reload();});}
</script>
