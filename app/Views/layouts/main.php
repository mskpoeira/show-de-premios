<?php
use App\Core\Auth;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Show de Prêmios') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= View::url('assets/icons/icon.svg') ?>">
    <link rel="manifest" href="<?= View::url('manifest.json') ?>">
    <meta name="theme-color" content="#1e1b4b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="stylesheet" href="<?= View::url('assets/css/style.css') ?>?v=<?= file_exists(__DIR__ . '/../../public/assets/css/style.css') ? filemtime(__DIR__ . '/../../public/assets/css/style.css') : time() ?>">
    <link rel="stylesheet" href="<?= View::url('assets/css/print.css') ?>" media="print">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

    <!-- Header & Topbar -->
    <header class="app-header">
        <div class="topbar-container">
            <a href="<?= View::url('painel') ?>" class="brand-title">
                🏆 <?= mb_strtoupper(View::e(View::systemTitle())) ?>
                <span class="brand-badge">OFICIAL</span>
            </a>

            <div class="user-nav">
                <?php if (Auth::check()): ?>
                    <span class="user-role-badge" style="<?= Auth::roleBadgeColor() ?> font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 6px;"><?= View::e(Auth::roleLabel()) ?></span>
                    <span>Olá, <strong><?= View::e(Auth::user()['name']) ?></strong></span>
                    <a href="<?= View::url('logout') ?>" class="nav-link" title="Sair do sistema">Sair ➔</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Navigation Menu (5 Centros de Controle Unificados) -->
    <?php if (Auth::check()): 
        $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
        if (str_starts_with($currentUri, '/showdepremios/')) {
            $currentUri = substr($currentUri, strlen('/showdepremios'));
        } elseif ($currentUri === '/showdepremios') {
            $currentUri = '/';
        }

        $isPainel = ($currentUri === '/' || str_starts_with($currentUri, '/painel') || str_starts_with($currentUri, '/relatorios') || str_starts_with($currentUri, '/auditoria'));
        $isCaixa = (str_starts_with($currentUri, '/caixa') || str_starts_with($currentUri, '/dia') || str_starts_with($currentUri, '/utilidades') || str_starts_with($currentUri, '/rodada'));
        $isLocutor = str_starts_with($currentUri, '/locutor');
        $isTelao = str_starts_with($currentUri, '/telao');
        $isConfig = (str_starts_with($currentUri, '/configuracoes') || str_starts_with($currentUri, '/vendedores') || str_starts_with($currentUri, '/operadores') || str_starts_with($currentUri, '/backup'));
    ?>
    <nav class="sub-nav">
        <ul class="sub-nav-list">
            <li><a href="<?= View::url('painel') ?>" class="<?= $isPainel ? 'active' : '' ?>">📊 Painel Gerencial</a></li>
            <li><a href="<?= View::url('caixa') ?>" class="<?= $isCaixa ? 'active' : '' ?>">💵 Caixa</a></li>
            <li><a href="<?= View::url('locutor') ?>" class="<?= $isLocutor ? 'active' : '' ?>" style="<?= $isLocutor ? '' : 'color: #c084fc;' ?> font-weight: 800;">🎤 Locutor</a></li>
            <li><a href="<?= View::url('telao') ?>" target="_blank" class="<?= $isTelao ? 'active' : '' ?>" style="<?= $isTelao ? '' : 'color: #fbbf24;' ?> font-weight: 800;" title="Abrir telão do público em nova aba ou projetor">📺 Telão ↗</a></li>
            <?php if (Auth::canManageUsers() || Auth::isAdmin()): ?>
                <li><a href="<?= View::url('configuracoes') ?>" class="<?= $isConfig ? 'active' : '' ?>">⚙️ Configurações</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Flash Alerts -->
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success">✅ <?= View::e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error">⚠️ <?= View::e($flashError) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashWarning)): ?>
            <div class="alert alert-warning">ℹ️ <?= View::e($flashWarning) ?></div>
        <?php endif; ?>

        <!-- Legend -->
        <div class="legend-box no-print">
            <span>✎ <strong>Fundo amarelo claro</strong> = Campo editável pelo usuário</span>
        </div>

        <?= $slot ?>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
        <p>Show de Prêmios &copy; <?= date('Y') ?> &bull; Sistema Seguro &bull; Início Histórico: 06/09/2026</p>
    </footer>

    <script src="<?= View::url('assets/js/app.js') ?>"></script>
    <script src="<?= View::url('assets/js/offline-sync.js') ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= View::url("sw.js") ?>').catch(() => {});
            });
        }
    </script>
</body>
</html>
