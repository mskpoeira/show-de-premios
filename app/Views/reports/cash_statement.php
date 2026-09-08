<?php
use App\Core\View;

$day = $statement['day'];
$summary = $statement['summary'];
$rounds = $statement['rounds'];
$movements = $statement['cash_movements'];
$isClosed = $day['status'] === 'CLOSED';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">📑 Termo de Fechamento de Caixa</h1>
        <p class="page-subtitle">Documento contábil para prestação de contas da paróquia e arquivamento físico.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= View::url('dia?id=' . $day['id']) ?>" class="btn btn-secondary">
            ← Voltar ao Dia
        </a>
        <button type="button" class="btn btn-success" onclick="shareCashWhatsApp()">
            📱 Compartilhar no WhatsApp
        </button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir Termo A4
        </button>
    </div>
</div>

<!-- Statement Paper Sheet -->
<div class="card" style="padding: 2.5rem; background: #ffffff;">

    <!-- Print Header -->
    <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 1.25rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.6rem; font-weight: 900; color: #0f172a; text-transform: uppercase;">
            TERMO DE ENCERRAMENTO E CONCILIAÇÃO DE CAIXA
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            Sistema Show de Prêmios &bull; Operação de <strong><?= View::date($day['operation_date']) ?></strong>
            &bull; Status: <strong><?= $day['status'] === 'CLOSED' ? 'FECHADO E CONCILIADO' : 'EM OPERAÇÃO' ?></strong>
        </p>
    </div>

    <!-- Metadata Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; background: var(--bg-alt); padding: 1.25rem; border-radius: 12px;">
        <div>
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Abertura do Dia:</span><br>
            <strong><?= View::datetime($day['opened_at']) ?></strong><br>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Por: <?= View::e($day['opener_name'] ?? 'Operador') ?></span>
        </div>
        <div>
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Fechamento do Dia:</span><br>
            <strong><?= $day['closing_time'] ? View::datetime($day['closing_time']) : ($day['closed_at'] ? View::datetime($day['closed_at']) : 'Em aberto') ?></strong><br>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Por: <?= View::e($day['closer_name'] ?? '-') ?></span>
        </div>
        <div>
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Fundo de Troco Inicial:</span><br>
            <strong style="font-size: 1.2rem; font-family: monospace; color: var(--primary);"><?= View::money($day['initial_cash']) ?></strong>
        </div>
        <div>
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total de Rodadas:</span><br>
            <strong style="font-size: 1.2rem; color: var(--text-main);"><?= count($rounds) ?> rodada(s)</strong>
        </div>
    </div>

    <!-- Conciliation Table -->
    <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
        📊 Demonstração Contábil da Conciliação
    </h3>

    <table class="table" style="font-size: 1rem; margin-bottom: 2rem;">
        <tbody>
            <tr>
                <td style="font-weight: 600;">(+) Fundo de Caixa Inicial (Troco)</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700;"><?= View::money($summary['initial_cash']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">(+) Total Arrecadado em Vendas de Cartelas (<?= $summary['total_quantity'] ?> cartelas)</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700; color: var(--success);"><?= View::money($summary['total_sales']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">(+) Entradas / Suplementações Avulsas de Caixa</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700;"><?= View::money($summary['total_cash_in']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">(-) Total de Prêmios Efetivamente Pagos aos Ganhadores</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700; color: var(--danger);">- <?= View::money($summary['total_prizes']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: 600;">(-) Retiradas / Sangrias / Despesas Operacionais</td>
                <td style="text-align: right; font-family: monospace; font-weight: 700; color: var(--danger);">- <?= View::money($summary['total_cash_out']) ?></td>
            </tr>
            <tr style="background: #f1f5f9; font-weight: 800; font-size: 1.1rem; border-top: 2px solid #0f172a;">
                <td>(=) SALDO DE CAIXA ESPERADO NO ENCERRAMENTO</td>
                <td style="text-align: right; font-family: monospace; color: var(--primary);"><?= View::money($summary['expected_cash']) ?></td>
            </tr>
            <tr style="background: #e2e8f0; font-weight: 800; font-size: 1.1rem;">
                <td>(=) SALDO FÍSICO CONTADO EM DINHEIRO</td>
                <td style="text-align: right; font-family: monospace; color: var(--text-main);">
                    <?= View::money($day['counted_cash'] ?? $summary['expected_cash']) ?>
                </td>
            </tr>
            <?php
            $diff = (float)($day['difference'] ?? 0.00);
            $diffColor = abs($diff) <= 0.01 ? 'var(--success)' : ($diff > 0 ? '#b45309' : 'var(--danger)');
            ?>
            <tr style="background: #f8fafc; font-weight: 900; font-size: 1.15rem;">
                <td>DIFERENÇA APURADA (Contado - Esperado):</td>
                <td style="text-align: right; font-family: monospace; color: <?= $diffColor ?>;">
                    <?= ($diff > 0 ? '+' : '') . View::money($diff) ?>
                    <span style="font-size: 0.85rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: var(--bg-alt); margin-left: 0.5rem;">
                        <?= abs($diff) <= 0.01 ? 'EXATO (OK)' : ($diff > 0 ? 'SOBRA' : 'FALTA') ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Justification if any -->
    <?php if (!empty($day['justification'])): ?>
        <div style="background: #fffbeb; border-left: 4px solid var(--warning); padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
            <strong style="color: #b45309; text-transform: uppercase; font-size: 0.85rem;">Justificativa Registrada da Divergência:</strong>
            <p style="margin-top: 0.25rem; font-style: italic; color: #78350f;"><?= nl2br(View::e($day['justification'])) ?></p>
        </div>
    <?php endif; ?>

    <!-- Rounds Summary Table -->
    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem;">
        Resumo de Cada Rodada
    </h3>

    <table class="table" style="font-size: 0.9rem; margin-bottom: 2rem;">
        <thead>
            <tr>
                <th>Rodada</th>
                <th style="text-align: center;">Qtd Vendida</th>
                <th style="text-align: right;">Total Vendas</th>
                <th style="text-align: right;">1º Prêmio</th>
                <th style="text-align: right;">2º Prêmio</th>
                <th style="text-align: right;">Lucro Bruto</th>
                <th style="text-align: center;">Ganhador(a)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rounds as $r): 
                $rSales = (float)$r['total_sales'];
                $rPrizes = (float)$r['prize_1'] + (float)$r['prize_2'];
                $rProfit = $rSales - $rPrizes;
            ?>
            <tr>
                <td><strong>Rodada <?= $r['round_number'] ?></strong></td>
                <td style="text-align: center;"><?= $r['total_quantity'] ?> un.</td>
                <td style="text-align: right; font-family: monospace;"><?= View::money($rSales) ?></td>
                <td style="text-align: right; font-family: monospace;"><?= View::money($r['prize_1']) ?></td>
                <td style="text-align: right; font-family: monospace;"><?= View::money($r['prize_2']) ?></td>
                <td style="text-align: right; font-family: monospace; font-weight: 700; color: var(--success);"><?= View::money($rProfit) ?></td>
                <td style="text-align: center; font-style: italic; color: var(--text-muted);"><?= View::e($r['winner_name'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signatures -->
    <div style="margin-top: 4.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; text-align: center;">
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Operador(a) do Caixa</strong><br>
            <span style="font-size: 0.8rem; color: #64748b;">Responsável pela Conferência</span>
        </div>
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Coordenação Geral</strong><br>
            <span style="font-size: 0.8rem; color: #64748b;">Show de Prêmios</span>
        </div>
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Tesouraria / Conselho</strong><br>
            <span style="font-size: 0.8rem; color: #64748b;">Visto e Aprovado</span>
        </div>
    </div>

</div>

<script>
function shareCashWhatsApp() {
    const text = `*🏆 SHOW DE PRÊMIOS — FECHAMENTO DE CAIXA*\n` +
                 `*Data:* <?= View::date($day['operation_date']) ?>\n` +
                 `*Status:* <?= $day['status'] === 'CLOSED' ? 'FECHADO' : 'EM OPERAÇÃO' ?>\n` +
                 `------------------------------------\n` +
                 `*Troco Inicial:* <?= View::money($summary['initial_cash']) ?>\n` +
                 `*Total Vendas:* <?= View::money($summary['total_sales']) ?> (<?= $summary['total_quantity'] ?> cartelas)\n` +
                 `*Prêmios Pagos:* <?= View::money($summary['total_prizes']) ?>\n` +
                 `*Sangrias/Saídas:* <?= View::money($summary['total_cash_out']) ?>\n` +
                 `------------------------------------\n` +
                 `*Caixa Esperado:* <?= View::money($summary['expected_cash']) ?>\n` +
                 `*Caixa Contado:* <?= View::money($day['counted_cash'] ?? $summary['expected_cash']) ?>\n` +
                 `*Diferença:* <?= ($diff > 0 ? '+' : '') . View::money($diff) ?> (<?= abs($diff) <= 0.01 ? 'OK' : ($diff > 0 ? 'SOBRA' : 'FALTA') ?>)\n` +
                 `*Lucro do Evento:* <?= View::money($summary['profit']) ?> (Margem: <?= View::percent($summary['margin']) ?>)\n` +
                 `------------------------------------\n` +
                 `_Fechamento concluído com sucesso._`;

    const url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}
</script>
