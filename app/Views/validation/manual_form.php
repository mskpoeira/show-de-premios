<?php
use App\Core\Auth;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação Manual de Cartela — Show de Prêmios</title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <style>body{background:#f8fafc;font-family:Arial,sans-serif;color:#1e293b;padding:2rem 1rem;margin:0}.val-container{max-width:550px;margin:0 auto}.card{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:2rem;box-shadow:0 4px 12px rgba(0,0,0,.05)}.form-group{margin-bottom:1.25rem}.form-label{display:block;font-size:.875rem;font-weight:600;margin-bottom:.35rem}.form-control{width:100%;padding:.75rem 1rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;box-sizing:border-box}.btn-val{background:#0284c7;color:#fff;border:0;padding:.85rem;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;width:100%}</style>
</head>
<body>
<div class="val-container">
    <div style="text-align:center;margin-bottom:1.5rem;"><h1 style="color:#0284c7;margin:0;">🔍 Validação de Cartela</h1><p style="color:#64748b;">Consulte a situação oficial da cartela</p></div>
    <div class="card">
        <form action="/validar" method="GET">
            <div class="form-group"><label class="form-label" for="ticket_number">Número da Cartela *</label><input type="text" id="ticket_number" name="ticket_number" class="form-control" required placeholder="Ex: JDA-0001" value="<?= View::e($ticketNumber) ?>" style="text-transform:uppercase;"></div>
            <div class="form-group"><label class="form-label" for="check_code">Código de Conferência <?= Auth::check() ? '(opcional para usuário autenticado)' : '*' ?></label><input type="text" id="check_code" name="check_code" class="form-control" <?= Auth::check() ? '' : 'required' ?> placeholder="Ex: K7P4-X2MQ" value="<?= View::e($checkCode) ?>" style="text-transform:uppercase;"></div>
            <button type="submit" class="btn-val">🔍 Verificar Cartela</button>
        </form>

        <?php if ($result !== null): ?>
        <div style="margin-top:1.75rem;border-top:2px solid #f1f5f9;padding-top:1.5rem;">
            <?php if (empty($result['found'])): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:1rem;border-radius:8px;text-align:center;font-weight:600;">❌ <?= View::e($result['message'] ?? 'Cartela não localizada.') ?></div>
            <?php elseif (!empty($result['authenticated'])): ?>
                <h2 style="text-align:center;color:#0284c7;"><?= View::e($result['ticket']['ticket_number']) ?></h2>
                <p style="text-align:center;"><strong>Status:</strong> <?= View::e($result['ticket']['status']) ?></p>
                <?php if (!empty($result['buyer'])): ?><div style="background:#f8fafc;padding:1rem;border-radius:8px;"><p><strong>Comprador:</strong> <?= View::e($result['buyer']['name']) ?></p><p><strong>Telefone:</strong> <?= View::e($result['buyer']['phone']) ?></p></div><?php endif; ?>
            <?php else: ?>
                <h2 style="text-align:center;color:#0284c7;"><?= View::e($result['ticket_number']) ?></h2>
                <p style="text-align:center;font-weight:800;color:<?= !empty($result['is_valid']) ? '#15803d' : '#b91c1c' ?>;"><?= !empty($result['is_valid']) ? '✅ CARTELA VÁLIDA' : '❌ CARTELA NÃO HABILITADA' ?></p>
                <div style="background:#f8fafc;padding:1rem;border-radius:8px;">
                    <p><strong>Evento:</strong> <?= View::e($result['event_name']) ?></p>
                    <?php if (!empty($result['event_date'])): ?><p><strong>Data:</strong> <?= View::date($result['event_date']) ?></p><?php endif; ?>
                    <?php if (!empty($result['buyer_name_masked'])): ?><p><strong>Comprador:</strong> <?= View::e($result['buyer_name_masked']) ?></p><?php endif; ?>
                    <?php if (!empty($result['buyer_cpf_masked'])): ?><p><strong>CPF:</strong> <?= View::e($result['buyer_cpf_masked']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
