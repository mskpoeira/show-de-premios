<?php
use App\Core\Csrf;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Login — Show de Prêmios') ?></title>
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        .login-box {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.25);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
            text-align: center;
        }
        .login-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <div class="login-title">🏆 SHOW DE PRÊMIOS</div>
        <div class="login-subtitle">Acesso Restrito ao Sistema Operacional</div>

        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success">✅ <?= View::e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error">⚠️ <?= View::e($flashError) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= View::url('login') ?>">
            <?= Csrf::inputField() ?>

            <div class="form-group">
                <label class="form-label" for="login">Usuário ou E-mail</label>
                <input type="text" id="login" name="login" class="form-control" required autofocus placeholder="Seu usuário">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Entrar no Sistema
            </button>
        </form>
    </div>

</body>
</html>
