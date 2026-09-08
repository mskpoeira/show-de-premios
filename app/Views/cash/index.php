<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$tab = $tab ?? 'fluxo';
$dayData = $day;
$summary = $dayReport['summary'] ?? [];
$rounds = $dayReport['rounds'] ?? [];
$reportSellers = $dayReport['sellers'] ?? [];

$bundleQty = (int)($pricingRule['bundle_quantity'] ?? 3);
$bundlePrice = (float)($pricingRule['bundle_price'] ?? 5.00);
$singlePrice = (float)($pricingRule['single_price'] ?? 2.00);
?>

<div class="page-header no-print">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title">💵 Caixa & Operação Diária</h1>
            <span class="badge <?= $dayData['status'] === 'OPEN' ? 'badge-open' : 'badge-closed' ?>" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">
                <?= $dayData['status'] === 'OPEN' ? '🟢 DIA ABERTO' : '🔒 DIA FECHADO' ?>
            </span>
            <span style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600;">
                &bull; Data: <strong><?= View::date($dayData['operation_date']) ?></strong>
            </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Central unificada de fluxo de caixa, rodadas do dia, contagem de cédulas e simulação financeira.
        </p>
    </div>

    <!-- Seletor de dia e ações rápidas -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <?php if (!empty($allDays) && count($allDays) > 1): ?>
            <form method="GET" action="<?= View::url('caixa') ?>" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <input type="hidden" name="tab" value="<?= View::e($tab) ?>">
                <select name="day_id" class="form-control" style="padding: 0.45rem 0.75rem; font-size: 0.875rem; font-weight: 700; width: auto;" onchange="this.form.submit()">
                    <?php foreach ($allDays as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $dayData['id'] ? 'selected' : '' ?>>
                            📅 <?= View::date($d['operation_date']) ?> (<?= $d['status'] === 'OPEN' ? 'Aberto' : 'Fechado' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>

        <?php if ($dayData['status'] === 'OPEN'): ?>
            <?php if (!empty($openRound)): ?>
                <a href="<?= View::url('rodada?id=' . $openRound['id']) ?>" class="btn btn-primary btn-sm">
                    ⚡ Operar Rodada <?= $openRound['round_number'] ?>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('modalNewRound').showModal()">
                    + Nova Rodada
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <a href="<?= View::url('relatorios/termo-caixa?day_id=' . $dayData['id']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Imprimir Termo de Fechamento de Caixa">
            📑 Termo de Caixa
        </a>
        <button type="button" class="btn btn-success btn-sm" onclick="shareDayWhatsApp()" title="Compartilhar resumo no WhatsApp">
            📱 WhatsApp
        </button>
        <a href="<?= View::url('telao') ?>" target="_blank" class="btn btn-warning btn-sm" title="Abrir telão público">
            📺 Telão ↗
        </a>
    </div>
</div>

<!-- Sub-Abas Unificadas do Caixa (Botões Visuais) -->
<div class="nav-subtabs no-print">
    <a href="<?= View::url('caixa?day_id=' . $dayData['id'] . '&tab=fluxo') ?>" class="tab-pill <?= $tab === 'fluxo' ? 'active' : '' ?>" role="button">
        💵 Fluxo de Caixa
        <?php if (!empty($rounds)): ?>
            <span class="tab-badge"><?= count($rounds) ?> rodada<?= count($rounds) > 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= View::url('caixa?day_id=' . $dayData['id'] . '&tab=contador') ?>" class="tab-pill <?= $tab === 'contador' ? 'active' : '' ?>" role="button">
        🧮 Contador de Cédulas
    </a>
    <a href="<?= View::url('caixa?day_id=' . $dayData['id'] . '&tab=simulador') ?>" class="tab-pill <?= $tab === 'simulador' ? 'active' : '' ?>" role="button">
        🔮 Simulador de Vendas & Lucro
    </a>
</div>

<!-- ========================================================================= -->
<!-- SUB-ABA: FLUXO DE CAIXA, OPERAÇÃO DO DIA, RODADAS & FECHAMENTO UNIFICADOS -->
<!-- ========================================================================= -->
<?php if ($tab === 'fluxo'): ?>

    <!-- Cash Status & Daily Operations KPI Cards (Unificado e sem repetições) -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 3px solid #64748b;">
            <div class="kpi-label">Fundo Inicial (Troco)</div>
            <div class="kpi-value"><?= View::money($cash['initial_cash']) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #10b981;">
            <div class="kpi-label">Vendas Totais (+)</div>
            <div class="kpi-value positive"><?= View::money($cash['total_sales']) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #3b82f6;">
            <div class="kpi-label">Cartelas Vendidas</div>
            <div class="kpi-value"><?= number_format($summary['total_qty'] ?? 0, 0, ',', '.') ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #ef4444;">
            <div class="kpi-label">Prêmios Pagos (-)</div>
            <div class="kpi-value negative"><?= View::money($cash['total_prizes']) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #f59e0b;">
            <div class="kpi-label">Sangrias / Retiradas (-)</div>
            <div class="kpi-value" style="color: #b45309;"><?= View::money($cash['withdrawals']) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #059669;">
            <div class="kpi-label">Lucro do Dia</div>
            <div class="kpi-value <?= ($summary['profit'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <?= View::money($summary['profit'] ?? 0) ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                Margem: <strong><?= View::percent($summary['margin'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #2563eb;">
            <div class="kpi-label">Caixa Esperado (Geral)</div>
            <div class="kpi-value" style="color: #1e40af;"><?= View::money($cash['expected_cash']) ?></div>
        </div>
        <div class="kpi-card" style="border-top: 3px solid #8b5cf6;">
            <div class="kpi-label">Caixa Contado Físico</div>
            <div class="kpi-value" style="color: #6d28d9;">
                <?= $cash['counted_cash'] !== null ? View::money($cash['counted_cash']) : 'Pendente' ?>
            </div>
        </div>
    </div>

    <!-- Breakdown por Forma de Pagamento (Dinheiro, PIX, Débito, Crédito) -->
    <div class="card" style="border-top: 4px solid #3b82f6;">
        <div class="card-title">
            <span>💳 Arrecadação por Forma de Pagamento</span>
            <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">Conciliação financeira e extrato</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 0.5rem;">
            <?php 
            $methodIcons = [
                'CASH' => ['icon' => '💵', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
                'PIX' => ['icon' => '⚡', 'color' => '#0284c7', 'bg' => '#f0f9ff', 'border' => '#bae6fd'],
                'DEBIT' => ['icon' => '💳', 'color' => '#7c3aed', 'bg' => '#faf5ff', 'border' => '#e9d5ff'],
                'CREDIT' => ['icon' => '💳', 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'],
            ];
            foreach (($cash['methods'] ?? []) as $mKey => $mInfo): 
                $style = $methodIcons[$mKey] ?? ['icon' => '💰', 'color' => '#475569', 'bg' => '#f8fafc', 'border' => '#e2e8f0'];
            ?>
                <div style="background: <?= $style['bg'] ?>; border: 1px solid <?= $style['border'] ?>; border-radius: 10px; padding: 1.1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-weight: 800; font-size: 1.05rem; color: <?= $style['color'] ?>;">
                            <?= $style['icon'] ?> <?= View::e($mInfo['name']) ?>
                        </span>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.4rem;">
                        <?= View::money($mInfo['total']) ?>
                    </div>
                    <div style="font-size: 0.825rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.2rem;">
                        <span>Entradas/Vendas: <strong><?= View::money($mInfo['inflows'] + $mInfo['sales']) ?></strong></span>
                        <?php if ($mInfo['outflows'] > 0): ?>
                            <span style="color: #dc2626;">Saídas/Prêmios: <strong>-<?= View::money($mInfo['outflows']) ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Rodadas do Dia (Unificado no Fluxo de Caixa) -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <span>🎯 Rodadas do Dia (<?= count($rounds) ?>)</span>
            <?php if ($dayData['status'] === 'OPEN' && empty($openRound)): ?>
                <button class="btn btn-sm btn-success" onclick="document.getElementById('modalNewRound').showModal()">
                    + Criar Nova Rodada
                </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Rodada</th>
                        <th>Cor da Cartela</th>
                        <th>Status</th>
                        <th class="text-right">Cartelas</th>
                        <th class="text-right">Total Vendas</th>
                        <th class="text-right">Prêmios</th>
                        <th>Ganhador(a)</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rounds)): ?>
                        <tr><td colspan="8" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhuma rodada criada para este dia de operação.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rounds as $r): ?>
                            <tr>
                                <td>
                                    <strong>Rodada <?= $r['round_number'] ?></strong>
                                    <?php if (!empty($r['round_name'])): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= View::e($r['round_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['card_color'])): ?>
                                        <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #facc15;">
                                            <?= View::e($r['card_color']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $r['status'] === 'OPEN' ? 'badge-open' : ($r['status'] === 'IN_PROGRESS' ? 'badge-warning' : 'badge-closed') ?>">
                                        <?= match($r['status']) {
                                            'OPEN' => 'ABERTA (Vendas)',
                                            'IN_PROGRESS' => 'EM ANDAMENTO (Cantoria)',
                                            'PAUSED' => 'PAUSADA',
                                            'CHECKING' => 'EM CONFERÊNCIA',
                                            'CLOSED' => 'FECHADA',
                                            default => $r['status']
                                        } ?>
                                    </span>
                                </td>
                                <td class="text-right font-mono"><?= number_format($r['total_quantity'] ?? 0, 0, ',', '.') ?></td>
                                <td class="text-right font-mono font-bold"><?= View::money($r['total_sales'] ?? 0) ?></td>
                                <td class="text-right font-mono" style="color: #dc2626;"><?= View::money(($r['prize_1'] ?? 0) + ($r['prize_2'] ?? 0) + ($r['prize_3'] ?? 0)) ?></td>
                                <td>
                                    <?php if (!empty($r['winner_name'])): ?>
                                        <strong>🏆 <?= View::e($r['winner_name']) ?></strong>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div style="display: inline-flex; gap: 0.35rem;">
                                        <a href="<?= View::url('rodada?id=' . $r['id']) ?>" class="btn btn-sm btn-primary">
                                            <?= $r['status'] === 'CLOSED' ? '👁️ Ver Vendas' : '⚡ Operar' ?>
                                        </a>
                                        <a href="<?= View::url('locutor?round_id=' . $r['id']) ?>" class="btn btn-sm btn-secondary" style="color: #7c3aed;" title="Painel do Locutor para esta rodada">
                                            🎤 Locutor
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Adicionar Movimentação de Caixa -->
    <?php if ($dayData['status'] === 'OPEN'): ?>
    <div class="card">
        <div class="card-title">+ Registrar Movimentação no Caixa (Dinheiro, PIX, Cartão, Sangria)</div>

        <form method="POST" action="<?= View::url('caixa/adicionar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" name="operation_day_id" value="<?= $dayData['id'] ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div class="form-group">
                    <label class="form-label" for="type">Tipo de Movimentação</label>
                    <select id="type" name="type" class="form-control" required>
                        <option value="INFLOW">Entrada / Acerto (+)</option>
                        <option value="WITHDRAWAL">Sangria / Retirada (-)</option>
                        <option value="OUTFLOW">Despesa / Outra Saída (-)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="payment_method">Forma de Pagamento</label>
                    <select id="payment_method" name="payment_method" class="form-control" required style="font-weight: 700;">
                        <option value="CASH">💵 Dinheiro em Espécie</option>
                        <option value="PIX">⚡ PIX (Transferência / QR Code)</option>
                        <option value="DEBIT">💳 Cartão de Débito (Maquininha)</option>
                        <option value="CREDIT">💳 Cartão de Crédito (Maquininha)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="amount">Valor (R$)</label>
                    <input type="text" id="amount" name="amount" class="form-control editable-input" required placeholder="0,00" style="font-weight: 800; font-size: 1.15rem;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="seller_id">Vendedor Associado (Opcional)</label>
                    <select id="seller_id" name="seller_id" class="form-control">
                        <option value="">Nenhum (Caixa Central)</option>
                        <?php foreach (($sellers ?? []) as $sl): ?>
                            <option value="<?= $sl['id'] ?>"><?= View::e($sl['name']) ?><?= !empty($sl['nickname']) ? ' (' . View::e($sl['nickname']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label" for="reason">Motivo / Descrição Detalhada</label>
                    <input type="text" id="reason" name="reason" class="form-control" required placeholder="Ex: Acerto de vendas do vendedor via PIX, Sangria para o cofre, Compra de suprimentos...">
                </div>

                <div class="form-group" style="grid-column: 1 / -1; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        💾 Salvar Movimentação no Caixa
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Movements History -->
    <div class="card">
        <div class="card-title">Histórico de Movimentações do Caixa</div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data & Horário</th>
                        <th>Tipo</th>
                        <th>Forma de Pagamento</th>
                        <th>Motivo</th>
                        <th>Registrado Por</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="6" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhuma movimentação avulsa registrada no caixa deste dia.</td></tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): 
                            $pMethod = strtoupper($m['payment_method'] ?? 'CASH');
                            $methodBadge = match($pMethod) {
                                'PIX' => ['label' => '⚡ PIX', 'bg' => '#e0f2fe', 'color' => '#0369a1'],
                                'DEBIT' => ['label' => '💳 Débito', 'bg' => '#f3e8ff', 'color' => '#7e22ce'],
                                'CREDIT' => ['label' => '💳 Crédito', 'bg' => '#fef3c7', 'color' => '#b45309'],
                                default => ['label' => '💵 Dinheiro', 'bg' => '#dcfce7', 'color' => '#15803d'],
                            };
                        ?>
                        <tr>
                            <td><span style="font-family: monospace; font-weight: 700;"><?= View::datetime($m['created_at']) ?></span></td>
                            <td>
                                <?php if ($m['type'] === 'WITHDRAWAL'): ?>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">Sangria / Retirada</span>
                                <?php elseif ($m['type'] === 'INFLOW'): ?>
                                    <span class="badge" style="background: #dcfce7; color: #166534;">Entrada / Acerto</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #475569;">Despesa / Saída</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $methodBadge['bg'] ?>; color: <?= $methodBadge['color'] ?>;">
                                    <?= $methodBadge['label'] ?>
                                </span>
                            </td>
                            <td><?= View::e($m['reason']) ?></td>
                            <td><?= View::e($m['user_name'] ?? 'Sistema') ?></td>
                            <td class="text-right">
                                <strong style="color: <?= $m['type'] === 'INFLOW' ? '#059669' : '#dc2626' ?>; font-size: 1.05rem;">
                                    <?= $m['type'] === 'INFLOW' ? '+' : '-' ?> <?= View::money($m['amount']) ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fechamento / Reabertura do Dia -->
    <?php if ($dayData['status'] === 'OPEN'): ?>
        <div class="card" style="border: 2px solid #ef4444; background: #fff5f5; margin-top: 1.5rem;">
            <div class="card-title" style="color: #991b1b;">⚠️ Fechamento Oficial do Dia</div>
            <p style="color: #7f1d1d; font-size: 0.9rem; margin-bottom: 1rem;">
                Ao fechar o dia, novas vendas e rodadas serão bloqueadas e o caixa físico será confrontado com o esperado.
            </p>
            <button type="button" class="btn btn-danger" onclick="document.getElementById('modalCloseDay').showModal()">
                🔒 Fechar Dia de Operação
            </button>
        </div>
    <?php elseif (Auth::isAdmin()): ?>
        <div class="card" style="border: 1px solid #cbd5e1; background: #f8fafc; margin-top: 1.5rem;">
            <div class="card-title">Reabertura de Dia Fechado (Administrador)</div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                Você tem privilégio de administrador para reabrir este dia se houver necessidade de ajustes contábeis.
            </p>
            <form method="POST" action="<?= View::url('dias/reabrir') ?>" onsubmit="return confirm('Deseja realmente reabrir este dia de operação?')">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="day_id" value="<?= $dayData['id'] ?>">
                <button type="submit" class="btn btn-warning">
                    🔓 Reabrir Este Dia
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Atalho para Relatório Completo de Vendedores no Painel Gerencial -->
    <div style="display: flex; justify-content: flex-end; margin-top: 1rem; margin-bottom: 1.25rem;">
        <a href="<?= View::url('painel?tab=visao&day_id=' . $dayData['id']) ?>" class="btn btn-secondary btn-sm" style="font-weight: 700;">
            📊 Ver Vendas por Vendedor e Relatórios no Painel Gerencial ➔
        </a>
    </div>

<!-- ========================================================================= -->
<!-- SUB-ABA 2: CONTADOR DE CÉDULAS & MOEDAS -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'contador'): ?>

    <div class="card" style="padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.3rem; font-weight: 800; color: #0f172a;">🧮 Espelho de Contagem Física de Dinheiro</h2>
                <p style="color: var(--text-muted); font-size: 0.875rem;">
                    Digite as quantidades de notas e moedas físicas contadas na gaveta do caixa. O total é somado instantaneamente.
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetCashCounter()">
                    🔄 Zerar Contador
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    🖨️ Imprimir Espelho
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
            <!-- Cédulas Section -->
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    💵 Cédulas (Notas)
                </h3>

                <table class="table" style="font-size: 0.95rem;">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th style="width: 130px; text-align: center;">Quantidade</th>
                            <th style="text-align: right;">Subtotal (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $notes = [
                            ['val' => 200, 'label' => 'R$ 200,00', 'bg' => '#e2e8f0'],
                            ['val' => 100, 'label' => 'R$ 100,00', 'bg' => '#bae6fd'],
                            ['val' => 50,  'label' => 'R$ 50,00',  'bg' => '#fed7aa'],
                            ['val' => 20,  'label' => 'R$ 20,00',  'bg' => '#fef08a'],
                            ['val' => 10,  'label' => 'R$ 10,00',  'bg' => '#fecdd3'],
                            ['val' => 5,   'label' => 'R$ 5,00',   'bg' => '#e9d5ff'],
                            ['val' => 2,   'label' => 'R$ 2,00',   'bg' => '#c7d2fe'],
                        ];
                        foreach ($notes as $n):
                        ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 6px; font-weight: 800; background: <?= $n['bg'] ?>; color: #0f172a;">
                                    <?= $n['label'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <input type="number" min="0" step="1" value="0"
                                       class="form-control editable-input note-input"
                                       data-value="<?= $n['val'] ?>"
                                       style="text-align: center; font-weight: 800; width: 100px; margin: 0 auto;"
                                       onfocus="this.select()"
                                       oninput="calcTotalCash()">
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; font-size: 1.05rem;" id="sub_note_<?= $n['val'] ?>">
                                R$ 0,00
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td colspan="2">Subtotal Cédulas:</td>
                            <td style="text-align: right; color: var(--primary); font-size: 1.1rem;" id="totalNotes">R$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Moedas Section -->
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🪙 Moedas
                </h3>

                <table class="table" style="font-size: 0.95rem;">
                    <thead>
                        <tr>
                            <th>Moeda</th>
                            <th style="width: 130px; text-align: center;">Quantidade</th>
                            <th style="text-align: right;">Subtotal (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $coins = [
                            ['val' => 1.00, 'label' => 'R$ 1,00', 'bg' => '#fef08a'],
                            ['val' => 0.50, 'label' => 'R$ 0,50', 'bg' => '#e2e8f0'],
                            ['val' => 0.25, 'label' => 'R$ 0,25', 'bg' => '#fed7aa'],
                            ['val' => 0.10, 'label' => 'R$ 0,10', 'bg' => '#e2e8f0'],
                            ['val' => 0.05, 'label' => 'R$ 0,05', 'bg' => '#ffedd5'],
                        ];
                        foreach ($coins as $c):
                        ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 6px; font-weight: 800; background: <?= $c['bg'] ?>; color: #0f172a;">
                                    <?= $c['label'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <input type="number" min="0" step="1" value="0"
                                       class="form-control editable-input coin-input"
                                       data-value="<?= $c['val'] ?>"
                                       style="text-align: center; font-weight: 800; width: 100px; margin: 0 auto;"
                                       onfocus="this.select()"
                                       oninput="calcTotalCash()">
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; font-size: 1.05rem;" id="sub_coin_<?= str_replace('.', '_', (string)$c['val']) ?>">
                                R$ 0,00
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td colspan="2">Subtotal Moedas:</td>
                            <td style="text-align: right; color: var(--primary); font-size: 1.1rem;" id="totalCoins">R$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Total Geral Card -->
        <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 12px; color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700;">
                    TOTAL GERAL DE DINHEIRO FÍSICO CONTADO:
                </span>
                <div style="font-size: 2.2rem; font-weight: 900; color: #4ade80; font-family: monospace;" id="grandTotalCash">
                    R$ 0,00
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-success" onclick="copyCashTotalToClipboard()">
                    📋 Copiar Total
                </button>
                <?php if ($dayData['status'] === 'OPEN'): ?>
                    <button type="button" class="btn btn-warning" onclick="sendToCloseDayModal()">
                        ➔ Usar no Fechamento do Dia
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- ========================================================================= -->
<!-- SUB-ABA 4: SIMULADOR DE VENDAS & LUCRO -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'simulador'): ?>

    <?php
    $defaultPoolPct = (int)($settings['prize_pool_percent'] ?? 50);
    $defaultP1Pct = (int)($settings['prize_1_percent'] ?? 65);
    $defaultP2Pct = (int)($settings['prize_2_percent'] ?? 35);
    $defaultRounding = (float)($settings['prize_rounding'] ?? 10.00);
    ?>

    <!-- Active Rule Banner -->
    <div class="card" style="border-left: 4px solid var(--primary); background: #f0f9ff; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <strong>🎟️ Tabela de Preços Vigente:</strong> 
            1 Cartela = <span class="badge badge-ok"><?= View::money($singlePrice) ?></span> &bull; 
            Combo c/ <?= $bundleQty ?> Cartelas = <span class="badge badge-ok"><?= View::money($bundlePrice) ?></span>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            (Sincronizado automaticamente com as Regras de Preços)
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
        <!-- Controls Card -->
        <div class="card" style="padding: 1.75rem;">
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                ⚙️ Parâmetros da Simulação
            </h2>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 800; font-size: 1.05rem;">
                    Expectativa de Vendas da Rodada (R$):
                </label>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="range" min="100" max="10000" step="50" value="1000" id="salesRange" style="flex: 1; height: 10px; accent-color: var(--primary);" oninput="syncFromRange()">
                    <input type="number" min="0" step="10" value="1000" id="salesInput" class="form-control editable-input" style="width: 140px; font-weight: 800; font-size: 1.2rem; text-align: right;" oninput="syncFromInput()">
                </div>
                
                <!-- Quick Preset buttons -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setPreset(500)">R$ 500</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setPreset(1000)">R$ 1.000</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setPreset(1500)">R$ 1.500</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setPreset(2500)">R$ 2.500</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="setPreset(5000)">R$ 5.000</button>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Reserva p/ Premiação:</label>
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <input type="number" min="10" max="90" step="1" value="<?= $defaultPoolPct ?>" id="paramPrizePool" class="form-control editable-input" style="font-weight: 700;" oninput="recalculateSimulation()">
                        <span style="font-weight: 700;">%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Arredondar Múltiplo:</label>
                    <select id="paramRounding" class="form-control editable-input" style="font-weight: 700;" onchange="recalculateSimulation()">
                        <option value="0" <?= $defaultRounding == 0.00 ? 'selected' : '' ?>>R$ 0,00</option>
                        <option value="5" <?= $defaultRounding == 5.00 ? 'selected' : '' ?>>R$ 5,00</option>
                        <option value="10" <?= $defaultRounding == 10.00 ? 'selected' : '' ?>>R$ 10,00</option>
                        <option value="20" <?= $defaultRounding == 20.00 ? 'selected' : '' ?>>R$ 20,00</option>
                        <option value="50" <?= $defaultRounding == 50.00 ? 'selected' : '' ?>>R$ 50,00</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Divisão 1º Prêmio:</label>
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <input type="number" min="50" max="95" step="1" value="<?= $defaultP1Pct ?>" id="paramP1" class="form-control editable-input" style="font-weight: 700;" oninput="syncP1()">
                        <span style="font-weight: 700;">%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Divisão 2º Prêmio:</label>
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <input type="number" min="5" max="50" step="1" value="<?= $defaultP2Pct ?>" id="paramP2" class="form-control auto-input" style="font-weight: 700;" readonly>
                        <span style="font-weight: 700;">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Output Projections Card -->
        <div class="card" style="padding: 1.75rem; background: linear-gradient(to bottom, #ffffff, #f8fafc);">
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                📊 Resultado Projetado
            </h2>

            <div class="kpi-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                <div class="kpi-card" style="border-left: 4px solid var(--primary);">
                    <div class="kpi-label">Vendas Projetadas</div>
                    <div class="kpi-value positive" id="outSales">R$ 1.000,00</div>
                </div>
                <div class="kpi-card" style="border-left: 4px solid #ef4444;">
                    <div class="kpi-label">Premiação Total</div>
                    <div class="kpi-value" style="color: #ef4444;" id="outTotalPrizes">R$ 500,00</div>
                </div>
                <div class="kpi-card" style="border-left: 4px solid #10b981;">
                    <div class="kpi-label">Lucro Bruto</div>
                    <div class="kpi-value positive" id="outProfit">R$ 500,00</div>
                </div>
                <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                    <div class="kpi-label">Margem Efetiva</div>
                    <div class="kpi-value" style="color: #d97706;" id="outMargin">50,0%</div>
                </div>
            </div>

            <!-- Detalhe da Premiação Sugerida -->
            <div style="background: #ffffff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;">
                <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.75rem; text-transform: uppercase;">
                    🏆 Distribuição Recomendada de Prêmios:
                </h4>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border);">
                    <span><strong>1º Prêmio</strong> (Linha Completa / Bingo):</span>
                    <strong style="color: var(--primary); font-size: 1.25rem;" id="outP1">R$ 330,00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span><strong>2º Prêmio</strong> (Quina / 2º Bingo):</span>
                    <strong style="color: var(--primary); font-size: 1.25rem;" id="outP2">R$ 170,00</strong>
                </div>
            </div>

            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                ℹ️ <em>Com base nas regras vigentes, a estimativa de cartelas em circulação para atingir este faturamento gira em torno de <strong id="outCardsEst">600</strong> cartelas vendidas.</em>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAIS: NOVA RODADA & FECHAR DIA -->
<!-- ========================================================================= -->
<?php if ($dayData['status'] === 'OPEN'): ?>
    <!-- Modal Nova Rodada -->
    <dialog id="modalNewRound">
        <form method="POST" action="<?= View::url('rodadas/criar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" name="operation_day_id" value="<?= $dayData['id'] ?>">

            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; color: #0f172a;">
                + Criar Nova Rodada
            </h3>

            <div class="form-group">
                <label class="form-label" for="modal_round_number">Número da Rodada</label>
                <input type="number" id="modal_round_number" name="round_number" value="<?= $nextRoundNumber ?>" class="form-control auto-input" readonly required>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal_round_name">Nome da Rodada (Exibido no Telão)</label>
                <input type="text" id="modal_round_name" name="round_name" value="Rodada <?= $nextRoundNumber ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal_card_color">Cor da Cartela</label>
                <select id="modal_card_color" name="card_color" class="form-control">
                    <option value="">Nenhuma / Padrão</option>
                    <?php foreach ($cardColors as $c): ?>
                        <option value="<?= View::e($c['name']) ?>"><?= View::e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label editable-label" for="modal_prize_1">1º Prêmio (R$)</label>
                    <input type="text" id="modal_prize_1" name="prize_1" class="form-control editable-input" placeholder="0,00" value="0,00">
                </div>
                <div class="form-group">
                    <label class="form-label editable-label" for="modal_prize_2">2º Prêmio (R$)</label>
                    <input type="text" id="modal_prize_2" name="prize_2" class="form-control editable-input" placeholder="0,00" value="0,00">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalNewRound').close()">Cancelar</button>
                <button type="submit" class="btn btn-success">Criar e Iniciar Rodada</button>
            </div>
        </form>
    </dialog>

    <!-- Modal Fechar Dia -->
    <dialog id="modalCloseDay">
        <form method="POST" action="<?= View::url('dias/fechar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" name="day_id" value="<?= $dayData['id'] ?>">

            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; color: #991b1b;">
                🔒 Fechamento Oficial do Dia
            </h3>

            <p style="color: #475569; font-size: 0.9rem; margin-bottom: 1rem;">
                Caixa Esperado pelo Sistema: <strong style="color: #1e40af; font-size: 1.1rem;"><?= View::money($cash['expected_cash']) ?></strong>
            </p>

            <div class="form-group">
                <label class="form-label editable-label" for="modal_counted_cash">
                    Valor Físico em Dinheiro Contado no Caixa (R$)
                </label>
                <input type="text" id="modal_counted_cash" name="counted_cash" class="form-control editable-input" required placeholder="0,00" style="font-size: 1.3rem; font-weight: 800;">
                <small style="color: var(--text-muted);">
                    Dica: Use a sub-aba "🧮 Contador de Cédulas" para somar rapidamente as notas e moedas.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="modal_notes">Observações do Fechamento</label>
                <textarea id="modal_notes" name="notes" class="form-control" rows="3" placeholder="Ex: Sobra de troco, diferença justificada, etc."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCloseDay').close()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Confirmar Fechamento do Dia</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<!-- SCRIPT COMPLETO DO CONTADOR E SIMULADOR -->
<script>
// --- SCRIPT CONTADOR DE CÉDULAS & MOEDAS ---
function calcTotalCash() {
    let totalNotes = 0;
    document.querySelectorAll('.note-input').forEach(input => {
        const val = parseFloat(input.dataset.value) || 0;
        const qty = parseInt(input.value, 10) || 0;
        const sub = val * qty;
        totalNotes += sub;
        const subEl = document.getElementById('sub_note_' + val);
        if (subEl) subEl.textContent = formatBRL(sub);
    });
    const totalNotesEl = document.getElementById('totalNotes');
    if (totalNotesEl) totalNotesEl.textContent = formatBRL(totalNotes);

    let totalCoins = 0;
    document.querySelectorAll('.coin-input').forEach(input => {
        const val = parseFloat(input.dataset.value) || 0;
        const qty = parseInt(input.value, 10) || 0;
        const sub = val * qty;
        totalCoins += sub;
        const idKey = val.toString().replace('.', '_');
        const subEl = document.getElementById('sub_coin_' + idKey);
        if (subEl) subEl.textContent = formatBRL(sub);
    });
    const totalCoinsEl = document.getElementById('totalCoins');
    if (totalCoinsEl) totalCoinsEl.textContent = formatBRL(totalCoins);

    const grand = totalNotes + totalCoins;
    const grandEl = document.getElementById('grandTotalCash');
    if (grandEl) grandEl.textContent = formatBRL(grand);
    window.__currentCashCount = grand;
}

function resetCashCounter() {
    if (confirm('Deseja zerar todas as quantidades contadas?')) {
        document.querySelectorAll('.note-input, .coin-input').forEach(i => i.value = 0);
        calcTotalCash();
    }
}

function copyCashTotalToClipboard() {
    const grand = window.__currentCashCount || 0;
    const txt = formatBRL(grand);
    navigator.clipboard.writeText(txt).then(() => {
        alert('Total de ' + txt + ' copiado para a área de transferência!');
    });
}

function sendToCloseDayModal() {
    const grand = window.__currentCashCount || 0;
    const closeInput = document.getElementById('modal_counted_cash');
    if (closeInput) {
        closeInput.value = grand.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const modal = document.getElementById('modalCloseDay');
    if (modal) {
        modal.showModal();
    }
}

function formatBRL(val) {
    return 'R$ ' + (val || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// --- SCRIPT SIMULADOR FINANCEIRO ---
const singlePrice = <?= json_encode($singlePrice) ?>;
const bundlePrice = <?= json_encode($bundlePrice) ?>;
const bundleQty = <?= json_encode($bundleQty) ?>;

function syncFromRange() {
    const range = document.getElementById('salesRange');
    const input = document.getElementById('salesInput');
    if (range && input) {
        input.value = range.value;
        recalculateSimulation();
    }
}

function syncFromInput() {
    const range = document.getElementById('salesRange');
    const input = document.getElementById('salesInput');
    if (range && input) {
        range.value = input.value;
        recalculateSimulation();
    }
}

function setPreset(val) {
    const range = document.getElementById('salesRange');
    const input = document.getElementById('salesInput');
    if (range && input) {
        range.value = val;
        input.value = val;
        recalculateSimulation();
    }
}

function syncP1() {
    const p1 = parseInt(document.getElementById('paramP1').value, 10) || 65;
    const p2Input = document.getElementById('paramP2');
    if (p2Input) p2Input.value = Math.max(0, 100 - p1);
    recalculateSimulation();
}

function recalculateSimulation() {
    const salesInput = document.getElementById('salesInput');
    if (!salesInput) return;
    const sales = parseFloat(salesInput.value) || 0;
    const poolPct = (parseFloat(document.getElementById('paramPrizePool').value) || 50) / 100;
    const rounding = parseFloat(document.getElementById('paramRounding').value) || 0;
    const p1Pct = (parseFloat(document.getElementById('paramP1').value) || 65) / 100;
    const p2Pct = (parseFloat(document.getElementById('paramP2').value) || 35) / 100;

    let totalPrize = sales * poolPct;
    if (rounding > 0) {
        totalPrize = Math.round(totalPrize / rounding) * rounding;
    }

    let p1 = totalPrize * p1Pct;
    let p2 = totalPrize * p2Pct;
    if (rounding > 0) {
        p1 = Math.round(p1 / rounding) * rounding;
        p2 = Math.max(0, totalPrize - p1);
    }

    const profit = sales - totalPrize;
    const margin = sales > 0 ? (profit / sales) * 100 : 0;
    const cardsEst = Math.round((sales / bundlePrice) * bundleQty);

    document.getElementById('outSales').textContent = formatBRL(sales);
    document.getElementById('outTotalPrizes').textContent = formatBRL(totalPrize);
    document.getElementById('outProfit').textContent = formatBRL(profit);
    document.getElementById('outMargin').textContent = margin.toFixed(1).replace('.', ',') + '%';
    document.getElementById('outP1').textContent = formatBRL(p1);
    document.getElementById('outP2').textContent = formatBRL(p2);
    document.getElementById('outCardsEst').textContent = cardsEst.toLocaleString('pt-BR');
}

function shareDayWhatsApp() {
    const dayDate = <?= json_encode(View::date($dayData['operation_date'])) ?>;
    const totalSales = <?= json_encode(View::money($summary['total_sales'] ?? 0)) ?>;
    const totalQty = <?= json_encode(number_format($summary['total_qty'] ?? 0, 0, ',', '.')) ?>;
    const profit = <?= json_encode(View::money($summary['profit'] ?? 0)) ?>;
    
    const msg = `*RESUMO SHOW DE PRÊMIOS*\n📅 Data: ${dayDate}\n🎟️ Cartelas: ${totalQty}\n💵 Vendas: ${totalSales}\n📈 Lucro Líquido: ${profit}\n\n_Gerado pelo Sistema Oficial Show de Prêmios_`;
    window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(msg), '_blank');
}

// Inicializar contagens ao carregar
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.note-input')) calcTotalCash();
    if (document.getElementById('salesInput')) recalculateSimulation();
});
</script>
