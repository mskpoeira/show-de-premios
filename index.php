<?php
/**
 * SHOW DE PRÊMIOS — Front Controller
 * Domínio oficial: https://showdepremios.mskpoeira.com.br
 */

declare(strict_types=1);

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

set_exception_handler(function (\Throwable $e): void {
    $requestId = bin2hex(random_bytes(8));
    http_response_code(500);
    error_log("[ShowDePremios][$requestId] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Show de Prêmios - Erro</title><style>body{margin:0;font-family:Arial,sans-serif;background:#0f172a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{max-width:620px;background:#1e293b;padding:2rem;border-radius:12px;border:1px solid #334155}a{color:#38bdf8}</style></head><body><div class='box'><h2>⚠️ Ocorreu um erro na aplicação</h2><p>O incidente foi registrado para análise.</p><p><strong>Código:</strong> " . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . "</p><p><a href='/'>Voltar ao início</a></p></div></body></html>";
    exit;
});

date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $rel = str_replace('\\', '/', substr($class, 4));
            $path = __DIR__ . '/app/' . $rel . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        } elseif (str_starts_with($class, 'Database\\')) {
            $rel = str_replace('\\', '/', substr($class, 9));
            $path = __DIR__ . '/database/' . $rel . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    });
}

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
if (str_starts_with($uri, '/showdepremios/')) {
    $uri = substr($uri, strlen('/showdepremios'));
} elseif ($uri === '/showdepremios') {
    $uri = '/';
}

if (preg_match('#^/assets/(css|js|img|icons)/.+\.(css|js|png|jpg|jpeg|svg|ico|webp)$#i', $uri)) {
    $filePath = __DIR__ . '/public' . $uri;
    if (is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimes = [
            'css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon', 'webp' => 'image/webp',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=3600');
        readfile($filePath);
        exit;
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Robots-Tag: noindex, nofollow');

\App\Core\Session::start();

use App\Controllers\OnlineSalesController;
use App\Controllers\ValidationController;
use App\Controllers\TicketController;
use App\Controllers\DrawController;
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
use App\Core\Router;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\OperatorMiddleware;
use App\Middleware\MasterMiddleware;
use App\Middleware\SpeakerMiddleware;
use App\Middleware\DrawOperatorMiddleware;

Router::get('/', [DashboardController::class, 'index'], [AuthMiddleware::class]);
Router::get('/painel', [DashboardController::class, 'index'], [AuthMiddleware::class]);

Router::get('/telao', [DisplayController::class, 'index']);
Router::get('/telao/status', [DisplayController::class, 'status']);

Router::get('/manifest.json', function(): void {
    header('Content-Type: application/manifest+json; charset=utf-8');
    readfile(__DIR__ . '/public/manifest.json');
    exit;
});
Router::get('/sw.js', function(): void {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . '/public/sw.js');
    exit;
});

Router::get('/utilidades/contador', function(): void {
    Response::redirect('/caixa?tab=contador');
}, [AuthMiddleware::class]);
Router::get('/utilidades/simulador', function(): void {
    Response::redirect('/caixa?tab=simulador');
}, [AuthMiddleware::class]);

Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
Router::get('/setup', [AuthController::class, 'showSetup']);
Router::post('/setup', [AuthController::class, 'setupAdmin']);

Router::get('/dia', function(): void {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/caixa?tab=dia' . $queryString);
}, [AuthMiddleware::class]);
Router::post('/dias/abrir', [DayController::class, 'open'], [OperatorMiddleware::class]);
Router::post('/dias/fechar', [DayController::class, 'close'], [OperatorMiddleware::class]);
Router::post('/dias/reabrir', [DayController::class, 'reopen'], [AdminMiddleware::class]);

Router::get('/rodada', [RoundController::class, 'operate'], [AuthMiddleware::class]);
Router::post('/rodadas/criar', [RoundController::class, 'create'], [OperatorMiddleware::class]);
Router::post('/rodadas/salvar-vendas', [RoundController::class, 'saveSales'], [OperatorMiddleware::class]);
Router::post('/rodadas/alterar-status', [RoundController::class, 'changeStatus'], [OperatorMiddleware::class]);
Router::post('/rodadas/fechar', [RoundController::class, 'close'], [OperatorMiddleware::class]);
Router::post('/rodadas/reabrir', [RoundController::class, 'reopen'], [AdminMiddleware::class]);

Router::get('/locutor', [SpeakerController::class, 'index'], [SpeakerMiddleware::class]);
Router::post('/locutor/cantar-pedra', [SpeakerController::class, 'callNumber'], [SpeakerMiddleware::class]);
Router::post('/locutor/desfazer-pedra', [SpeakerController::class, 'undoNumber'], [SpeakerMiddleware::class]);
Router::post('/locutor/alterar-status', [SpeakerController::class, 'updateStatus'], [SpeakerMiddleware::class]);
Router::post('/locutor/salvar-ganhador', [SpeakerController::class, 'saveWinner'], [SpeakerMiddleware::class]);
Router::post('/locutor/limpar-pedras', [SpeakerController::class, 'clearNumbers'], [SpeakerMiddleware::class]);

Router::get('/caixa', [CashController::class, 'index'], [AuthMiddleware::class]);
Router::post('/caixa/adicionar', [CashController::class, 'addMovement'], [OperatorMiddleware::class]);

Router::get('/vendedores', function(): void {
    Response::redirect('/configuracoes?tab=vendedores');
}, [AuthMiddleware::class]);
Router::post('/vendedores/criar', [SellerController::class, 'create'], [OperatorMiddleware::class]);
Router::post('/vendedores/editar', [SellerController::class, 'update'], [OperatorMiddleware::class]);
Router::post('/vendedores/toggle', [SellerController::class, 'toggleStatus'], [OperatorMiddleware::class]);

Router::get('/relatorios', function(): void {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=relatorios' . $queryString);
}, [AuthMiddleware::class]);
Router::get('/relatorios/gerencial', function(): void {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=gerencial' . $queryString);
}, [AuthMiddleware::class]);
Router::get('/relatorios/vendedor', [ReportController::class, 'sellerStatement'], [AuthMiddleware::class]);
Router::get('/relatorios/termo-caixa', [ReportController::class, 'cashStatement'], [AuthMiddleware::class]);
Router::get('/relatorios/csv', [ReportController::class, 'exportCsv'], [AuthMiddleware::class]);

Router::get('/operadores', function(): void {
    Response::redirect('/configuracoes?tab=operadores');
}, [AdminMiddleware::class]);
Router::post('/operadores/novo', [UserController::class, 'store'], [AdminMiddleware::class]);
Router::post('/operadores/editar', [UserController::class, 'update'], [AdminMiddleware::class]);
Router::post('/operadores/status', [UserController::class, 'toggleStatus'], [AdminMiddleware::class]);

Router::get('/configuracoes', [SettingsController::class, 'index'], [AdminMiddleware::class]);
Router::post('/configuracoes/precos', [SettingsController::class, 'updatePricing'], [AdminMiddleware::class]);
Router::post('/configuracoes/planejamento', [SettingsController::class, 'updatePlanning'], [AdminMiddleware::class]);
Router::post('/configuracoes/precos/excluir', [SettingsController::class, 'deletePricing'], [MasterMiddleware::class]);
Router::post('/configuracoes/parametros', [SettingsController::class, 'updateParameters'], [AdminMiddleware::class]);
Router::post('/configuracoes/pix', [SettingsController::class, 'updatePix'], [AdminMiddleware::class]);
Router::post('/configuracoes/limpar-banco', [SettingsController::class, 'cleanDatabase'], [MasterMiddleware::class]);

Router::get('/auditoria', function(): void {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
    Response::redirect('/painel?tab=auditoria' . $queryString);
}, [AdminMiddleware::class]);

Router::get('/backup', function(): void {
    Response::redirect('/configuracoes?tab=backup');
}, [MasterMiddleware::class]);
Router::get('/backup/exportar', [BackupController::class, 'export'], [MasterMiddleware::class]);
Router::post('/backup/restaurar', [BackupController::class, 'restore'], [MasterMiddleware::class]);

Router::get('/api/ping', function(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['status' => 'online', 'time' => time()], JSON_UNESCAPED_UNICODE);
    exit;
});

Router::get('/comprar', [OnlineSalesController::class, 'showCheckout']);
Router::post('/comprar', [OnlineSalesController::class, 'processCheckout']);
Router::get('/pedido/{orderCode}', [OnlineSalesController::class, 'showOrder']);
Router::get('/pedido/{orderCode}/status', [OnlineSalesController::class, 'orderStatusJson']);
Router::post('/pedido/{orderCode}/confirmar', [OnlineSalesController::class, 'confirmPayment'], [OperatorMiddleware::class]);

Router::get('/v/{token}', [ValidationController::class, 'validateByToken']);
Router::get('/validar', [ValidationController::class, 'manualValidation']);
Router::post('/validar', [ValidationController::class, 'manualValidation']);

Router::get('/cartelas', [TicketController::class, 'index'], [AuthMiddleware::class]);
Router::get('/cartelas/imprimir/{secureToken}', [TicketController::class, 'printTicket']);
Router::post('/cartelas/invalidar', [TicketController::class, 'invalidate'], [OperatorMiddleware::class]);
Router::post('/cartelas/gerar-lote', [TicketController::class, 'generateBatch'], [AdminMiddleware::class]);

Router::get('/sorteio', [DrawController::class, 'index'], [AuthMiddleware::class]);
Router::get('/admin/sorteio', [DrawController::class, 'index'], [AuthMiddleware::class]);
Router::post('/sorteio/cantar', [DrawController::class, 'call'], [DrawOperatorMiddleware::class]);
Router::post('/sorteio/desfazer', [DrawController::class, 'undo'], [DrawOperatorMiddleware::class]);
Router::get('/sorteio/inteligencia', [DrawController::class, 'intelligenceJson'], [AuthMiddleware::class]);
Router::post('/sorteio/ganhador/contato', [DrawController::class, 'winnerContact'], [DrawOperatorMiddleware::class]);
Router::post('/sorteio/alterar-premio', [DrawController::class, 'changePrize'], [DrawOperatorMiddleware::class]);
Router::post('/sorteio/ganhador/fisico', [DrawController::class, 'registerPhysicalWinner'], [DrawOperatorMiddleware::class]);
Router::post('/sorteio/homologar', [DrawController::class, 'homologate'], [AdminMiddleware::class]);

Router::get('/auditoria/consultas', [AuditController::class, 'personalDataAccessLogs'], [AdminMiddleware::class]);

Router::dispatch();
