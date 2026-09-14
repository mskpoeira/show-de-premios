<?php
use App\Core\View;

$grid=[];
foreach($ticket['numbers'] as $cell){$grid[(int)$cell['row_index']][(int)$cell['col_index']]=$cell;}
$validationUrl='https://showdepremios.mskpoeira.com.br/v/'.rawurlencode((string)$ticket['secure_token']);
$qrSrc='';
try{
    if(class_exists(\chillerlan\QRCode\QRCode::class)){
        $rendered=(new \chillerlan\QRCode\QRCode())->render($validationUrl);
        if(str_starts_with($rendered,'data:'))$qrSrc=$rendered;
        elseif(str_contains($rendered,'<svg'))$qrSrc='data:image/svg+xml;base64,'.base64_encode($rendered);
    }
}catch(\Throwable $e){error_log('[Local QR] '.$e->getMessage());}
$nameParts=preg_split('/\s+/',trim((string)($ticket['buyer_name'] ?? ''))) ?: [];
$maskedName='Portador';
if($nameParts){$first=array_shift($nameParts);$maskedName=$first;foreach($nameParts as $part)$maskedName.=' '.mb_substr($part,0,1).'***';}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Cartela <?= View::e($ticket['ticket_number']) ?> — Impressão Oficial</title><style>@page{size:A4 portrait;margin:10mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#0f172a;margin:0;background:#fff}.toolbar{background:#0f172a;color:#fff;padding:10px;text-align:center;position:sticky;top:0;z-index:10}.toolbar button{padding:8px 14px;border:0;border-radius:6px;font-weight:700;cursor:pointer;margin:0 4px}.page{padding:8px;display:flex;flex-direction:column;gap:8mm}.ticket{border:2px solid #0284c7;border-radius:10px;padding:10px 14px;page-break-inside:avoid}.head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #0284c7;padding-bottom:7px;margin-bottom:8px}.event{font-weight:900;color:#0284c7}.number{font-size:20px;font-weight:900}.body{display:grid;grid-template-columns:1fr 150px;gap:12px}.bingo{border-collapse:collapse;width:100%}.bingo th{background:#0284c7;color:#fff;padding:5px}.bingo td{border:1px solid #cbd5e1;text-align:center;font-size:18px;font-weight:800;padding:7px}.center{background:#f0fdf4;color:#15803d;font-size:10px!important}.side{border-left:1px dashed #94a3b8;padding-left:10px;font-size:9px;display:flex;flex-direction:column;justify-content:space-between;gap:7px}.qr{width:112px;height:112px;margin:auto;display:flex;align-items:center;justify-content:center;border:1px solid #cbd5e1}.qr img{width:108px;height:108px}.url{font-size:7px;word-break:break-all;color:#64748b}.cut{border-top:1px dashed #94a3b8;text-align:center;font-size:8px;color:#64748b;padding-top:2px}@media print{.toolbar{display:none}.page{padding:0}}</style></head><body>
<div class="toolbar">🖨️ Impressão oficial <button onclick="window.print()">Imprimir</button><button onclick="window.close()">Fechar</button></div><div class="page">
<?php for($copy=0;$copy<$layout;$copy++): if($copy>0):?><div class="cut">✂ Linha de corte</div><?php endif;?>
<div class="ticket"><div class="head"><div><div class="event"><?= View::e($ticket['event_name']) ?></div><small><?= View::date($ticket['event_date']) ?> • <?= View::e($ticket['event_location']) ?></small></div><div style="text-align:right"><div class="number"><?= View::e($ticket['ticket_number']) ?></div><small>Código: <strong><?= View::e($ticket['check_code']) ?></strong></small></div></div><div class="body"><table class="bingo"><thead><tr><th>B</th><th>I</th><th>N</th><th>G</th><th>O</th></tr></thead><tbody><?php for($r=1;$r<=5;$r++):?><tr><?php for($c=1;$c<=5;$c++):$cell=$grid[$r][$c]??null;$center=$cell&&(int)$cell['is_center']===1;?><td class="<?= $center?'center':'' ?>"><?= $center?'★ LIVRE':View::e($cell['number_value']??'') ?></td><?php endfor;?></tr><?php endfor;?></tbody></table><div class="side"><div><div class="qr"><?php if($qrSrc!==''):?><img src="<?= View::e($qrSrc) ?>" alt="QR de validação"><?php else:?><span>QR indisponível</span><?php endif;?></div><div style="text-align:center">Validação oficial local</div><div class="url"><?= View::e($validationUrl) ?></div></div><div><strong>PRÊMIOS</strong><?php foreach($ticket['prizes'] as $prize):?><div>• <?= View::e($prize['title']) ?> — <?= View::money($prize['value']) ?></div><?php endforeach;?></div><div><div>Comprador: <?= View::e($maskedName) ?></div><div>CPF: <?= View::e($masked_cpf) ?></div><div>Via: <?= (int)$ticket['print_count'] ?></div></div></div></div></div>
<?php endfor;?></div></body></html>
