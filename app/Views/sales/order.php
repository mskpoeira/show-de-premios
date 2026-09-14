<?php
use App\Core\Csrf;
use App\Core\View;
use App\Services\TicketService;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido <?= View::e($order['order_code']) ?> — Show de Prêmios</title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <style>
        body{background:#f8fafc;font-family:Arial,sans-serif;color:#1e293b;margin:0;padding:1.5rem 1rem}.order-container{max-width:680px;margin:0 auto}.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,.05);padding:1.75rem;margin-bottom:1.5rem}.status-badge{display:inline-block;padding:.35rem .85rem;border-radius:9999px;font-weight:700;font-size:.85rem}.status-paid{background:#dcfce7;color:#15803d}.status-pending{background:#fef3c7;color:#b45309}.pix-code{background:#f1f5f9;padding:.85rem;border-radius:8px;font-family:monospace;font-size:.8rem;word-break:break-all;border:1px solid #cbd5e1;user-select:all}.btn-copy{background:#0284c7;color:#fff;border:0;padding:.75rem 1.25rem;border-radius:8px;font-weight:600;cursor:pointer;width:100%;margin-top:.5rem}.ticket-item{display:flex;justify-content:space-between;align-items:center;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:.75rem;gap:1rem;flex-wrap:wrap}
    </style>
</head>
<body>
<div class="order-container">
    <div style="text-align:center;margin-bottom:1.5rem;"><h1 style="margin:0;color:#0284c7;">🎉 Show de Prêmios Oficial</h1><p><?= View::e($order['event_name']) ?></p></div>

    <div class="card">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;border-bottom:1px solid #f1f5f9;padding-bottom:.75rem;">
            <div><small>Código do Pedido:</small><strong style="display:block;word-break:break-all;"><?= View::e($order['order_code']) ?></strong></div>
            <div><?php if ($is_paid): ?><span class="status-badge status-paid">✅ PAGAMENTO CONFIRMADO</span><?php else: ?><span class="status-badge status-pending">⏳ AGUARDANDO CONFIRMAÇÃO</span><?php endif; ?></div>
        </div>

        <div style="margin-top:1rem;">
            <p><strong>Comprador:</strong> <?= View::e($order['buyer_name']) ?></p>
            <p><strong>CPF:</strong> <?= View::e(TicketService::maskCpf($order['buyer_cpf'])) ?></p>
            <p><strong>Quantidade:</strong> <?= (int)$order['quantity'] ?> cartela(s)</p>
            <p style="font-size:1.15rem;color:#16a34a;"><strong>Valor Total:</strong> <?= View::money($order['total_amount']) ?></p>
        </div>

        <?php if (!$is_paid): ?>
            <div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:1.25rem;text-align:center;">
                <h3 style="margin-top:0;color:#92400e;">📱 PIX Copia e Cola</h3>
                <p style="font-size:.9rem;color:#78350f;">O QR/PIX é estático e <strong>não confirma automaticamente</strong> o pagamento. As cartelas serão habilitadas somente depois que um operador autorizado registrar a confirmação.</p>
                <div class="pix-code" id="pixPayload"><?= View::e($order['pix_payload'] ?? 'PIX não configurado.') ?></div>
                <button class="btn-copy" onclick="copyPix()">📋 Copiar Código PIX</button>

                <?php if ($isAuthenticated): ?>
                    <form action="/pedido/<?= rawurlencode($order['order_code']) ?>/confirmar" method="POST" style="margin-top:1.5rem;border-top:1px dashed #fde68a;padding-top:1rem;">
                        <?= Csrf::inputField() ?>
                        <button type="submit" style="background:#15803d;color:#fff;border:0;padding:.7rem 1.2rem;border-radius:6px;font-weight:700;cursor:pointer;">💼 Confirmar Pagamento Manualmente</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:1.25rem;text-align:center;"><strong>🎉 Cartelas habilitadas para o evento.</strong></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">🎟️ Cartelas do Pedido</h3>
        <?php if (!$canViewTicketSecrets): ?>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;color:#475569;">Os números, códigos de conferência e tokens das cartelas ficam ocultos até a confirmação do pagamento.</div>
        <?php elseif (empty($tickets)): ?>
            <p>Nenhuma cartela encontrada.</p>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-item">
                    <div><strong style="font-size:1.1rem;color:#0284c7;"><?= View::e($ticket['ticket_number']) ?></strong><div style="font-size:.8rem;color:#64748b;">Código de conferência: <code><?= View::e($ticket['check_code']) ?></code></div></div>
                    <div style="display:flex;gap:.5rem;"><a href="/v/<?= View::e($ticket['secure_token']) ?>" target="_blank">🔍 Validar</a><a href="/cartelas/imprimir/<?= View::e($ticket['secure_token']) ?>" target="_blank">🖨️ Imprimir</a></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
function copyPix(){const el=document.getElementById('pixPayload');if(!el)return;navigator.clipboard.writeText(el.innerText).then(()=>alert('Código PIX copiado.'));}
<?php if (!$is_paid): ?>
setInterval(()=>{fetch('/pedido/<?= rawurlencode($order['order_code']) ?>/status',{cache:'no-store'}).then(r=>r.json()).then(d=>{if(d.is_paid)location.reload();}).catch(()=>{});},5000);
<?php endif; ?>
</script>
</body>
</html>
