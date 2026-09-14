<?php
use App\Core\Csrf;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Cartelas — <?= View::e($event['name']) ?></title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <style>
        body{background:#f8fafc;font-family:Arial,sans-serif;color:#1e293b;margin:0}.checkout-header{background:linear-gradient(135deg,#0284c7,#0f766e);color:#fff;padding:2.5rem 1rem;text-align:center;border-bottom:4px solid #38bdf8}.checkout-container{max-width:640px;margin:-2rem auto 3rem;padding:0 1rem}.card-box{background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);padding:2rem;margin-bottom:1.5rem;border:1px solid #e2e8f0}.qty-selector{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin:1.5rem 0}.qty-btn{background:#f1f5f9;border:2px solid #cbd5e1;border-radius:8px;padding:1rem .5rem;text-align:center;cursor:pointer;font-weight:700}.qty-btn.active{border-color:#0284c7;background:#0284c7;color:#fff}.form-group{margin-bottom:1.25rem}.form-label{display:block;font-size:.875rem;font-weight:600;margin-bottom:.35rem}.form-control{width:100%;padding:.75rem 1rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;box-sizing:border-box}.btn-submit{width:100%;background:#16a34a;color:#fff;border:0;border-radius:8px;padding:1rem;font-size:1.1rem;font-weight:700;cursor:pointer}.price-display{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:1rem;border-radius:8px;margin-bottom:1.5rem;text-align:center;font-size:1.25rem;font-weight:800}
    </style>
</head>
<body>
<header class="checkout-header">
    <h1 style="margin:0 0 .5rem;font-size:1.75rem;">🎉 <?= View::e($event['name']) ?></h1>
    <p style="margin:0;">📅 <?= View::date($event['event_date']) ?> às <?= View::e($event['event_time']) ?> &bull; 📍 <?= View::e($event['location']) ?></p>
</header>

<div class="checkout-container">
    <?php if (!empty($error)): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:1rem;border-radius:8px;margin-bottom:1.5rem;font-weight:600;">⚠️ <?= View::e($error) ?></div>
    <?php endif; ?>

    <div class="card-box" style="margin-top:1.5rem;">
        <h3 style="margin-top:0;">🏆 Premiação Oficial</h3>
        <ul style="list-style:none;padding:0;margin:0;">
            <?php foreach ($prizes as $prize): ?>
                <li style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px dashed #e2e8f0;">
                    <span><?= View::e($prize['title']) ?></span><strong style="color:#16a34a;"><?= View::money($prize['value']) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card-box">
        <h3 style="margin-top:0;">🎟️ Escolha a quantidade de cartelas digitais</h3>
        <form action="/comprar" method="POST" id="checkoutForm">
            <?= Csrf::inputField() ?>
            <input type="hidden" name="quantity" id="inputQuantity" value="1">

            <div class="qty-selector">
                <button type="button" class="qty-btn active" onclick="setQty(this,1)">1 Cartela</button>
                <button type="button" class="qty-btn" onclick="setQty(this,3)">3 Cartelas<br><small>PROMOÇÃO</small></button>
                <button type="button" class="qty-btn" onclick="setQty(this,6)">6 Cartelas</button>
                <button type="button" class="qty-btn" onclick="setQty(this,10)">10 Cartelas</button>
            </div>

            <div class="price-display" id="priceDisplay">Total: <?= View::money($event['single_price']) ?></div>

            <div class="form-group"><label class="form-label" for="name">Nome Completo *</label><input type="text" id="name" name="name" class="form-control" required autocomplete="name"></div>
            <div class="form-group"><label class="form-label" for="cpf">CPF *</label><input type="text" id="cpf" name="cpf" class="form-control" required maxlength="14" inputmode="numeric"></div>
            <div class="form-group"><label class="form-label" for="phone">Telefone / WhatsApp *</label><input type="tel" id="phone" name="phone" class="form-control" required autocomplete="tel"></div>
            <div class="form-group"><label class="form-label" for="email">E-mail (opcional)</label><input type="email" id="email" name="email" class="form-control" autocomplete="email"></div>
            <div class="form-group"><label><input type="checkbox" name="wants_email" value="1" checked> Enviar cartelas por e-mail após confirmação do pagamento</label></div>

            <button type="submit" class="btn-submit">🚀 Continuar para Pagamento PIX</button>
        </form>
    </div>

    <div style="text-align:center;color:#64748b;font-size:.85rem;">🔒 O QR PIX é estático. A liberação das cartelas ocorre somente após confirmação registrada no sistema.</div>
</div>

<script>
const singlePrice = <?= json_encode((float)$event['single_price']) ?>;
const bundleQty = <?= json_encode((int)$event['bundle_qty']) ?>;
const bundlePrice = <?= json_encode((float)$event['bundle_price']) ?>;
function setQty(button, qty) {
    document.getElementById('inputQuantity').value = qty;
    document.querySelectorAll('.qty-btn').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
    const bundles = Math.floor(qty / bundleQty);
    const rem = qty % bundleQty;
    const total = (bundles * bundlePrice) + (rem * singlePrice);
    document.getElementById('priceDisplay').textContent = 'Total: ' + total.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}
document.getElementById('cpf').addEventListener('input', function(e){let v=e.target.value.replace(/\D/g,'').slice(0,11);if(v.length>9)v=v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4');else if(v.length>6)v=v.replace(/(\d{3})(\d{3})(\d{1,3})/,'$1.$2.$3');else if(v.length>3)v=v.replace(/(\d{3})(\d{1,3})/,'$1.$2');e.target.value=v;});
</script>
</body>
</html>
