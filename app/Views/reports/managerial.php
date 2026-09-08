<?php
use App\Core\Auth;
use App\Core\View;

$user = Auth::user();
$now = date('d/m/Y H:i');
$isSingleDay = isset($report['day']);
$summary = $report['summary'];
?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="<?= View::url('relatorios') ?>" class="btn btn-secondary">← Voltar aos Relatórios</a>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir Relatório
        </button>
        <button type="button" class="btn btn-success" onclick="window.print()">
            📄 Exportar em PDF
        </button>
    </div>
</div>

<!-- Managerial Report Sheet -->
<div class="card" style="padding: 2.5rem; background: #ffffff;">

    <!-- Print Header -->
    <div class="print-header" style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
            🏆 SHOW DE PRÊMIOS — RELATÓRIO GERENCIAL
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            <?php if ($isSingleDay): ?>
                Operação de <strong><?= View::date($report['day']['operation_date']) ?></strong>
            <?php else: ?>
                Período de <strong><?= View::date($startDate) ?></strong> até <strong><?= View::date($endDate) ?></strong>
            <?php endif; ?>
            &bull; Emissão: <strong><?= $now ?></strong> &bull; Responsável: <strong><?= View::e($user['name'] ?? 'Admin') ?></strong>
        </p>
    </div>

    <!-- 1. Executive Summary -->
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.4rem;">
        1. Resumo Executivo
    </h2>
    <div class="kpi-grid" style="margin-bottom: 1.5rem;">
        <div class="kpi-card">
            <div class="kpi-label">Vendas Totais</div>
            <div class="kpi-value"><?= View::money($summary['total_sales']) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Quantidade Total</div>
            <div class="kpi-value"><?= number_format($summary['total_qty'], 0, ',', '.') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Premiação Paga</div>
            <div class="kpi-value"><?= View::money($summary['total_prizes']) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Lucro Líquido</div>
            <div class="kpi-value <?= $summary['profit'] >= 0 ? 'positive' : 'negative' ?>">
                <?= View::money($summary['profit']) ?>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Margem Operacional</div>
            <div class="kpi-value"><?= View::percent($summary['margin']) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><?= $isSingleDay ? 'Rodadas' : 'Dias' ?></div>
            <div class="kpi-value"><?= $isSingleDay ? $summary['rounds_count'] : $summary['days_count'] ?></div>
        </div>
    </div>

    <!-- 2. Detailed Breakdown -->
    <?php if ($isSingleDay): ?>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.4rem;">
            2. Detalhamento das Rodadas
        </h2>
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Rodada</th>
                        <th class="text-right">Qtd Vendida</th>
                        <th class="text-right">Vendas (R$)</th>
                        <th class="text-right">1º Prêmio</th>
                        <th class="text-right">2º Prêmio</th>
                        <th class="text-right">Total Prêmios</th>
                        <th class="text-right">Lucro</th>
                        <th class="text-right">Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['rounds'] as $r): ?>
                    <tr>
                        <td><strong>Rodada <?= $r['round_number'] ?></strong></td>
                        <td class="text-right"><?= number_format($r['total_qty'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= View::money($r['total_sales']) ?></td>
                        <td class="text-right"><?= View::money($r['prize_1']) ?></td>
                        <td class="text-right"><?= View::money($r['prize_2']) ?></td>
                        <td class="text-right"><?= View::money($r['total_prizes']) ?></td>
                        <td class="text-right" style="color: <?= $r['profit'] >= 0 ? '#059669' : '#dc2626' ?>;">
                            <?= View::money($r['profit']) ?>
                        </td>
                        <td class="text-right"><?= View::percent($r['margin']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Cash Reconciliation -->
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.4rem;">
            3. Conciliação de Caixa
        </h2>
        <?php $cash = $report['cash']; ?>
        <table class="table" style="max-width: 600px; margin-bottom: 1.5rem;">
            <tbody>
                <tr>
                    <td>(+) Caixa / Troco Inicial</td>
                    <td class="text-right"><strong><?= View::money($cash['initial_cash']) ?></strong></td>
                </tr>
                <tr>
                    <td>(+) Total de Vendas em Dinheiro</td>
                    <td class="text-right" style="color: #059669;"><strong>+ <?= View::money($cash['total_sales']) ?></strong></td>
                </tr>
                <tr>
                    <td>(-) Total de Prêmios Pagos</td>
                    <td class="text-right" style="color: #dc2626;"><strong>- <?= View::money($cash['total_prizes']) ?></strong></td>
                </tr>
                <tr>
                    <td>(-) Retiradas / Sangrias Efetuadas</td>
                    <td class="text-right"><strong>- <?= View::money($cash['withdrawals']) ?></strong></td>
                </tr>
                <tr>
                    <td>(+/-) Outras Entradas / Saídas</td>
                    <td class="text-right"><strong><?= View::money($cash['other_inflows'] - $cash['other_outflows']) ?></strong></td>
                </tr>
                <tr style="background: #f1f5f9; font-size: 1.05rem;">
                    <td><strong>(=) Caixa Esperado Final</strong></td>
                    <td class="text-right"><strong><?= View::money($cash['expected_cash']) ?></strong></td>
                </tr>
                <tr style="background: #f8fafc; font-size: 1.05rem;">
                    <td><strong>(=) Caixa Contado Físico</strong></td>
                    <td class="text-right"><strong><?= $cash['counted_cash'] !== null ? View::money($cash['counted_cash']) : 'Pendente' ?></strong></td>
                </tr>
                <tr style="font-size: 1.1rem;">
                    <td><strong>Diferença de Caixa</strong></td>
                    <td class="text-right">
                        <strong style="color: <?= $cash['difference'] == 0 ? '#059669' : '#dc2626' ?>;">
                            <?= View::money($cash['difference']) ?>
                        </strong>
                        <span class="badge <?= $cash['status'] === 'OK' ? 'badge-ok' : 'badge-divergence' ?>" style="margin-left: 0.5rem;">
                            <?= $cash['status'] === 'OK' ? 'CONCILIADO' : 'DIVERGÊNCIA' ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($cash['justification'])): ?>
                <tr>
                    <td colspan="2" style="background: #fffbeb; color: #92400e;">
                        <strong>Justificativa registrada:</strong> <?= View::e($cash['justification']) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>
        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.4rem;">
            2. Resultado Diário Consolidado
        </h2>
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-right">Rodadas</th>
                        <th class="text-right">Qtd Vendida</th>
                        <th class="text-right">Vendas (R$)</th>
                        <th class="text-right">Prêmios (R$)</th>
                        <th class="text-right">Lucro (R$)</th>
                        <th class="text-right">Margem (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['days'] as $d): ?>
                    <tr>
                        <td><strong><?= View::date($d['operation_date']) ?></strong></td>
                        <td class="text-right"><?= $d['rounds_count'] ?></td>
                        <td class="text-right"><?= number_format($d['qty'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= View::money($d['sales']) ?></td>
                        <td class="text-right"><?= View::money($d['prizes']) ?></td>
                        <td class="text-right"><?= View::money($d['profit']) ?></td>
                        <td class="text-right"><?= View::percent($d['margin']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ranking / Seller Performance -->
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.4rem;">
        <?= $isSingleDay ? '4' : '3' ?>. Desempenho e Ranking de Vendedores(as)
    </h2>
    <div class="table-responsive" style="margin-bottom: 2rem;">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vendedor(a)</th>
                    <th class="text-right">Quantidade Vendida</th>
                    <th class="text-right">Total Vendido (R$)</th>
                    <th class="text-right">Participação %</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sellerList = $isSingleDay ? $report['sellers'] : $report['ranking'];
                $rank = 1;
                ?>
                <?php foreach ($sellerList as $s): ?>
                    <?php 
                    $name = $s['seller_name'];
                    $qty = $s['total_qty'];
                    $amount = $s['total_amount'];
                    $share = isset($s['share']) ? $s['share'] : ($summary['total_sales'] > 0 ? ($amount / $summary['total_sales']) * 100 : 0);
                    ?>
                <tr>
                    <td><strong><?= $rank++ ?>º</strong></td>
                    <td><strong><?= View::e($name) ?></strong></td>
                    <td class="text-right"><?= number_format($qty, 0, ',', '.') ?></td>
                    <td class="text-right"><strong><?= View::money($amount) ?></strong></td>
                    <td class="text-right"><?= View::percent($share) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 3rem; page-break-inside: avoid;">
        <div style="border-top: 1px solid #000; text-align: center; padding-top: 0.5rem;">
            <strong>Operador(a) / Caixa</strong><br>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Assinatura</span>
        </div>
        <div style="border-top: 1px solid #000; text-align: center; padding-top: 0.5rem;">
            <strong>Gerência / Administração</strong><br>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Assinatura</span>
        </div>
    </div>

</div>
