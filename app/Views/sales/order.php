<?php
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; padding: 1.5rem 1rem; }
        .order-container { max-width: 650px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.75rem; margin-bottom: 1.5rem; }
        .status-badge { display: inline-block; padding: 0.35rem 0.85rem; border-radius: 9999px; font-weight: 700; font-size: 0.85rem; }
        .status-paid { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .status-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .pix-code { background: #f1f5f9; padding: 0.85rem; border-radius: 8px; font-family: monospace; font-size: 0.85rem; word-break: break-all; border: 1px solid #cbd5e1; user-select: all; }
        .btn-copy { background: #0284c7; color: #fff; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; font-size: 1rem; margin-top: 0.5rem; }
        .btn-copy:hover { background: #0369a1; }
        .ticket-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <div class="order-container">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 800; color: #0284c7;">
                🎉 Show de Prêmios Oficial
            </h1>
            <p style="margin: 0; color: #64748b;"><?= View::e($order['event_name']) ?></p>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                <div>
                    <span style="font-size: 0.85rem; color: #64748b;">Código do Pedido:</span>
                    <strong style="font-size: 1.1rem; display: block;"><?= View::e($order['order_code']) ?></strong>
                </div>
                <div>
                    <?php if ($is_paid): ?>
                        <span class="status-badge status-paid">✅ PAGAMENTO CONFIRMADO</span>
                    <?php else: ?>
                        <span class="status-badge status-pending" id="statusBadge">⏳ AGUARDANDO PAGAMENTO</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <p style="margin: 0.25rem 0;"><strong>Comprador:</strong> <?= View::e($order['buyer_name']) ?></p>
                <p style="margin: 0.25rem 0;"><strong>CPF:</strong> <?= View::e(TicketService::maskCpf($order['buyer_cpf'])) ?></p>
                <p style="margin: 0.25rem 0;"><strong>Quantidade:</strong> <?= (int)$order['quantity'] ?> cartela(s)</p>
                <p style="margin: 0.25rem 0; font-size: 1.15rem; color: #16a34a;"><strong>Valor Total:</strong> <?= View::money($order['total_amount']) ?></p>
            </div>

            <?php if (!$is_paid): ?>
                <div id="pendingArea" style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 1.25rem; margin-top: 1.5rem; text-align: center;">
                    <h3 style="margin-top: 0; color: #92400e;">📱 Pague via PIX para Liberar suas Cartelas</h3>
                    <p style="font-size: 0.9rem; color: #78350f;">
                        Abra o app do seu banco, escolha <strong>PIX Copia e Cola</strong> e cole o código abaixo:
                    </p>

                    <div class="pix-code" id="pixPayload"><?= View::e($order['pix_payload'] ?? 'Carregando chave PIX...') ?></div>
                    <button class="btn-copy" onclick="copyPix()">📋 Copiar Código PIX</button>

                    <p style="font-size: 0.8rem; color: #92400e; margin-top: 1rem;">
                        ⏱️ Esta tela atualiza automaticamente assim que o pagamento for registrado.
                    </p>

                    <?php if ($isAuthenticated): ?>
                        <form action="/pedido/<?= View::e($order['order_code']) ?>/confirmar" method="POST" style="margin-top: 1.5rem; border-top: 1px dashed #fde68a; padding-top: 1rem;">
                            <button type="submit" style="background: #15803d; color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; font-weight: 700; cursor: pointer;">
                                💼 [Operador] Confirmar Pagamento Manualmente
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem; margin-top: 1.5rem; text-align: center;">
                    <h3 style="margin-top: 0; color: #15803d;">🎉 Suas Cartelas Estão Prontas e Habilitadas!</h3>
                    <p style="font-size: 0.95rem; color: #166534; margin: 0;">
                        Suas cartelas são válidas para todos os prêmios do evento. Guarde seus números ou imprima abaixo.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lista de Cartelas do Pedido -->
        <div class="card">
            <h3 style="margin-top: 0; font-size: 1.15rem; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem;">
                🎟️ Cartelas do Pedido (<?= count($tickets) ?>)
            </h3>

            <?php foreach ($tickets as $t): ?>
                <div class="ticket-item">
                    <div>
                        <strong style="font-size: 1.1rem; color: #0284c7;"><?= View::e($t['ticket_number']) ?></strong>
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                            Código de Conferência: <code><?= View::e($t['check_code']) ?></code>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="/v/<?= View::e($t['secure_token']) ?>" target="_blank" style="background: #e0f2fe; color: #0284c7; text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                            🔍 Validar QR
                        </a>
                        <a href="/cartelas/imprimir/<?= View::e($t['secure_token']) ?>" target="_blank" style="background: #dcfce7; color: #15803d; text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                            🖨️ Imprimir
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="/comprar" style="color: #0284c7; font-weight: 600; text-decoration: none;">← Fazer outro pedido</a>
        </div>
    </div>

    <script>
        function copyPix() {
            const text = document.getElementById('pixPayload').innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Código PIX copiado com sucesso!');
            });
        }

        <?php if (!$is_paid): ?>
        // Polling para checar confirmação automática do pagamento a cada 4 segundos
        setInterval(() => {
            fetch('/pedido/<?= View::e($order['order_code']) ?>/status')
                .then(res => res.json())
                .then(data => {
                    if (data.is_paid) {
                        window.location.reload();
                    }
                }).catch(() => {});
        }, 4000);
        <?php endif; ?>
    </script>
</body>
</html>
