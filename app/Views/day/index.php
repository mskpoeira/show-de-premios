<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$dayData = $report['day'];
$summary = $report['summary'];
$cash = $report['cash'];
$rounds = $report['rounds'];
$sellers = $report['sellers'];
?>

<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <h1 class="page-title">Operação do Dia: <?= View::date($dayData['operation_date']) ?></h1>
            <span class="badge <?= $dayData['status'] === 'OPEN' ? 'badge-open' : 'badge-closed' ?>">
                <?= $dayData['status'] === 'OPEN' ? '🟢 DIA ABERTO' : '🔒 DIA FECHADO' ?>
            </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Aberto em <?= View::datetime($dayData['opened_at']) ?>
            <?php if ($dayData['closed_at']): ?>
                &bull; Fechado em <?= View::datetime($dayData['closed_at']) ?>
            <?php endif; ?>
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if ($dayData['status'] === 'OPEN'): ?>
            <?php if ($openRound): ?>
                <a href="<?= View::url('rodada?id=' . $openRound['id']) ?>" class="btn btn-primary">
                    ⚡ Operar Rodada <?= $openRound['round_number'] ?> (Aberta)
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-success" onclick="document.getElementById('modalNewRound').showModal()">
                    + Nova Rodada
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <a href="<?= View::url('caixa?day_id=' . $dayData['id']) ?>" class="btn btn-secondary">💵 Caixa</a>
        <a href="<?= View::url('relatorios/termo-caixa?day_id=' . $dayData['id']) ?>" class="btn btn-secondary">📑 Termo de Caixa</a>
        <a href="<?= View::url('relatorios/gerencial?day_id=' . $dayData['id']) ?>" class="btn btn-secondary">📊 Gerencial</a>
        <button type="button" class="btn btn-success" onclick="shareDayWhatsApp()">📱 WhatsApp</button>
        <a href="<?= View::url('telao') ?>" target="_blank" class="btn btn-primary" title="Abrir telão público">📺 Telão</a>
    </div>
</div>

<!-- Daily KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Vendas do Dia</div>
        <div class="kpi-value"><?= View::money($summary['total_sales']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Quantidade Total</div>
        <div class="kpi-value"><?= number_format($summary['total_qty'], 0, ',', '.') ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Prêmios Pagos</div>
        <div class="kpi-value" style="color: #64748b;"><?= View::money($summary['total_prizes']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Lucro do Dia</div>
        <div class="kpi-value <?= $summary['profit'] >= 0 ? 'positive' : 'negative' ?>">
            <?= View::money($summary['profit']) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Margem</div>
        <div class="kpi-value <?= $summary['margin'] >= 0 ? 'positive' : 'negative' ?>">
            <?= View::percent($summary['margin']) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Caixa Esperado</div>
        <div class="kpi-value"><?= View::money($cash['expected_cash']) ?></div>
    </div>
</div>

<!-- Rounds of the Day -->
<div class="card">
    <div class="card-title">
        <span>Rodadas do Dia (<?= count($rounds) ?>)</span>
        <?php if ($dayData['status'] === 'OPEN' && !$openRound): ?>
            <button class="btn btn-sm btn-success" onclick="document.getElementById('modalNewRound').showModal()">
                + Criar Rodada
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Rodada</th>
                    <th>Status</th>
                    <th class="text-right">Qtd Vendida</th>
                    <th class="text-right">Total Vendas</th>
                    <th class="text-right">1º Prêmio</th>
                    <th class="text-right">2º Prêmio</th>
                    <th class="text-right">Lucro</th>
                    <th class="text-right">Margem</th>
                    <th class="text-right">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rounds)): ?>
                    <tr><td colspan="9" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhuma rodada iniciada neste dia. Clique em "+ Nova Rodada" para começar.</td></tr>
                <?php else: ?>
                    <?php foreach ($rounds as $r): ?>
                    <tr>
                        <td><strong>Rodada <?= $r['round_number'] ?></strong></td>
                        <td>
                            <span class="badge <?= $r['status'] === 'OPEN' ? 'badge-open' : 'badge-closed' ?>">
                                <?= $r['status'] === 'OPEN' ? 'ABERTA' : 'FECHADA' ?>
                            </span>
                        </td>
                        <td class="text-right"><?= number_format($r['total_qty'], 0, ',', '.') ?></td>
                        <td class="text-right"><strong><?= View::money($r['total_sales']) ?></strong></td>
                        <td class="text-right"><?= View::money($r['prize_1']) ?></td>
                        <td class="text-right"><?= View::money($r['prize_2']) ?></td>
                        <td class="text-right" style="color: <?= $r['profit'] >= 0 ? '#059669' : '#dc2626' ?>;">
                            <?= View::money($r['profit']) ?>
                        </td>
                        <td class="text-right"><?= View::percent($r['margin']) ?></td>
                        <td class="text-right">
                            <a href="<?= View::url('rodada?id=' . $r['id']) ?>" class="btn btn-sm <?= $r['status'] === 'OPEN' ? 'btn-primary' : 'btn-secondary' ?>">
                                <?= $r['status'] === 'OPEN' ? 'Lançar / Fechar' : 'Consultar' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Sales by Seller Table -->
<div class="card">
    <div class="card-title">Desempenho dos Vendedores(as) no Dia</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Vendedor(a)</th>
                    <th class="text-right">Quantidade Vendida</th>
                    <th class="text-right">Total em Vendas</th>
                    <th class="text-right">Participação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sellers)): ?>
                    <tr><td colspan="4" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhuma venda registrada ainda nas rodadas.</td></tr>
                <?php else: ?>
                    <?php foreach ($sellers as $s): ?>
                        <?php 
                        $share = $summary['total_sales'] > 0 ? ($s['total_amount'] / $summary['total_sales']) * 100 : 0;
                        ?>
                    <tr>
                        <td>
                            <strong><?= View::e($s['seller_name']) ?></strong>
                            <?php if (!empty($s['nickname'])): ?>
                                <small style="color: var(--text-muted);">(<?= View::e($s['nickname']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format($s['total_qty'], 0, ',', '.') ?></td>
                        <td class="text-right"><strong><?= View::money($s['total_amount']) ?></strong></td>
                        <td class="text-right"><?= View::percent($share) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Cash Closing Section -->
<div class="card" style="border-top: 4px solid var(--primary);">
    <div class="card-title">Fechamento do Dia e Conciliação de Caixa</div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <div>
            <div style="font-size: 0.9rem; color: var(--text-muted);">Caixa Inicial:</div>
            <div style="font-size: 1.1rem; font-weight: 600;"><?= View::money($cash['initial_cash']) ?></div>

            <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">Total Vendas (+):</div>
            <div style="font-size: 1.1rem; font-weight: 600; color: #059669;"><?= View::money($cash['total_sales']) ?></div>

            <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">Prêmios Pagos (-):</div>
            <div style="font-size: 1.1rem; font-weight: 600; color: #dc2626;"><?= View::money($cash['total_prizes']) ?></div>
        </div>

        <div>
            <div style="font-size: 0.9rem; color: var(--text-muted);">Retiradas / Sangrias (-):</div>
            <div style="font-size: 1.1rem; font-weight: 600;"><?= View::money($cash['withdrawals']) ?></div>

            <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">Outras Entradas / Saídas:</div>
            <div style="font-size: 1.1rem; font-weight: 600;"><?= View::money($cash['other_inflows'] - $cash['other_outflows']) ?></div>

            <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">Caixa Esperado Final:</div>
            <div style="font-size: 1.3rem; font-weight: 700; color: #1e40af;"><?= View::money($cash['expected_cash']) ?></div>
        </div>

        <div>
            <?php if ($cash['counted_cash'] !== null): ?>
                <div style="font-size: 0.9rem; color: var(--text-muted);">Caixa Contado em Dinheiro:</div>
                <div style="font-size: 1.3rem; font-weight: 700;"><?= View::money($cash['counted_cash']) ?></div>

                <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">Diferença:</div>
                <div style="font-size: 1.2rem; font-weight: 700;" class="<?= $cash['difference'] == 0 ? 'positive' : 'negative' ?>">
                    <?= View::money($cash['difference']) ?>
                    <span class="badge <?= $cash['status'] === 'OK' ? 'badge-ok' : 'badge-divergence' ?>" style="margin-left: 0.5rem;">
                        <?= $cash['status'] === 'OK' ? 'CONCILIADO' : 'DIVERGÊNCIA' ?>
                    </span>
                </div>

                <?php if (!empty($cash['justification'])): ?>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem; background: #f8fafc; padding: 0.5rem; border-radius: var(--radius-sm);">
                        <strong>Justificativa:</strong> <?= View::e($cash['justification']) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 1rem; border-radius: var(--radius); color: #92400e;">
                    ⚠️ Caixa final ainda não conferido para fechamento.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($dayData['status'] === 'OPEN'): ?>
        <form method="POST" action="<?= View::url('dias/fechar') ?>" style="border-top: 1px solid var(--border); padding-top: 1.25rem;">
            <?= Csrf::inputField() ?>
            <input type="hidden" name="operation_day_id" value="<?= $dayData['id'] ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <label class="form-label editable-label" for="counted_cash" title="Campo editável" style="margin-bottom: 0;">Valor Contado no Caixa (R$)</label>
                        <a href="<?= View::url('utilidades/contador') ?>" class="btn btn-sm btn-secondary" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;" title="Abrir contador de cédulas e moedas">
                            💵 Abrir Contador de Notas
                        </a>
                    </div>
                    <?php
                    $defaultCounted = $cash['counted_cash'] !== null 
                        ? number_format($cash['counted_cash'], 2, ',', '.') 
                        : (isset($_GET['counted']) ? number_format((float)$_GET['counted'], 2, ',', '.') : number_format($cash['expected_cash'], 2, ',', '.'));
                    ?>
                    <input type="text" id="counted_cash" name="counted_cash" class="form-control editable-input" required placeholder="0,00" value="<?= $defaultCounted ?>" style="font-weight: 800; font-size: 1.15rem;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="justification">Justificativa (obrigatória se houver divergência)</label>
                    <input type="text" id="justification" name="justification" class="form-control" placeholder="Motivo da diferença de troco, se houver" value="<?= View::e($cash['justification'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-danger" style="width: 100%; padding: 0.65rem;" onclick="return confirm('Tem certeza que deseja fechar este dia? Não poderá mais lançar vendas sem reabertura.');">
                        🔒 FECHAR DIA
                    </button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <?php if (Auth::isAdmin()): ?>
            <form method="POST" action="<?= View::url('dias/reabrir') ?>" style="border-top: 1px solid var(--border); padding-top: 1rem;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="operation_day_id" value="<?= $dayData['id'] ?>">
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 1;">
                        <input type="text" name="reopen_reason" class="form-control" required placeholder="Motivo para reabertura do dia (obrigatório para auditoria)">
                    </div>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Reabrir o dia registrará uma alteração na auditoria. Prosseguir?');">
                        🔓 Reabrir Dia (Admin)
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Nova Rodada -->
<dialog id="modalNewRound" style="padding: 1.75rem; border: 1px solid var(--border); border-radius: var(--radius); max-width: 600px; width: 95%; margin: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="margin: 0; font-size: 1.4rem;">+ Iniciar Nova Rodada</h3>
        <button type="button" style="background: none; border: none; font-size: 1.4rem; cursor: pointer;" onclick="document.getElementById('modalNewRound').close()">&times;</button>
    </div>

    <form method="POST" action="<?= View::url('rodadas/criar') ?>">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="operation_day_id" value="<?= $dayData['id'] ?>">

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label class="form-label" for="modal_round_number"><strong>Nº Rodada</strong></label>
                <input type="number" id="modal_round_number" name="round_number" class="form-control" required min="1" value="<?= $nextRoundNumber ?? 1 ?>" style="font-weight: 800;" onchange="updateSuggestedRoundName(this.value)">
            </div>
            <div class="form-group">
                <label class="form-label" for="modal_round_name"><strong>Nome da Rodada (Exibido no Telão)</strong></label>
                <input type="text" id="modal_round_name" name="round_name" class="form-control" value="Rodada <?= $nextRoundNumber ?? 1 ?>" placeholder="Ex: 1ª Rodada, Rodada da Pizza...">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" for="modal_prizes_count"><strong>Premiação desta Rodada</strong></label>
            <select id="modal_prizes_count" name="prizes_count" class="form-control" onchange="toggleModalPrizes(this.value)" style="font-weight: 700;">
                <option value="1">🥇 Apenas 1 Prêmio (Prêmio Único / Principal)</option>
                <option value="2" selected>🥇🥈 2 Prêmios (1º e 2º Prêmio)</option>
            </select>
        </div>

        <!-- 1º Prêmio -->
        <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
            <div style="font-weight: 800; color: #b45309; margin-bottom: 0.5rem;">🥇 1º Prêmio</div>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label" style="font-size: 0.85rem;" for="modal_prize_1_title">Nome do Prêmio (Produto / Brinde / Descrição)</label>
                    <input type="text" id="modal_prize_1_title" name="prize_1_title" class="form-control" placeholder="Ex: Lanche no Lanchão da Praia">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.85rem;" for="modal_prize_1">Valor R$ (Se houver)</label>
                    <input type="text" id="modal_prize_1" name="prize_1" class="form-control" placeholder="0,00" value="0,00">
                </div>
            </div>
        </div>

        <!-- 2º Prêmio -->
        <div id="modalPrize2Block" style="background: #f0f9ff; border: 2px solid #0284c7; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
            <div style="font-weight: 800; color: #0369a1; margin-bottom: 0.5rem;">🥈 2º Prêmio</div>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="form-label" style="font-size: 0.85rem;" for="modal_prize_2_title">Nome do Prêmio (Produto / Brinde / Descrição)</label>
                    <input type="text" id="modal_prize_2_title" name="prize_2_title" class="form-control" placeholder="Ex: Pizza na Pizzaria">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.85rem;" for="modal_prize_2">Valor R$ (Se houver)</label>
                    <input type="text" id="modal_prize_2" name="prize_2" class="form-control" placeholder="0,00" value="0,00">
                </div>
            </div>
        </div>

        <!-- Cor da Cartela -->
        <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label" for="modal_card_color"><strong>Cor da Cartela em Jogo</strong></label>
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                <input type="text" id="modal_card_color" name="card_color" class="form-control" value="Amarela" style="font-weight: 800; width: 220px;" placeholder="Digite ou selecione">
                <span style="font-size: 0.85rem; color: var(--text-muted);">(Pode digitar uma nova cor)</span>
            </div>
            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                <?php foreach (($cardColors ?? []) as $cc): ?>
                    <button type="button" class="btn btn-sm" 
                            style="background: <?= $cc['bg_color'] ?>; color: <?= $cc['text_color'] ?>; border: 1px solid <?= $cc['border_color'] ?>; font-weight: 700; padding: 0.25rem 0.6rem;" 
                            onclick="document.getElementById('modal_card_color').value = '<?= View::e($cc['name']) ?>'">
                        <?= View::e($cc['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="modal_notes">Observação Interna (opcional)</label>
            <input type="text" id="modal_notes" name="notes" class="form-control" placeholder="Ex: Rodada doada pelo comércio local">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalNewRound').close()">Cancelar</button>
            <button type="submit" class="btn btn-primary" style="font-weight: 800; padding: 0.6rem 1.5rem;">
                🚀 Iniciar Rodada
            </button>
        </div>
    </form>
</dialog>

<script>
function updateSuggestedRoundName(num) {
    const nameInp = document.getElementById('modal_round_name');
    if (nameInp && (nameInp.value.startsWith('Rodada ') || nameInp.value === '')) {
        nameInp.value = 'Rodada ' + num;
    }
}

function toggleModalPrizes(count) {
    const block2 = document.getElementById('modalPrize2Block');
    if (block2) {
        block2.style.display = parseInt(count, 10) >= 2 ? 'block' : 'none';
    }
}
</script>

<script>
function shareDayWhatsApp() {
    const dayDate = '<?= View::date($dayData["operation_date"]) ?>';
    const status = '<?= $dayData["status"] === "OPEN" ? "EM OPERAÇÃO" : "FECHADO" ?>';
    const initialCash = '<?= View::money($cash["initial_cash"] ?? 0) ?>';
    const totalSales = '<?= View::money($summary["total_sales"] ?? 0) ?>';
    const totalQty = '<?= $summary["total_qty"] ?? 0 ?>';
    const totalPrizes = '<?= View::money($summary["total_prizes"] ?? 0) ?>';
    const profit = '<?= View::money($summary["profit"] ?? 0) ?>';
    const margin = '<?= View::percent($summary["margin"] ?? 0) ?>';
    const expectedCash = '<?= View::money($cash["expected_cash"] ?? 0) ?>';
    const countedCash = '<?= ($cash["counted_cash"] ?? null) !== null ? View::money($cash["counted_cash"]) : "Ainda não conferido" ?>';
    const diff = '<?= ($cash["difference"] ?? null) !== null ? View::money($cash["difference"]) : "R$ 0,00" ?>';

    const text = `*🏆 <?= mb_strtoupper(View::e(View::systemTitle())) ?> — RESUMO DO DIA*\n` +
                 `*Data:* ${dayDate}\n` +
                 `*Status:* ${status}\n` +
                 `------------------------------------\n` +
                 `*Troco Inicial:* ${initialCash}\n` +
                 `*Vendas Totais:* ${totalSales} (${totalQty} cartelas)\n` +
                 `*Prêmios Pagos:* ${totalPrizes}\n` +
                 `*Lucro Líquido:* ${profit} (Margem: ${margin})\n` +
                 `------------------------------------\n` +
                 `*Caixa Esperado:* ${expectedCash}\n` +
                 `*Caixa Contado:* ${countedCash}\n` +
                 `*Diferença:* ${diff}\n` +
                 `------------------------------------\n` +
                 `_Termo contábil completo: ${window.location.origin}<?= View::url('relatorios/termo-caixa') ?>?day_id=<?= $dayData["id"] ?>_`;

    const url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}
</script>
