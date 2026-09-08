<?php
use App\Core\View;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Relatórios e Consultas</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Consolidação financeira, operacional e rankings</p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= View::url('relatorios/csv?type=vendas') ?>" class="btn btn-secondary">📥 CSV Vendas</a>
        <a href="<?= View::url('relatorios/csv?type=rodadas') ?>" class="btn btn-secondary">📥 CSV Rodadas</a>
        <a href="<?= View::url('relatorios/gerencial') ?>" class="btn btn-primary">📑 Relatório Gerencial Completo</a>
    </div>
</div>

<!-- Tabs Navigation -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;">
    <a href="<?= View::url('relatorios?tab=diario') ?>" class="btn <?= $tab === 'diario' ? 'btn-primary' : 'btn-secondary' ?>">
        📅 Relatório Diário
    </a>
    <a href="<?= View::url('relatorios?tab=periodo') ?>" class="btn <?= $tab === 'periodo' ? 'btn-primary' : 'btn-secondary' ?>">
        🗓️ Relatório por Período
    </a>
    <a href="<?= View::url('relatorios?tab=ranking') ?>" class="btn <?= $tab === 'ranking' ? 'btn-primary' : 'btn-secondary' ?>">
        🏆 Ranking de Vendedores
    </a>
</div>

<?php if ($tab === 'diario'): ?>
    <!-- Daily Report Tab -->
    <div class="card">
        <form method="GET" action="<?= View::url('relatorios') ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <input type="hidden" name="tab" value="diario">
            <div class="form-group" style="margin-bottom: 0; min-width: 250px;">
                <label class="form-label" for="day_select">Selecione o Dia</label>
                <select id="day_select" name="day_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($days as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $selectedDayId == $d['id'] ? 'selected' : '' ?>>
                            <?= View::date($d['operation_date']) ?> (<?= $d['status'] === 'OPEN' ? 'ABERTO' : 'FECHADO' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($dayReport): ?>
            <?php 
            $s = $dayReport['summary']; 
            $cash = $dayReport['cash'];
            ?>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Vendas Totais</div>
                    <div class="kpi-value"><?= View::money($s['total_sales']) ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Quantidade</div>
                    <div class="kpi-value"><?= number_format($s['total_qty'], 0, ',', '.') ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Prêmios Pagos</div>
                    <div class="kpi-value" style="color: #64748b;"><?= View::money($s['total_prizes']) ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Lucro Líquido</div>
                    <div class="kpi-value <?= $s['profit'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= View::money($s['profit']) ?>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Margem</div>
                    <div class="kpi-value"><?= View::percent($s['margin']) ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Caixa Esperado</div>
                    <div class="kpi-value"><?= View::money($cash['expected_cash']) ?></div>
                </div>
            </div>

            <!-- Rounds table -->
            <h3 style="margin-bottom: 0.75rem; font-size: 1.1rem;">Rodadas Realizadas</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rodada</th>
                            <th class="text-right">Qtd Vendida</th>
                            <th class="text-right">Total Vendas</th>
                            <th class="text-right">1º Prêmio</th>
                            <th class="text-right">2º Prêmio</th>
                            <th class="text-right">Lucro</th>
                            <th class="text-right">Margem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dayReport['rounds'] as $r): ?>
                        <tr>
                            <td><strong>Rodada <?= $r['round_number'] ?></strong></td>
                            <td class="text-right"><?= number_format($r['total_qty'], 0, ',', '.') ?></td>
                            <td class="text-right"><?= View::money($r['total_sales']) ?></td>
                            <td class="text-right"><?= View::money($r['prize_1']) ?></td>
                            <td class="text-right"><?= View::money($r['prize_2']) ?></td>
                            <td class="text-right" style="color: <?= $r['profit'] >= 0 ? '#059669' : '#dc2626' ?>;">
                                <?= View::money($r['profit']) ?>
                            </td>
                            <td class="text-right"><?= View::percent($r['margin']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); text-align: center; padding: 2rem;">Nenhum dia disponível.</p>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'periodo'): ?>
    <!-- Period Report Tab -->
    <div class="card">
        <form method="GET" action="<?= View::url('relatorios') ?>" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <input type="hidden" name="tab" value="periodo">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="start">Data Inicial</label>
                <input type="date" id="start" name="start" class="form-control" value="<?= View::e($startDate) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="end">Data Final</label>
                <input type="date" id="end" name="end" class="form-control" value="<?= View::e($endDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar Período</button>
        </form>

        <?php $ps = $periodReport['summary']; ?>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Vendas no Período</div>
                <div class="kpi-value"><?= View::money($ps['total_sales']) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Quantidade Total</div>
                <div class="kpi-value"><?= number_format($ps['total_qty'], 0, ',', '.') ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Prêmios Pagos</div>
                <div class="kpi-value" style="color: #64748b;"><?= View::money($ps['total_prizes']) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Lucro Líquido</div>
                <div class="kpi-value <?= $ps['profit'] >= 0 ? 'positive' : 'negative' ?>">
                    <?= View::money($ps['profit']) ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Margem Média</div>
                <div class="kpi-value"><?= View::percent($ps['margin']) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Dias Operados</div>
                <div class="kpi-value"><?= $ps['days_count'] ?></div>
            </div>
        </div>

        <h3 style="margin-bottom: 0.75rem; font-size: 1.1rem;">Evolução Diária</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-right">Rodadas</th>
                        <th class="text-right">Qtd Vendida</th>
                        <th class="text-right">Total Vendas</th>
                        <th class="text-right">Total Prêmios</th>
                        <th class="text-right">Lucro</th>
                        <th class="text-right">Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($periodReport['days'])): ?>
                        <tr><td colspan="7" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhum dia operado no período informado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($periodReport['days'] as $d): ?>
                        <tr>
                            <td><strong><?= View::date($d['operation_date']) ?></strong></td>
                            <td class="text-right"><?= $d['rounds_count'] ?></td>
                            <td class="text-right"><?= number_format($d['qty'], 0, ',', '.') ?></td>
                            <td class="text-right"><strong><?= View::money($d['sales']) ?></strong></td>
                            <td class="text-right"><?= View::money($d['prizes']) ?></td>
                            <td class="text-right" style="color: <?= $d['profit'] >= 0 ? '#059669' : '#dc2626' ?>;">
                                <?= View::money($d['profit']) ?>
                            </td>
                            <td class="text-right"><?= View::percent($d['margin']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Ranking Tab -->
    <div class="card">
        <div class="card-title">Ranking Geral de Vendedores(as)</div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Posição</th>
                        <th>Vendedor(a)</th>
                        <th>Apelido</th>
                        <th class="text-right">Qtd Vendida</th>
                        <th class="text-right">Total em Vendas</th>
                        <th class="text-right">Participação</th>
                        <th class="text-right">Dias com Venda</th>
                        <th class="text-right">Média Diária</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ranking)): ?>
                        <tr><td colspan="8" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhum registro de vendas localizado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ranking as $rk): ?>
                        <tr>
                            <td><strong><?= $rk['position'] ?>º</strong></td>
                            <td><strong><?= View::e($rk['seller_name']) ?></strong></td>
                            <td><?= View::e($rk['nickname'] ?? '-') ?></td>
                            <td class="text-right"><?= number_format($rk['total_qty'], 0, ',', '.') ?></td>
                            <td class="text-right"><strong><?= View::money($rk['total_amount']) ?></strong></td>
                            <td class="text-right"><?= View::percent($rk['share']) ?></td>
                            <td class="text-right"><?= $rk['days_count'] ?></td>
                            <td class="text-right"><?= View::money($rk['avg_daily']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
