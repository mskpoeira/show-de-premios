<?php
use AppCoreView;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Cartelas — <?= View::e($event['name']) ?></title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .checkout-header {
            background: linear-gradient(135deg, #0284c7 0%, #0f766e 100%);
            color: white;
            padding: 2.5rem 1rem;
            text-align: center;
            border-bottom: 4px solid #38bdf8;
        }
        .checkout-container {
            max-width: 600px;
            margin: -2rem auto 3rem auto;
            padding: 0 1rem;
        }
        .card-box {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .qty-selector {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin: 1.5rem 0;
        }
        .qty-btn {
            background: #f1f5f9;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 1rem 0.5rem;
            text-align: center;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .qty-btn:hover {
            border-color: #0284c7;
            background: #e0f2fe;
        }
        .qty-btn.active {
            border-color: #0284c7;
            background: #0284c7;
            color: white;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            background: #fff;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .btn-submit {
            width: 100%;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(22, 163, 74, 0.2);
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #15803d;
        }
        .price-display {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 800;
        }
    </style>
</head>
<body>

    <header class="checkout-header">
        <h1 style="margin:0 0 0.5rem 0; font-size: 1.75rem; font-weight: 800;">🎉 <?= View::e($event['name']) ?></h1>
        <p style="margin: 0; font-size: 0.95rem; opacity: 0.95;">
            📅 Data do Evento: <?= View::date($event['event_date']) ?> às <?= View::e($event['event_time']) ?> &bull; 📍 <?= View::e($event['location']) ?>
        </p>
    </header>

    <div class="checkout-container">
        <?php if (!empty($error)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
                ⚠️ <?= View::e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Prêmios do Evento -->
        <div class="card-box" style="margin-top: 1.5rem;">
            <h3 style="margin-top:0; font-size: 1.1rem; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                🏆 Premiação Oficial
            </h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($prizes as $prize): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                        <span style="font-weight: 600; color: #334155;"><?= View::e($prize['title']) ?></span>
                        <span style="font-weight: 700; color: #16a34a;"><?= View::money($prize['value']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Formulário de Compra -->
        <div class="card-box">
            <h3 style="margin-top:0; font-size: 1.1rem; color: #0f172a;">
                🎟️ Escolha a Quantidade de Cartelas
            </h3>
            
            <form action="/comprar" method="POST" id="checkoutForm">
                <input type="hidden" name="quantity" id="inputQuantity" value="1">

                <div class="qty-selector">
                    <div class="qty-btn active" onclick="setQty(1)">1 Cartela</div>
                    <div class="qty-btn" onclick="setQty(3)">3 Cartelas<br><small style="font-size:0.7rem; color:#d97706;">PROMOÇÃO</small></div>
                    <div class="qty-btn" onclick="setQty(6)">6 Cartelas</div>
                    <div class="qty-btn" onclick="setQty(10)">10 Cartelas</div>
                </div>

                <div class="price-display" id="priceDisplay">
                    Total: <?= View::money($event['single_price']) ?>
                </div>

                <h3 style="font-size: 1.1rem; color: #0f172a; margin-top: 1.5rem; border-top: 2px solid #f1f5f9; padding-top: 1rem;">
                    👤 Seus Dados para Identificação
                </h3>

                <div class="form-group">
                    <label class="form-label" for="name">Nome Completo *</label>
                    <input type="text" id="name" name="name" class="form-control" required placeholder="Ex: João da Silva">
                </div>

                <div class="form-group">
                    <label class="form-label" for="cpf">CPF *</label>
                    <input type="tel" id="cpf" name="cpf" class="form-control" required placeholder="000.000.000-00" maxlength="14">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Telefone / WhatsApp *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required placeholder="(12) 98242-2387">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mail <span style="font-weight: 400; color: #64748b;">(Opcional)</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="seuemail@exemplo.com">
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.95rem; color: #334155;">
                        <input type="checkbox" name="wants_email" value="1" checked style="width: 18px; height: 18px; accent-color: #0284c7;">
                        <span>Enviar minhas cartelas por e-mail após a confirmação</span>
                    </label>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 1rem;">
                    🚀 Continuar para Pagamento PIX
                </button>
            </form>
        </div>

        <div style="text-align: center; color: #64748b; font-size: 0.85rem;">
            🔒 Compra 100% Segura &bull; Cartelas geradas com selo criptográfico de autenticidade
        </div>
    </div>

    <script>
        const singlePrice = <?= (float)$event['single_price'] ?>;
        const bundleQty = <?= (int)$event['bundle_qty'] ?>;
        const bundlePrice = <?= (float)$event['bundle_price'] ?>;

        function setQty(qty) {
            document.getElementById('inputQuantity').value = qty;
            document.querySelectorAll('.qty-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');

            let total = 0;
            if (bundleQty > 1 && qty >= bundleQty) {
                const bundles = Math.floor(qty / bundleQty);
                const rem = qty % bundleQty;
                total = (bundles * bundlePrice) + (rem * singlePrice);
            } else {
                total = qty * singlePrice;
            }

            document.getElementById('priceDisplay').textContent = 'Total: ' + total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        // Máscaras automáticas
        document.getElementById('cpf').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            e.target.value = v;
        });

        document.getElementById('phone').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            else if (v.length > 6) v = v.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
            else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,5})/, '($1) $2');
            e.target.value = v;
        });
    </script>
</body>
</html>
