<?php
use App\Core\Csrf;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Instalação — Show de Prêmios') ?></title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .setup-box {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            padding: 2.2rem;
            border-radius: var(--radius);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.25);
        }
        .setup-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
            text-align: center;
        }
        .setup-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="setup-box">
        <div class="setup-title">🚀 Configuração Inicial</div>
        <div class="setup-subtitle">Criação do Administrador Master do Show de Prêmios</div>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error">⚠️ <?= View::e($flashError) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= View::url('setup') ?>">
            <?= Csrf::inputField() ?>

            <div class="form-group">
                <label class="form-label" for="name">Nome Completo</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="Ex: Administrador">
            </div>

            <div class="form-group">
                <label class="form-label" for="login">Login de Acesso</label>
                <input type="text" id="login" name="login" class="form-control" required placeholder="Ex: admin">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Senha de Acesso (Mínimo 6 caracteres)</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirmação da Senha</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Concluir Configuração e Acessar
            </button>
        </form>
    </div>

</body>
</html>
