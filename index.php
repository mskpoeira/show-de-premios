<?php
/**
 * SHOW DE PRÊMIOS — Front Controller
 * Início do Histórico: 06/09/2026
 * URL: https://mskpoeira.com.br/showdepremios
 */

declare(strict_types=1);

// Error handling in production: log errors, capture uncaught exceptions
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
if (is_writable($logDir)) {
    ini_set('error_log', $logDir . '/error.log');
}

set_exception_handler(function (\Throwable $e) {
    http_response_code(500);
    error_log("[ShowDePremios Fatal] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Show de Prêmios - Erro</title><style>body{margin:0;font-family:sans-serif;padding:1.5rem;background:#0f172a;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:900px;width:100%;background:#1e293b;padding:2rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5);border:1px solid #334155;}</style></head><body>";
    echo "<div class='box'>";
    echo "<h2 style='margin-top:0;color:#f87171;'>⚠️ Ocorreu um erro na aplicação</h2>";
    echo "<p style='color:#cbd5e1;'>Detalhes do erro:</p>";
    echo "<pre style='background:#0f172a;padding:1rem;border-radius:8px;color:#fca5a5;overflow:auto;max-height:400px;font-size:0.85rem;'>" . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "<p style='margin-bottom:0;'><a href='/dia' style='color:#38bdf8;text-decoration:none;font-weight:bold;'>← Voltar ao Início</a></p>";
    echo "</div></body></html>";
    exit;
});

// Timezone & Locale
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');

// Simple PSR-4 Autoloader
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'App\\')) {
        $rel = str_replace('\\', '/', substr($class, 4));
        $path = __DIR__ . '/app/' . $rel . '.php';
        if (!file_exists($path)) {
            $path = __DIR__ . '/app/' . strtolower($rel) . '.php';
        }
        if (file_exists($path)) {
            require_once $path;
        }
    } elseif (str_starts_with($class, 'Database\\')) {
        $rel = str_replace('\\', '/', substr($class, 9));
        $path = __DIR__ . '/database/' . $rel . '.php';
        if (!file_exists($path)) {
            $path = __DIR__ . '/database/' . strtolower($rel) . '.php';
        }
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

// Load .env if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$k, $v] = explode('=', $line, 2) + ['', ''];
        putenv(trim($k) . '=' . trim($v));
        $_ENV[trim($k)] = trim($v);
    }
}

// Normalize URI across domains (supports root / on showdepremios.mskpoeira.com.br and /showdepremios)
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
if (str_starts_with($uri, '/showdepremios/')) {
    $uri = substr($uri, strlen('/showdepremios'));
} elseif ($uri === '/showdepremios') {
    $uri = '/';
}

if (preg_match('#^/assets/(css|js|img)/.+\.(css|js|png|jpg|svg|ico)$#', $uri)) {
    $filePath = __DIR__ . '/public' . $uri;
    if (file_exists($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'text/plain'));
        readfile($filePath);
        exit;
    }
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');

// Run migrations on start
\Database\Migrations::run();

// Start Session
\App\Core\Session::start();

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DayController;
use App\Controllers\RoundController;
use App\Controllers\CashController;
use App\Controllers\SellerController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\AuditController;
use App\Controllers\BackupController;
use App\Controllers\DisplayController;
use App\Controllers\SpeakerController;
use App\Controllers\UserController;
use App\Controllers\UtilitiesController;
use App\Core\Router;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\OperatorMiddleware;

// Routes
Router::get('/', [DashboardController::class, 'index'], [AuthMiddleware::class]);
Router::get('/painel', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Public Big Screen (Telão do Evento / Datashow / Smart TV)
Router::get('/telao', [DisplayController::class, 'index']);
Router::get('/telao/status', [DisplayController::class, 'status']);

// PWA & Static files fallback
Router::get('/manifest.json', function() {
    header('Content-Type: application/manifest+json; charset=utf-8');
    readfile(__DIR__ . '/public/manifest.json');
    exit;
});
Router::get('/sw.js', function() {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(__DIR__ . '/public/sw.js');
    exit;
});

// Utilities (Redirecionamento para a nova aba unificada no Caixa)
Router::get('/utilidades/contador', function() {
    Response::redirect('/caixa?tab=contador');
}, [AuthMiddleware::class]);
Router::get('/utilidades/simulador', function() {
    Response::redirect('/caixa?tab=simulador');
}, [AuthMiddleware::class]);

// Auth & Setup
Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::get('/logout', [AuthController::class, 'logout']);
Router::get('/setup', [AuthController::class, 'showSetup']);
Router::post('/setup', [AuthController::class, 'setupAdmin']);

// Days (Redirecionamento para a nova aba unificada de Operação do Dia no Caixa)
Router::get('/dia', function() {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/caixa?tab=dia' . $queryString);
}, [AuthMiddleware::class]);
Router::get('/dias/abrir', [DayController::class, 'open'], [OperatorMiddleware::class]);
Router::post('/dias/abrir', [DayController::class, 'open'], [OperatorMiddleware::class]);
Router::post('/dias/fechar', [DayController::class, 'close'], [OperatorMiddleware::class]);
Router::post('/dias/reabrir', [DayController::class, 'reopen'], [AdminMiddleware::class]);

// Rounds
Router::get('/rodada', [RoundController::class, 'operate'], [AuthMiddleware::class]);
Router::post('/rodadas/criar', [RoundController::class, 'create'], [OperatorMiddleware::class]);
Router::post('/rodadas/salvar-vendas', [RoundController::class, 'saveSales'], [OperatorMiddleware::class]);
Router::post('/rodadas/alterar-status', [RoundController::class, 'changeStatus'], [OperatorMiddleware::class]);
Router::post('/rodadas/fechar', [RoundController::class, 'close'], [OperatorMiddleware::class]);
Router::post('/rodadas/reabrir', [RoundController::class, 'reopen'], [AdminMiddleware::class]);

// Speaker & Bingo Sorteio (Acesso direto para Locutor no Celular ou Computador)
Router::get('/locutor', [SpeakerController::class, 'index']);
Router::post('/locutor/cantar-pedra', [SpeakerController::class, 'callNumber']);
Router::post('/locutor/desfazer-pedra', [SpeakerController::class, 'undoNumber']);
Router::post('/locutor/alterar-status', [SpeakerController::class, 'updateStatus']);
Router::post('/locutor/salvar-ganhador', [SpeakerController::class, 'saveWinner']);
Router::post('/locutor/limpar-pedras', [SpeakerController::class, 'clearNumbers']);

// Cash (Hub Unificado: Fluxo, Dia Atual, Contador de Cédulas, Simulador)
Router::get('/caixa', [CashController::class, 'index'], [AuthMiddleware::class]);
Router::post('/caixa/adicionar', [CashController::class, 'addMovement'], [OperatorMiddleware::class]);

// Sellers (Redirecionamento para a nova aba unificada de Vendedores nas Configurações)
Router::get('/vendedores', function() {
    Response::redirect('/configuracoes?tab=vendedores');
}, [AuthMiddleware::class]);
Router::post('/vendedores/criar', [SellerController::class, 'create'], [OperatorMiddleware::class]);
Router::post('/vendedores/editar', [SellerController::class, 'update'], [OperatorMiddleware::class]);
Router::post('/vendedores/toggle', [SellerController::class, 'toggleStatus'], [OperatorMiddleware::class]);

// Reports (Redirecionamento para as novas abas unificadas no Painel Gerencial)
Router::get('/relatorios', function() {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=relatorios' . $queryString);
}, [AuthMiddleware::class]);
Router::get('/relatorios/gerencial', function() {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=gerencial' . $queryString);
}, [AuthMiddleware::class]);
Router::get('/relatorios/vendedor', [ReportController::class, 'sellerStatement'], [AuthMiddleware::class]);
Router::get('/relatorios/termo-caixa', [ReportController::class, 'cashStatement'], [AuthMiddleware::class]);
Router::get('/relatorios/csv', [ReportController::class, 'exportCsv'], [AuthMiddleware::class]);

// Operators & Team Management (Redirecionamento para a aba unificada nas Configurações)
Router::get('/operadores', function() {
    Response::redirect('/configuracoes?tab=operadores');
}, [AuthMiddleware::class]);
Router::post('/operadores/novo', [UserController::class, 'store'], [AuthMiddleware::class]);
Router::post('/operadores/editar', [UserController::class, 'update'], [AuthMiddleware::class]);
Router::post('/operadores/status', [UserController::class, 'toggleStatus'], [AuthMiddleware::class]);

// Settings (Hub Unificado: Gerais & Preços, Vendedores, Operadores, Backup)
Router::get('/configuracoes', [SettingsController::class, 'index'], [AdminMiddleware::class]);
Router::post('/configuracoes/precos', [SettingsController::class, 'updatePricing'], [AdminMiddleware::class]);
Router::post('/configuracoes/precos/excluir', [SettingsController::class, 'deletePricing'], [AdminMiddleware::class]);
Router::post('/configuracoes/parametros', [SettingsController::class, 'updateParameters'], [AdminMiddleware::class]);
Router::post('/configuracoes/pix', [SettingsController::class, 'updatePix'], [AdminMiddleware::class]);
Router::post('/configuracoes/limpar-banco', [SettingsController::class, 'cleanDatabase'], [AdminMiddleware::class]);

// Audit (Redirecionamento para a nova aba unificada no Painel Gerencial)
Router::get('/auditoria', function() {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=auditoria' . $queryString);
}, [AdminMiddleware::class]);

// Backup (Redirecionamento para a nova aba unificada nas Configurações)
Router::get('/backup', function() {
    Response::redirect('/configuracoes?tab=backup');
}, [AdminMiddleware::class]);
Router::get('/backup/exportar', [BackupController::class, 'export'], [AdminMiddleware::class]);
Router::post('/backup/restaurar', [BackupController::class, 'restore'], [AdminMiddleware::class]);

// API Connectivity & Ping
Router::get('/api/ping', function() {
    \App\Services\BackupService::autoBackupIfNeeded();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'online', 'time' => time()]);
    exit;
});

// Auto-backup check (runs in 0.0001s if within 5-min interval)
\App\Services\BackupService::autoBackupIfNeeded();

// Dispatch
Router::dispatch();
