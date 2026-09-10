<?php
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Cartela — Show de Prêmios</title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, sans-serif;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            box-sizing: border-box;
        }
        .validation-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e2e8f0;
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 0.6rem 1.5rem;
            border-radius: 9999px;
            font-size: 1.15rem;
            font-weight: 800;
            margin: 1.5rem 0;
            letter-spacing: 0.05em;
        }
        .status-valid {
            background: #dcfce7;
            color: #15803d;
            border: 2px solid #86efac;
        }
        .status-invalid {
            background: #fee2e2;
            color: #b91c1c;
            border: 2px solid #fca5a5;
        }
        .status-pending {
            background: #fef3c7;
            color: #b45309;
            border: 2px solid #fde68a;
        }
        .ticket-number {
            font-size: 2.25rem;
            font-weight: 900;
            color: #0284c7;
            margin: 0.5rem 0;
            letter-spacing: 0.02em;
        }
        .info-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            font-size: 0.95rem;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

    <div class="validation-card">
        <div style="color: #0284c7; font-weight: 800; font-size: 1.2rem; margin-bottom: 0.5rem;">
            🏆 SHOW DE PRÊMIOS OFICIAL
        </div>

        <?php if (!$found): ?>
            <div class="status-badge status-invalid">
                🚫 CARTELA NÃO ENCONTRADA
            </div>
            <p style="color: #64748b; margin-top: 1rem;">
                <?= View::e($message ?? 'Código de validação inválido.') ?>
            </p>
        <?php else: ?>
            <div class="ticket-number">
                <?= View::e($ticket_number) ?>
            </div>

            <?php if ($status === 'VALID' || $status === 'AWARDED'): ?>
                <div class="status-badge status-valid">
                    ✅ CARTELA VÁLIDA
                </div>
            <?php elseif ($status === 'PENDING' || $status === 'RESERVED'): ?>
                <div class="status-badge status-pending">
                    ⏳ PAGAMENTO PENDENTE
                </div>
            <?php else: ?>
                <div class="status-badge status-invalid">
                    ❌ CARTELA INVÁLIDA / CANCELADA
                </div>
            <?php endif; ?>

            <div class="info-box">
                <div class="info-row">
                    <span style="color: #64748b;">Evento:</span>
                    <strong><?= View::e($event_name) ?></strong>
                </div>
                <div class="info-row">
                    <span style="color: #64748b;">Código de Conferência:</span>
                    <strong><code><?= View::e($check_code) ?></code></strong>
                </div>
                <div class="info-row">
                    <span style="color: #64748b;">Situação no Sorteio:</span>
                    <strong style="<?= $is_valid ? 'color:#15803d;' : 'color:#b91c1c;' ?>">
                        <?= $is_valid ? 'Habilitada para concorrer' : 'Não habilitada' ?>
                    </strong>
                </div>
            </div>

            <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 1.5rem;">
                🔒 Validação oficial com integridade digital. Por motivos de privacidade e conformidade com a LGPD, os dados pessoais do comprador não são exibidos publicamente.
            </p>
        <?php endif; ?>

        <div style="margin-top: 2rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
            <a href="/validar" style="color: #0284c7; text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                🔍 Validar outra cartela manualmente
            </a>
        </div>
    </div>

</body>
</html>
