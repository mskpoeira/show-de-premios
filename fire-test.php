<?php
/**
 * Show de Prêmios — Central da Prova de Fogo
 * Versão de homologação 0.5.0-rc.1
 */
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $path = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
}

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

\App\Core\Session::start();

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

if (!Auth::check()) {
    header('Location: /login');
    exit;
}

$pdo = Database::getConnection();
$driver = Database::getDriver();
$id = match ($driver) {
    'pgsql' => 'SERIAL PRIMARY KEY',
    'mysql' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
    default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
};
$time = $driver === 'sqlite' ? 'TEXT' : 'TIMESTAMP';

$pdo->exec("CREATE TABLE IF NOT EXISTS test_issues (
    id {$id},
    source VARCHAR(30) NOT NULL DEFAULT 'WEB',
    app_version VARCHAR(30) NULL,
    event_id INTEGER NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
    category VARCHAR(50) NOT NULL DEFAULT 'FUNCTIONAL',
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    steps TEXT NULL,
    expected_result TEXT NULL,
    actual_result TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    detected_automatically SMALLINT NOT NULL DEFAULT 0,
    created_by INTEGER NULL,
    created_at {$time} DEFAULT CURRENT_TIMESTAMP,
    updated_at {$time} DEFAULT CURRENT_TIMESTAMP
)");

function redirectSelf(string $message = ''): never {
    $url = '/fire-test.php';
    if ($message !== '') $url .= '?msg=' . rawurlencode($message);
    header('Location: ' . $url);
    exit;
}

function clean(string $value, int $max = 5000): string {
    return mb_substr(trim($value), 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate((string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Token CSRF inválido. Atualize a página e tente novamente.');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_event' && Auth::isAdmin()) {
        $name = clean((string)($_POST['name'] ?? ''), 255);
        $date = clean((string)($_POST['event_date'] ?? ''), 10);
        $timeValue = clean((string)($_POST['event_time'] ?? '20:00'), 5);
        $location = clean((string)($_POST['location'] ?? 'Ubatuba/SP'), 255);
        $modality = strtoupper(clean((string)($_POST['modality'] ?? 'HYBRID'), 20));
        $prefix = strtoupper(clean((string)($_POST['ticket_prefix'] ?? 'JDA'), 10));
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
            redirectSelf('Evento futuro inválido. Informe nome e data igual ou posterior a hoje.');
        }
        if (!in_array($modality, ['DIGITAL_ONLY','PHYSICAL_ONLY','HYBRID'], true)) $modality = 'HYBRID';
        if (!preg_match('/^[A-Z]{3}$/', $prefix)) $prefix = 'JDA';
        $stmt = $pdo->prepare("INSERT INTO events (name,event_date,event_time,location,status,modality,ticket_prefix,single_price,bundle_qty,bundle_price,game_mode,center_free,card_format,tie_rule,is_locked,created_at,updated_at) VALUES (?,?,?,?, 'PLANNED', ?, ?, 2.00, 3, 5.00, 'BINGO_75', 1, '5x5', 'SPLIT', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute([$name,$date,$timeValue,$location,$modality,$prefix]);
        redirectSelf('Evento futuro cadastrado.');
    }

    if ($action === 'activate_event' && Auth::isAdmin()) {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE events SET status='PLANNED', updated_at=CURRENT_TIMESTAMP WHERE status='ACTIVE'");
            $stmt = $pdo->prepare("UPDATE events SET status='ACTIVE', updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$eventId]);
            $pdo->commit();
            redirectSelf('Evento ativado. Web e Windows passarão a usar o mesmo evento ativo.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirectSelf('Falha ao ativar evento.');
        }
    }

    if ($action === 'close_event' && Auth::isAdmin()) {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $pdo->prepare("UPDATE events SET status='CLOSED', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$eventId]);
        redirectSelf('Evento encerrado.');
    }

    if ($action === 'issue' || $action === 'auto_issue') {
        $title = clean((string)($_POST['title'] ?? ''), 180);
        if ($title === '') $title = 'Erro detectado durante teste';
        $severity = strtoupper(clean((string)($_POST['severity'] ?? 'MEDIUM'), 20));
        if (!in_array($severity, ['LOW','MEDIUM','HIGH','CRITICAL'], true)) $severity = 'MEDIUM';
        $category = strtoupper(clean((string)($_POST['category'] ?? 'FUNCTIONAL'), 50));
        $eventId = (int)($_POST['event_id'] ?? 0);
        $versionFile = __DIR__ . '/VERSION';
        $version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : 'dev';
        $stmt = $pdo->prepare("INSERT INTO test_issues (source,app_version,event_id,severity,category,title,description,steps,expected_result,actual_result,status,detected_automatically,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, 'OPEN', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute([
            clean((string)($_POST['source'] ?? 'WEB'), 30),
            $version,
            $eventId > 0 ? $eventId : null,
            $severity,
            $category,
            $title,
            clean((string)($_POST['description'] ?? ''), 5000),
            clean((string)($_POST['steps'] ?? ''), 5000),
            clean((string)($_POST['expected_result'] ?? ''), 5000),
            clean((string)($_POST['actual_result'] ?? ''), 5000),
            $action === 'auto_issue' ? 1 : 0,
            Auth::id(),
        ]);
        if ($action === 'auto_issue') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirectSelf('Problema registrado para correção.');
    }

    if ($action === 'issue_status' && Auth::isAdmin()) {
        $issueId = (int)($_POST['issue_id'] ?? 0);
        $status = strtoupper(clean((string)($_POST['status'] ?? 'OPEN'), 30));
        if (!in_array($status, ['OPEN','IN_PROGRESS','RESOLVED','RETEST','WONT_FIX'], true)) $status = 'OPEN';
        $pdo->prepare("UPDATE test_issues SET status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$status,$issueId]);
        redirectSelf('Status do problema atualizado.');
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prova-de-fogo-problemas.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Origem','Versão','Evento','Severidade','Categoria','Título','Status','Automático','Criado em'], ';');
    foreach ($pdo->query("SELECT ti.*, e.name AS event_name FROM test_issues ti LEFT JOIN events e ON e.id=ti.event_id ORDER BY ti.id DESC") as $row) {
        fputcsv($out, [$row['id'],$row['source'],$row['app_version'],$row['event_name'],$row['severity'],$row['category'],$row['title'],$row['status'],$row['detected_automatically'],$row['created_at']], ';');
    }
    fclose($out);
    exit;
}

$events = $pdo->query("SELECT * FROM events ORDER BY CASE status WHEN 'ACTIVE' THEN 0 WHEN 'PLANNED' THEN 1 ELSE 2 END, event_date ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$futureEvents = array_values(array_filter($events, fn(array $e): bool => ($e['event_date'] ?? '') >= date('Y-m-d') && ($e['status'] ?? '') !== 'CLOSED'));
$issues = $pdo->query("SELECT ti.*, e.name AS event_name FROM test_issues ti LEFT JOIN events e ON e.id=ti.event_id ORDER BY CASE ti.status WHEN 'OPEN' THEN 0 WHEN 'IN_PROGRESS' THEN 1 WHEN 'RETEST' THEN 2 ELSE 3 END, CASE ti.severity WHEN 'CRITICAL' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END, ti.id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$csrf = Csrf::getToken();
$versionFile = __DIR__ . '/VERSION';
$version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : 'dev';
$msg = clean((string)($_GET['msg'] ?? ''), 500);
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Prova de Fogo — Show de Prêmios</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f4f6fb;color:#172033}.wrap{max-width:1500px;margin:auto;padding:24px}.top{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}.badge{background:#111827;color:#fff;padding:6px 10px;border-radius:999px}.ok{background:#dcfce7;border:1px solid #86efac;padding:12px;border-radius:10px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{background:#fff;border:1px solid #dbe1ea;border-radius:14px;padding:18px;box-shadow:0 2px 10px #0001}label{display:block;font-weight:700;margin:8px 0}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px}textarea{min-height:80px}.row{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.btn{display:inline-block;border:0;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none;background:#1d4ed8;color:#fff}.btn.alt{background:#475569}.btn.warn{background:#b45309}.btn.danger{background:#b91c1c}.event,.issue{border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin:10px 0}.critical{border-left:5px solid #b91c1c}.high{border-left:5px solid #ea580c}.medium{border-left:5px solid #d97706}.low{border-left:5px solid #16a34a}.meta{font-size:12px;color:#64748b}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}@media(max-width:900px){.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}}@media print{form,.no-print,.actions{display:none!important}.card{box-shadow:none}}
</style></head><body><div class="wrap">
<div class="top"><div><h1>🧪 Prova de Fogo — Show de Prêmios</h1><div>Versão <strong><?= htmlspecialchars($version) ?></strong> · Web e Windows usando a mesma base quando o app Windows estiver em modo sincronizado.</div></div><div><a class="btn alt" href="/painel">Voltar ao sistema</a> <a class="btn" href="?export=csv">Exportar problemas CSV</a></div></div>
<?php if ($msg !== ''): ?><div class="ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="grid">
<section class="card"><h2>📅 Eventos futuros</h2><p>Todos os eventos com data futura podem ser cadastrados agora e ativados quando chegar a hora.</p>
<?php foreach ($futureEvents as $event): ?><div class="event"><strong><?= htmlspecialchars((string)$event['name']) ?></strong> — <?= htmlspecialchars((string)$event['event_date']) ?> <?= htmlspecialchars((string)$event['event_time']) ?><br><span class="meta"><?= htmlspecialchars((string)$event['location']) ?> · <?= htmlspecialchars((string)$event['modality']) ?> · status <?= htmlspecialchars((string)$event['status']) ?></span>
<?php if (Auth::isAdmin()): ?><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="activate_event"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button class="btn" type="submit">Ativar</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="close_event"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button class="btn danger" type="submit">Encerrar</button></form></div><?php endif; ?></div><?php endforeach; ?>
<?php if (Auth::isAdmin()): ?><hr><h3>Novo evento futuro</h3><form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="create_event"><label>Nome<input name="name" required></label><div class="row"><label>Data<input name="event_date" type="date" min="<?= date('Y-m-d') ?>" required></label><label>Hora<input name="event_time" type="time" value="20:00" required></label></div><label>Local<input name="location" value="Ubatuba/SP"></label><div class="row"><label>Modalidade<select name="modality"><option value="HYBRID">Híbrido</option><option value="DIGITAL_ONLY">Somente digital</option><option value="PHYSICAL_ONLY">Somente física</option></select></label><label>Prefixo<input name="ticket_prefix" maxlength="3" value="JDA"></label></div><button class="btn" type="submit">Cadastrar evento</button></form><?php endif; ?></section>
<section class="card"><h2>🐞 Registrar problema manualmente</h2><form method="post" id="issueForm"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="issue"><input type="hidden" name="source" value="WEB"><label>Título<input name="title" required placeholder="Ex.: tela travou ao confirmar vencedor"></label><div class="row"><label>Severidade<select name="severity"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></label><label>Categoria<select name="category"><option>FUNCTIONAL</option><option>SYNC</option><option>DRAW</option><option>PAYMENT</option><option>REPORT</option><option>SECURITY</option><option>UI</option><option>PERFORMANCE</option><option>OTHER</option></select></label></div><label>Evento<select name="event_id"><option value="0">Sem evento específico</option><?php foreach ($events as $event): ?><option value="<?= (int)$event['id'] ?>"><?= htmlspecialchars((string)$event['name']) ?> — <?= htmlspecialchars((string)$event['event_date']) ?></option><?php endforeach; ?></select></label><label>Descrição<textarea name="description"></textarea></label><label>Passos para reproduzir<textarea name="steps"></textarea></label><div class="row"><label>Resultado esperado<textarea name="expected_result"></textarea></label><label>Resultado obtido<textarea name="actual_result"></textarea></label></div><button class="btn" type="submit">Registrar problema</button></form></section>
</div>
<section class="card" style="margin-top:18px"><div class="top"><h2>📋 Problemas encontrados no teste</h2><button class="btn alt no-print" onclick="window.print()">Imprimir relatório</button></div><?php if (!$issues): ?><p>Nenhum problema registrado até o momento.</p><?php endif; ?><?php foreach ($issues as $issue): ?><div class="issue <?= strtolower((string)$issue['severity']) ?>"><strong>#<?= (int)$issue['id'] ?> · <?= htmlspecialchars((string)$issue['title']) ?></strong><div class="meta"><?= htmlspecialchars((string)$issue['severity']) ?> · <?= htmlspecialchars((string)$issue['category']) ?> · <?= htmlspecialchars((string)$issue['source']) ?> · v<?= htmlspecialchars((string)$issue['app_version']) ?> · <?= htmlspecialchars((string)($issue['event_name'] ?: 'sem evento')) ?> · <?= htmlspecialchars((string)$issue['created_at']) ?><?= (int)$issue['detected_automatically'] === 1 ? ' · AUTO' : '' ?></div><p><?= nl2br(htmlspecialchars((string)$issue['description'])) ?></p><?php if (Auth::isAdmin()): ?><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="issue_status"><input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>"><select name="status" style="width:auto"><option <?= $issue['status']==='OPEN'?'selected':'' ?>>OPEN</option><option <?= $issue['status']==='IN_PROGRESS'?'selected':'' ?>>IN_PROGRESS</option><option <?= $issue['status']==='RETEST'?'selected':'' ?>>RETEST</option><option <?= $issue['status']==='RESOLVED'?'selected':'' ?>>RESOLVED</option><option <?= $issue['status']==='WONT_FIX'?'selected':'' ?>>WONT_FIX</option></select><button class="btn alt" type="submit">Atualizar</button></form><?php endif; ?></div><?php endforeach; ?></section>
</div>
<script>
const csrf = <?= json_encode($csrf) ?>;
let sending = false;
async function autoIssue(title, description) {
  if (sending) return;
  sending = true;
  try {
    const data = new URLSearchParams({ _csrf: csrf, action: 'auto_issue', source: 'WEB', severity: 'HIGH', category: 'UI', title, description, actual_result: description });
    await fetch('/fire-test.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: data.toString(), credentials:'same-origin' });
  } catch (_) {} finally { setTimeout(() => { sending = false; }, 1000); }
}
window.addEventListener('error', e => autoIssue('Erro JavaScript automático', `${e.message} em ${e.filename}:${e.lineno}:${e.colno}`));
window.addEventListener('unhandledrejection', e => autoIssue('Promise rejeitada automaticamente', String(e.reason?.stack || e.reason || 'Motivo desconhecido')));
</script></body></html>
