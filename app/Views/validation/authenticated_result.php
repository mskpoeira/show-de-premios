<?php
use App\Core\View;
use App\Services\TicketService;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação Administrativa — Cartela <?= View::e($ticket['ticket_number']) ?></title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; padding: 2rem 1rem; margin: 0; }
        .auth-val-container { max-width: 650px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge { display: inline-block; padding: 0.35rem 0.8rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="auth-val-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <a href="/painel" style="color: #0284c7; font-weight: 600; text-decoration: none;">← Voltar ao Painel</a>
            <span style="background: #0284c7; color: white; padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 700;">
                Operador: <?= View::e($user['name']) ?> (<?= View::e($user['role']) ?>)
            </span>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <span style="font-size: 0.85rem; color: #64748b;">Número da Cartela:</span>
                    <h1 style="margin: 0; font-size: 2rem; color: #0284c7; font-weight: 900;"><?= View::e($ticket['ticket_number']) ?></h1>
                    <span style="font-size: 0.85rem; color: #64748b;">Código: <code><?= View::e($ticket['check_code']) ?></code></span>
                </div>
                <div>
                    <?php if ($ticket['status'] === 'VALID' || $ticket['status'] === 'AWARDED'): ?>
                        <span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">✅ VÁLIDA</span>
                    <?php else: ?>
                        <span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;">❌ <?= View::e($ticket['status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <h3 style="font-size: 1.1rem; color: #0f172a; margin-top: 0;">👤 Dados do Comprador (Consulta Auditada)</h3>
            <?php if ($buyer): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <p style="margin: 0.3rem 0;"><strong>Nome:</strong> <?= View::e($buyer['name']) ?></p>
                    <p style="margin: 0.3rem 0;"><strong>CPF:</strong> <?= View::e(TicketService::maskCpf($buyer['cpf'])) ?> <small style="color: #64748b;">(Original: <?= View::e($buyer['cpf']) ?>)</small></p>
                    <p style="margin: 0.3rem 0;"><strong>Telefone:</strong> <?= View::e($buyer['phone']) ?></p>
                    <p style="margin: 0.3rem 0;"><strong>E-mail:</strong> <?= View::e($buyer['email'] ?: 'Não informado') ?></p>
                    
                    <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
                        <a href="tel:<?= View::e($buyer['phone']) ?>" style="background: #0284c7; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                            📞 Ligar
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $buyer['phone']) ?>?text=Ol%C3%A1%20<?= urlencode($buyer['name']) ?>!%20Entramos%20em%20contato%20sobre%20sua%20cartela%20<?= urlencode($ticket['ticket_number']) ?>" target="_blank" style="background: #16a34a; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                            💬 WhatsApp
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <p style="color: #64748b; font-style: italic;">Nenhum comprador vinculado a esta cartela (venda anônima ou pré-impressa).</p>
            <?php endif; ?>

            <?php if ($order): ?>
                <h3 style="font-size: 1.1rem; color: #0f172a;">📦 Pedido de Origem</h3>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <p style="margin: 0.3rem 0;"><strong>Código do Pedido:</strong> <?= View::e($order['order_code']) ?></p>
                    <p style="margin: 0.3rem 0;"><strong>Valor Total:</strong> <?= View::money($order['total_amount']) ?></p>
                    <p style="margin: 0.3rem 0;"><strong>Status Pagamento:</strong> <span class="badge" style="background: #e0f2fe; color: #0284c7;"><?= View::e($order['payment_status']) ?></span></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid #f1f5f9; padding-top: 1.5rem;">
                <a href="/cartelas/imprimir/<?= View::e($ticket['secure_token']) ?>" target="_blank" style="background: #0f766e; color: white; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                    🖨️ Reemitir / Imprimir 2ª Via
                </a>
            </div>
        </div>
    </div>
</body>
</html>
