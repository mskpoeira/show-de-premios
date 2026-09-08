<?php
use App\Core\Auth;
use App\Core\View;

$tab = $tab ?? 'visao';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">📊 Painel Gerencial</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Indicadores executivos consolidados, demonstrativo gerencial (DRE), ranking de vendas e auditoria.
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if (!empty($currentOpenDay)): ?>
            <a href="<?= View::url('caixa?day_id=' . $currentOpenDay['id'] . '&tab=fluxo') ?>" class="btn btn-success btn-sm">
                🟢 Operar Dia Aberto (<?= View::date($currentOpenDay['operation_date']) ?>)
            </a>
        <?php else: ?>
            <a href="<?= View::url('dias/abrir') ?>" class="btn btn-primary btn-sm">
                + Abrir Novo Dia
            </a>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm" title="Imprimir relatório gerencial">
            🖨️ Imprimir DRE
        </button>
    </div>
</div>

<!-- Sub-Abas Unificadas do Painel Gerencial (Botões Visuais) -->
<div class="nav-subtabs no-print">
    <a href="<?= View::url('painel?tab=visao') ?>" class="tab-pill <?= $tab === 'visao' ? 'active' : '' ?>" role="button">
        📊 Visão Geral & Relatórios
    </a>
    <?php if (Auth::isAdmin() || Auth::isMasterAdmin()): ?>
        <a href="<?= View::url('painel?tab=auditoria') ?>" class="tab-pill <?= $tab === 'auditoria' ? 'active' : '' ?>" role="button">
            🛡️ Auditoria & Segurança
            <?php if (!empty($auditTotalLogs)): ?>
                <span class="tab-badge"><?= $auditTotalLogs ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- SUB-ABA 1: VISÃO GERAL & RELATÓRIOS CONSOLIDADOS (SEM REPETIÇÕES) -->
<!-- ========================================================================= -->
<?php if ($tab === 'visao'): ?>

    <!-- Active Day Highlight (se houver dia aberto) -->
    <?php if (!empty($currentOpenDay)): ?>
        <div class="card no-print" style="border-left: 4px solid var(--success); background: #f0fdf4; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <span class="badge badge-open">Dia em Andamento</span>
                    <strong style="font-size: 1.15rem; margin-left: 0.5rem; color: #065f46;">Data: <?= View::date($currentOpenDay['operation_date']) ?></strong>
                    <span style="color: var(--text-muted); font-size: 0.9rem; margin-left: 0.5rem;">Fundo de Troco: <?= View::money($currentOpenDay['initial_cash']) ?></span>
                </div>
                <div>
                    <a href="<?= View::url('caixa?day_id=' . $currentOpenDay['id'] . '&tab=fluxo') ?>" class="btn btn-sm btn-primary">Acessar Operação no Caixa ➔</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Barra de Filtro Rápido e Exportação -->
    <div class="card no-print" style="margin-bottom: 1.5rem; padding: 1rem 1.5rem;">
        <form method="GET" action="<?= View::url('painel') ?>" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
            <input type="hidden" name="tab" value="visao">

            <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; min-width: 240px;">
                    <label class="form-label" for="day_select" style="font-weight: 700;">📅 Selecionar Dia para Detalhamento</label>
                    <select id="day_select" name="day_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($days as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $selectedDayId == $d['id'] ? 'selected' : '' ?>>
                                <?= View::date($d['operation_date']) ?> (<?= $d['status'] === 'OPEN' ? 'ABERTO' : 'FECHADO' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= View::url('relatorios/csv?type=vendas') ?>" class="btn btn-secondary btn-sm" title="Baixar relatório de vendas em CSV">📥 CSV Vendas</a>
                <a href="<?= View::url('relatorios/csv?type=rodadas') ?>" class="btn btn-secondary btn-sm" title="Baixar relatório de rodadas em CSV">📥 CSV Rodadas</a>
            </div>
        </form>
    </div>

    <!-- 1. Cards de Indicadores Executivos (KPIs) -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 3px solid #2563eb;">
            <div class="kpi-label">Vendas Totais Acumuladas</div>
            <div class="kpi-value positive"><?= View::money($totalSales) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #3b82f6;">
            <div class="kpi-label">Cartelas Vendidas</div>
            <div class="kpi-value"><?= number_format($totalQty, 0, ',', '.') ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #ef4444;">
            <div class="kpi-label">Prêmios Pagos</div>
            <div class="kpi-value" style="color: #dc2626;"><?= View::money($totalPrizes) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #10b981;">
            <div class="kpi-label">Lucro Líquido Acumulado</div>
            <div class="kpi-value <?= $totalProfit >= 0 ? 'positive' : 'negative' ?>">
                <?= View::money($totalProfit) ?>
            </div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #f59e0b;">
            <div class="kpi-label">Margem Geral de Lucro</div>
            <div class="kpi-value <?= $totalMargin >= 0 ? 'positive' : 'negative' ?>">
                <?= View::percent($totalMargin) ?>
            </div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #8b5cf6;">
            <div class="kpi-label">Vendedores Ativos</div>
            <div class="kpi-value"><?= $activeSellersCount ?></div>
        </div>
    </div>

    <!-- 2. Demonstrativo Gerencial (DRE Consolidado) -->
    <?php if ($dayReport): 
        $s = $dayReport['summary']; 
        $c = $dayReport['cash'];
    ?>
        <div class="card" style="border: 2px solid var(--border); margin-bottom: 1.75rem;">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                <span>📑 Demonstrativo Gerencial (DRE) — Dia <?= View::date($dayReport['day']['operation_date']) ?></span>
                <span class="badge <?= $dayReport['day']['status'] === 'OPEN' ? 'badge-open' : 'badge-closed' ?>">
                    <?= $dayReport['day']['status'] === 'OPEN' ? 'DIA ABERTO' : 'DIA FECHADO' ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th>Conta / Rubrica Operacional</th>
                            <th class="text-right">Valor (R$)</th>
                            <th class="text-right">% Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>(+) Receita Bruta de Vendas</strong> (<?= number_format($s['total_qty'], 0, ',', '.') ?> cartelas)</td>
                            <td class="text-right font-mono font-bold" style="color: #15803d;"><?= View::money($s['total_sales']) ?></td>
                            <td class="text-right font-mono">100,00%</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 2rem; color: var(--text-muted);">
                                ↳ Dinheiro: <?= View::money($c['sales_breakdown']['DINHEIRO'] ?? 0) ?> |
                                PIX: <?= View::money($c['sales_breakdown']['PIX'] ?? 0) ?> |
                                Débito: <?= View::money($c['sales_breakdown']['DEBITO'] ?? 0) ?> |
                                Crédito: <?= View::money($c['sales_breakdown']['CREDITO'] ?? 0) ?>
                            </td>
                            <td class="text-right font-mono text-muted">-</td>
                            <td class="text-right font-mono text-muted">-</td>
                        </tr>
                        <tr style="color: #b91c1c;">
                            <td><strong>(-) Custos de Premiação</strong> (<?= $s['rounds_count'] ?> rodadas)</td>
                            <td class="text-right font-mono font-bold"><?= View::money($s['total_prizes']) ?></td>
                            <td class="text-right font-mono"><?= $s['total_sales'] > 0 ? View::percent(($s['total_prizes'] / $s['total_sales']) * 100) : '-' ?></td>
                        </tr>
                        <tr style="background: <?= $s['profit'] >= 0 ? '#f0fdf4' : '#fef2f2' ?>; font-size: 1.15rem;">
                            <td><strong>(=) Lucro Líquido Operacional do Dia</strong></td>
                            <td class="text-right font-mono font-bold" style="color: <?= $s['profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                <?= View::money($s['profit']) ?>
                            </td>
                            <td class="text-right font-mono font-bold" style="color: <?= $s['profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                <?= View::percent($s['margin']) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. Vendas por Vendedor(a) no Dia (Enviado do Caixa para o Painel Gerencial) -->
    <div class="card" style="margin-bottom: 1.75rem;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>👥 Vendas por Vendedor(a) <?= $dayReport ? ('no Dia ' . View::date($dayReport['day']['operation_date'])) : '' ?></span>
            <a href="<?= View::url('configuracoes?tab=vendedores') ?>" class="btn btn-sm btn-secondary no-print">Gerenciar Vendedores</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Vendedor(a)</th>
                        <th class="text-right">Cartelas</th>
                        <th class="text-right">Valor Total</th>
                        <th class="text-center no-print">Extrato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sellersList = $dayReport['sellers'] ?? [];
                    if (empty($sellersList)): 
                    ?>
                        <tr>
                            <td colspan="4" class="text-center" style="color: var(--text-muted); padding: 2rem;">
                                Nenhuma venda atribuída a vendedores neste dia.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sellersList as $s): ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--text-main);"><?= View::e($s['seller_name'] ?? $s['name']) ?></strong>
                                    <?php if (!empty($s['nickname'])): ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">(<?= View::e($s['nickname']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right font-mono"><?= number_format($s['total_qty'] ?? $s['total_quantity'] ?? 0, 0, ',', '.') ?></td>
                                <td class="text-right font-mono font-bold" style="color: #15803d;"><?= View::money($s['total_amount'] ?? 0) ?></td>
                                <td class="text-center no-print">
                                    <a href="<?= View::url('relatorios/vendedor?seller_id=' . ($s['seller_id'] ?? $s['id']) . '&day_id=' . ($selectedDayId ?? 0)) ?>" class="btn btn-sm btn-secondary" target="_blank" title="Imprimir extrato individual">
                                        📄 Extrato
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Gráficos de Desempenho -->
    <div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">
        <div class="card">
            <div class="card-title">Vendas × Premiação por Dia</div>
            <div style="position: relative; height: 260px;">
                <canvas id="chartSalesPrizes"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Evolução do Lucro por Dia</div>
            <div style="position: relative; height: 260px;">
                <canvas id="chartProfit"></canvas>
            </div>
        </div>
    </div>

    <!-- 5. Histórico dos Dias de Operação -->
    <div class="card">
        <div class="card-title">
            <span>📅 Histórico Geral dos Dias de Operação</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data da Operação</th>
                        <th>Status</th>
                        <th class="text-right">Rodadas</th>
                        <th class="text-right">Cartelas</th>
                        <th class="text-right">Vendas (R$)</th>
                        <th class="text-right">Prêmios (R$)</th>
                        <th class="text-right">Lucro Líquido</th>
                        <th class="text-right">Margem</th>
                        <th class="text-center no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentDays)): ?>
                        <tr><td colspan="9" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhum dia de operação registrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentDays as $d): 
                            $profit = (float)$d['total_sales'] - (float)$d['total_prizes'];
                            $margin = (float)$d['total_sales'] > 0 ? ($profit / (float)$d['total_sales']) * 100 : 0.00;
                        ?>
                        <tr>
                            <td>
                                <strong><?= View::date($d['operation_date']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $d['status'] === 'OPEN' ? 'badge-open' : 'badge-closed' ?>">
                                    <?= $d['status'] === 'OPEN' ? 'ABERTO' : 'FECHADO' ?>
                                </span>
                            </td>
                            <td class="text-right font-mono"><?= (int)$d['rounds_count'] ?></td>
                            <td class="text-right font-mono"><?= number_format($d['total_qty'], 0, ',', '.') ?></td>
                            <td class="text-right font-mono font-bold"><?= View::money($d['total_sales']) ?></td>
                            <td class="text-right font-mono" style="color: #dc2626;"><?= View::money($d['total_prizes']) ?></td>
                            <td class="text-right font-mono" style="color: <?= $profit >= 0 ? '#059669' : '#dc2626' ?>; font-weight: 800;">
                                <?= View::money($profit) ?>
                            </td>
                            <td class="text-right font-mono"><?= View::percent($margin) ?></td>
                            <td class="text-center no-print" style="white-space: nowrap;">
                                <a href="<?= View::url('caixa?day_id=' . $d['id'] . '&tab=fluxo') ?>" class="btn btn-sm btn-secondary" title="Ver operações no Caixa">
                                    💵 Caixa
                                </a>
                                <a href="<?= View::url('painel?tab=visao&day_id=' . $d['id']) ?>" class="btn btn-sm btn-primary" title="Ver no Painel">
                                    🔍 Detalhar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Script de Inicialização dos Gráficos Chart.js -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const labels = <?= $chartLabelsJson ?>;
        const sales = <?= $chartSalesJson ?>;
        const prizes = <?= $chartPrizesJson ?>;
        const profit = <?= $chartProfitJson ?>;

        const ctx1 = document.getElementById('chartSalesPrizes');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Vendas (R$)', data: sales, backgroundColor: '#2563eb', borderRadius: 4 },
                        { label: 'Prêmios (R$)', data: prizes, backgroundColor: '#ef4444', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        const ctx2 = document.getElementById('chartProfit');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Lucro Líquido (R$)',
                        data: profit,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 3,
                        pointBackgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    });
    </script>

<!-- ========================================================================= -->
<!-- SUB-ABA 2: AUDITORIA & SEGURANÇA -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'auditoria' && (Auth::isAdmin() || Auth::isMasterAdmin())): ?>

    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span>🛡️ Trilha de Auditoria do Sistema</span>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">Total: <?= number_format($auditTotalLogs, 0, ',', '.') ?> registros</span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
            Registro imutável de todas as ações sensíveis realizadas por operadores (abertura/fechamento de dia e rodadas, estornos e alterações cadastrais).
        </p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Operador</th>
                        <th>Ação</th>
                        <th>Entidade</th>
                        <th>IP</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="6" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhum registro de auditoria encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.85rem;"><?= View::datetime($log['created_at']) ?></td>
                            <td>
                                <strong><?= View::e($log['user_name'] ?? 'Sistema') ?></strong>
                                <?php if (!empty($log['user_login'])): ?>
                                    <small style="color: var(--text-muted);">(<?= View::e($log['user_login']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= View::e($log['action']) ?></code></td>
                            <td><span class="badge badge-closed"><?= View::e($log['entity_type']) ?> #<?= $log['entity_id'] ?></span></td>
                            <td><small style="color: var(--text-muted);"><?= View::e($log['ip_address']) ?></small></td>
                            <td style="max-width: 320px; word-break: break-all; font-size: 0.8rem;">
                                <?php 
                                    $data = json_decode($log['details'] ?? '', true);
                                    if ($data) {
                                        echo htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                                    } else {
                                        echo View::e($log['details']);
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($auditTotalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap;">
                <?php if ($auditPage > 1): ?>
                    <a href="<?= View::url('painel?tab=auditoria&page=' . ($auditPage - 1)) ?>" class="btn btn-sm btn-secondary">◀ Anterior</a>
                <?php endif; ?>

                <span style="display: inline-flex; align-items: center; padding: 0 1rem; font-size: 0.875rem; color: var(--text-muted);">
                    Página <?= $auditPage ?> de <?= $auditTotalPages ?>
                </span>

                <?php if ($auditPage < $auditTotalPages): ?>
                    <a href="<?= View::url('painel?tab=auditoria&page=' . ($auditPage + 1)) ?>" class="btn btn-sm btn-secondary">Próxima ▶</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
