<?php
use AppCoreView;
use AppServicesTicketService;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação Manual de Cartela — Show de Prêmios</title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; padding: 2rem 1rem; margin: 0; }
        .val-container { max-width: 550px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.35rem; color: #334155; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        .btn-val { background: #0284c7; color: white; border: none; padding: 0.85rem; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; width: 100%; }
        .btn-val:hover { background: #0369a1; }
    </style>
</head>
<body>
    <div class="val-container">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="color: #0284c7; margin: 0 0 0.25rem 0; font-size: 1.6rem; font-weight: 800;">
                🔍 Validação de Cartela
            </h1>
            <p style="margin: 0; color: #64748b;">Consulte a situação oficial da cartela no sorteio</p>
        </div>

        <div class="card">
            <form action="/validar" method="GET">
                <div class="form-group">
                    <label class="form-label" for="ticket_number">Número da Cartela *</label>
                    <input type="text" id="ticket_number" name="ticket_number" class="form-control" required placeholder="Ex: JDA-0001" value="<?= View::e($ticketNumber) ?>" style="text-transform: uppercase;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="check_code">Código de Conferência <small style="font-weight:400; color:#64748b;">(Opcional para público)</small></label>
                    <input type="text" id="check_code" name="check_code" class="form-control" placeholder="Ex: K7P4-X2MQ" value="<?= View::e($checkCode) ?>" style="text-transform: uppercase;">
                </div>

                <button type="submit" class="btn-val">
                    🔍 Verificar Situação da Cartela
                </button>
            </form>

            <?php if ($result !== null): ?>
                <div style="margin-top: 1.75rem; border-top: 2px solid #f1f5f9; padding-top: 1.5rem;">
                    <?php if (!$result['found']): ?>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem; border-radius: 8px; text-align: center; font-weight: 600;">
                            ❌ <?= View::e($result['message']) ?>
                        </div>
                    <?php elseif (!empty($result['authenticated'])): ?>
                        <!-- Visualização autenticada -->
                        <div style="text-align: center;">
                            <h2 style="margin: 0; color: #0284c7; font-size: 1.8rem;"><?= View::e($result['ticket']['ticket_number']) ?></h2>
                            <div style="margin: 0.5rem 0;">
                                <span style="background: #dcfce7; color: #15803d; padding: 0.35rem 0.8rem; border-radius: 6px; font-weight: 700;">
                                    <?= View::e($result['ticket']['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($result['buyer'])): ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 1rem; text-align: left;">
                                    <p style="margin: 0.25rem 0;"><strong>Comprador:</strong> <?= View::e($result['buyer']['name']) ?></p>
                                    <p style="margin: 0.25rem 0;"><strong>Telefone:</strong> <?= View::e($result['buyer']['phone']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Visualização pública estrita (SEM dados pessoais) -->
                        <div style="text-align: center;">
                            <h2 style="margin: 0; color: #0284c7; font-size: 1.8rem;"><?= View::e($result['ticket_number']) ?></h2>
                            <div style="margin: 0.75rem 0;">
                                <?php if ($result['is_valid']): ?>
                                    <span style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 0.4rem 1rem; border-radius: 9999px; font-weight: 800; font-size: 1.1rem;">
                                        ✅ CARTELA VÁLIDA
                                    </span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.4rem 1rem; border-radius: 9999px; font-weight: 800; font-size: 1.1rem;">
                                        ❌ CARTELA NÃO HABILITADA
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 0;">Evento: <strong><?= View::e($result['event_name']) ?></strong></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="/comprar" style="color: #0284c7; font-weight: 600; text-decoration: none;">Comprar Cartelas Online ➔</a>
        </div>
    </div>
</body>
</html>
