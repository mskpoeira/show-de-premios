<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$currentStatus = $round['status'] ?? 'OPEN';
$statusStyles = [
    'OPEN' => ['bg' => '#16a34a', 'text' => '🟢 RODADA ABERTA (Vendas)'],
    'IN_PROGRESS' => ['bg' => '#2563eb', 'text' => '🎤 EM ANDAMENTO (Cantoria)'],
    'PAUSED' => ['bg' => '#d97706', 'text' => '⏸️ RODADA PAUSADA'],
    'CHECKING' => ['bg' => '#dc2626', 'text' => '🔔 EM CONFERÊNCIA'],
    'CLOSED' => ['bg' => '#475569', 'text' => '🔒 RODADA FECHADA'],
];
$activeStatusStyle = $statusStyles[$currentStatus] ?? ['bg' => '#64748b', 'text' => $currentStatus];
?>

<div class="page-header" style="flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title">Rodada <?= $round['round_number'] ?> &bull; <?= View::date($round['operation_date']) ?></h1>
            <span class="badge" id="round_status_badge" style="font-size: 0.95rem; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 800; background: <?= $activeStatusStyle['bg'] ?>; color: #fff;">
                <?= $activeStatusStyle['text'] ?>
            </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Regra Vigente: 1 unidade = <?= View::money($pricingRule['single_price']) ?> &bull; Pacote de <?= $pricingRule['bundle_quantity'] ?> = <?= View::money($pricingRule['bundle_price']) ?>
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <!-- Botão Módulo do Locutor -->
        <a href="<?= View::url('locutor?id=' . $round['id']) ?>" class="btn" style="background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; font-weight: 800; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);" title="Abrir painel para cantar pedras e conferir bingo">
            🎤 Módulo do Locutor
        </a>

        <!-- Botão Abrir Telão -->
        <a href="<?= View::url('telao?id=' . $round['id']) ?>" target="_blank" class="btn" style="background: #0f172a; color: #fef08a; border: 1px solid #f59e0b; font-weight: 800;" title="Abrir telão em nova janela ou tela cheia">
            📺 Telão Oficial ⛶
        </a>

        <a href="<?= View::url('dia?id=' . $round['operation_day_id']) ?>" class="btn btn-secondary">
            ← Voltar ao Dia
        </a>
    </div>
</div>

<!-- 5-Status Fast Bar -->
<div class="card" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
            <span>⚡ Alterar Estado da Rodada:</span>
            <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">(Controle imediato transmitido para o Telão e Locutor)</span>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <!-- Aberta -->
            <form method="POST" action="<?= View::url('rodadas/alterar-status') ?>" style="display: inline;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="status" value="OPEN">
                <button type="submit" class="btn btn-sm" style="<?= $currentStatus === 'OPEN' ? 'background: #16a34a; color: #fff; font-weight: 800; border: 2px solid #14532d;' : 'background: #fff; color: #16a34a; border: 1px solid #16a34a;' ?>">
                    🟢 Aberta (Vendas)
                </button>
            </form>

            <!-- Em Andamento -->
            <form method="POST" action="<?= View::url('rodadas/alterar-status') ?>" style="display: inline;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="status" value="IN_PROGRESS">
                <button type="submit" class="btn btn-sm" style="<?= $currentStatus === 'IN_PROGRESS' ? 'background: #2563eb; color: #fff; font-weight: 800; border: 2px solid #1e40af;' : 'background: #fff; color: #2563eb; border: 1px solid #2563eb;' ?>">
                    🎤 Em Andamento
                </button>
            </form>

            <!-- Pausada -->
            <form method="POST" action="<?= View::url('rodadas/alterar-status') ?>" style="display: inline;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="status" value="PAUSED">
                <button type="submit" class="btn btn-sm" style="<?= $currentStatus === 'PAUSED' ? 'background: #d97706; color: #fff; font-weight: 800; border: 2px solid #92400e;' : 'background: #fff; color: #d97706; border: 1px solid #d97706;' ?>">
                    ⏸️ Pausada
                </button>
            </form>

            <!-- Em Conferência -->
            <form method="POST" action="<?= View::url('rodadas/alterar-status') ?>" style="display: inline;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="status" value="CHECKING">
                <button type="submit" class="btn btn-sm" style="<?= $currentStatus === 'CHECKING' ? 'background: #dc2626; color: #fff; font-weight: 800; border: 2px solid #991b1b;' : 'background: #fff; color: #dc2626; border: 1px solid #dc2626;' ?>">
                    🔔 Em Conferência
                </button>
            </form>

            <!-- Fechada -->
            <form method="POST" action="<?= View::url('rodadas/alterar-status') ?>" style="display: inline;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="status" value="CLOSED">
                <button type="submit" class="btn btn-sm" style="<?= $currentStatus === 'CLOSED' ? 'background: #475569; color: #fff; font-weight: 800; border: 2px solid #1e293b;' : 'background: #fff; color: #475569; border: 1px solid #475569;' ?>" onclick="return confirm('Deseja realmente marcar esta rodada como FECHADA?');">
                    🔒 Fechada
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Round Live Counters -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Vendas da Rodada</div>
        <div class="kpi-value" id="total_sales_display"><?= View::money($totalSales) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Quantidade Vendida</div>
        <div class="kpi-value" id="total_qty_display"><?= number_format($totalQty, 0, ',', '.') ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Prêmios Pagos</div>
        <div class="kpi-value" style="color: #64748b;"><?= View::money($totalPrizes) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Lucro da Rodada</div>
        <div class="kpi-value <?= $profit >= 0 ? 'positive' : 'negative' ?>" id="total_profit_display">
            <?= View::money($profit) ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Margem</div>
        <div class="kpi-value <?= $margin >= 0 ? 'positive' : 'negative' ?>" id="total_margin_display">
            <?= View::percent($margin) ?>
        </div>
    </div>
</div>

<!-- Sales Entry Form -->
<form method="POST" action="<?= View::url('rodadas/salvar-vendas') ?>" id="salesForm">
    <?= Csrf::inputField() ?>
    <input type="hidden" name="round_id" value="<?= $round['id'] ?>">

    <!-- Round Header Configuration -->
    <div class="card" style="border-top: 4px solid #f59e0b;">
        <div class="card-title">⚙️ Configurações da Rodada & Cor da Cartela</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label editable-label" for="round_number">Número da Rodada</label>
                <input type="number" id="round_number" name="round_number" class="form-control editable-input" required min="1" value="<?= (int)$round['round_number'] ?>" style="font-weight: 800;">
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label class="form-label editable-label" for="round_name">Nome / Título da Rodada (Exibido no Telão)</label>
                <input type="text" id="round_name" name="round_name" class="form-control editable-input" value="<?= View::e($round['round_name'] ?? ('Rodada ' . $round['round_number'])) ?>" placeholder="Ex: 1ª Rodada — Abertura, Rodada do Peru, Rodada Especial...">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="prizes_count">Qtd de Prêmios nesta Rodada</label>
                <select id="prizes_count" name="prizes_count" class="form-control editable-input" onchange="togglePrizesVisibility()" style="font-weight: 700;">
                    <option value="1" <?= ((int)($round['prizes_count'] ?? 2) === 1) ? 'selected' : '' ?>>🥇 1 Prêmio (Principal)</option>
                    <option value="2" <?= ((int)($round['prizes_count'] ?? 2) === 2) ? 'selected' : '' ?>>🥇🥈 2 Prêmios (Principal + Secundário)</option>
                    <option value="3" <?= ((int)($round['prizes_count'] ?? 2) === 3) ? 'selected' : '' ?>>🥇🥈🥉 3 Prêmios</option>
                </select>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label editable-label" for="card_color">Cor da Cartela em Jogo</label>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.5rem;">
                    <input type="text" id="card_color" name="card_color" class="form-control editable-input" value="<?= View::e($round['card_color'] ?? 'Amarela') ?>" style="width: 220px; font-weight: 800;" placeholder="Ex: Amarela, Papel Jornal...">
                    <span style="font-size: 0.85rem; color: var(--text-muted); margin-right: 0.5rem;">(Pode digitar qualquer cor ou clicar abaixo):</span>
                </div>
                <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                    <?php foreach (($cardColors ?? []) as $cc): ?>
                        <button type="button" class="btn btn-sm" 
                                style="background: <?= $cc['bg_color'] ?>; color: <?= $cc['text_color'] ?>; border: 1px solid <?= $cc['border_color'] ?>; font-weight: 700;" 
                                onclick="selectColor('<?= View::e($cc['name']) ?>')">
                            <?= View::e($cc['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Configuração de Valores da Rodada -->
            <div style="grid-column: 1 / -1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.85rem; margin-top: 0.5rem;">
                <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.4rem;">
                    <span>💵 Valores e Preço da Cartela desta Rodada</span>
                    <span style="font-weight: normal; font-size: 0.8rem; color: var(--text-muted);">(Altere aqui o valor da rodada para aplicar preços diferenciados)</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label editable-label" for="single_price">Preço da Cartela Avulsa (R$)</label>
                        <input type="text" id="single_price" name="single_price" class="form-control editable-input" 
                               value="<?= number_format((float)($round['single_price'] ?? $pricingRule['single_price']), 2, ',', '.') ?>" 
                               style="font-weight: 800; color: #0f172a;" placeholder="2,00">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label editable-label" for="bundle_quantity">Qtd Cartelas no Pacote/Combo</label>
                        <input type="number" id="bundle_quantity" name="bundle_quantity" class="form-control editable-input" 
                               value="<?= (int)($round['bundle_quantity'] ?? $pricingRule['bundle_quantity']) ?>" 
                               style="font-weight: 800; text-align: center;" min="1" step="1">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label editable-label" for="bundle_price">Preço do Pacote/Combo (R$)</label>
                        <input type="text" id="bundle_price" name="bundle_price" class="form-control editable-input" 
                               value="<?= number_format((float)($round['bundle_price'] ?? $pricingRule['bundle_price']), 2, ',', '.') ?>" 
                               style="font-weight: 800; color: #0f172a;" placeholder="5,00">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Entry Grid (Supports Direct Amount & Shortcuts) -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <span>Grade de Lançamento por Vendedor(a)</span>
                <p style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                    💡 <strong>Lançamento Direto:</strong> Você pode digitar diretamente o valor em R$ e usar os atalhos. A quantidade de cartelas pode ser ignorada.
                </p>
            </div>
            <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">
                Pressione <kbd>Enter</kbd> para avançar
            </span>
        </div>

        <?php if (empty($sellers)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                Nenhum vendedor cadastrado ainda. <a href="<?= View::url('vendedores') ?>">Clique aqui para cadastrar vendedores</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Vendedor(a)</th>
                            <th style="width: 45%;">Valor em R$ (Lançamento Direto) ✎</th>
                            <th style="width: 30%;">Qtd Cartelas (Opcional)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $s): ?>
                        <tr class="sales-row">
                            <td>
                                <strong><?= View::e($s['name']) ?></strong>
                                <?php if (!empty($s['nickname'])): ?>
                                    <small style="color: var(--text-muted); display: block;">(<?= View::e($s['nickname']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isOpen): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="font-weight: 800; color: #0f172a; font-size: 1.1rem;">R$</span>
                                            <input type="text" 
                                                   name="amounts[<?= $s['id'] ?>]" 
                                                   id="amt_input_<?= $s['id'] ?>"
                                                   class="form-control editable-input sale-amt-input" 
                                                   value="<?= number_format((float)$s['amount'], 2, ',', '.') ?>" 
                                                   style="width: 140px; font-weight: 800; font-size: 1.15rem; color: #0f172a;"
                                                   placeholder="0,00"
                                                   oninput="handleAmountInput(<?= $s['id'] ?>)"
                                                   title="Campo editável: digite o valor diretamente em reais">
                                        </div>
                                        <div class="quick-add-group" style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="setAmt('amt_input_<?= $s['id'] ?>', 0)" title="Zerar valor">R$ 0</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="addAmt('amt_input_<?= $s['id'] ?>', 5)" title="Adicionar R$ 5">+5</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="addAmt('amt_input_<?= $s['id'] ?>', 10)" title="Adicionar R$ 10">+10</button>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="addAmt('amt_input_<?= $s['id'] ?>', 20)" title="Adicionar R$ 20">+20</button>
                                            <button type="button" class="btn btn-sm btn-success" onclick="addAmt('amt_input_<?= $s['id'] ?>', 50)" title="Adicionar R$ 50">+50</button>
                                            <button type="button" class="btn btn-sm btn-warning" onclick="addAmt('amt_input_<?= $s['id'] ?>', 100)" title="Adicionar R$ 100">+100</button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="font-weight: 800; font-size: 1.15rem; color: #0f172a;"><?= View::money($s['amount']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isOpen): ?>
                                    <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                        <input type="number" 
                                               name="quantities[<?= $s['id'] ?>]" 
                                               id="qty_input_<?= $s['id'] ?>"
                                               class="form-control sale-qty-input" 
                                               min="0" 
                                               step="1" 
                                               value="<?= (int)$s['quantity'] ?>" 
                                               style="width: 75px; font-weight: 700; text-align: center;"
                                               oninput="handleQtyInput(<?= $s['id'] ?>)"
                                               title="Quantidade de cartelas">
                                        <?php $bQty = (int)($pricingRule['bundle_quantity'] ?? 2); ?>
                                        <div class="quick-add-group" style="display: flex; gap: 0.2rem;">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="quickAdd('qty_input_<?= $s['id'] ?>', 1, <?= $s['id'] ?>)" title="Adicionar 1 cartela">+1</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="quickAdd('qty_input_<?= $s['id'] ?>', <?= $bQty ?>, <?= $s['id'] ?>)" title="Adicionar 1 pacote (+<?= $bQty ?>)">+<?= $bQty ?></button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="quickAdd('qty_input_<?= $s['id'] ?>', <?= $bQty * 2 ?>, <?= $s['id'] ?>)" title="Adicionar 2 pacotes (+<?= $bQty * 2 ?>)">+<?= $bQty * 2 ?></button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="font-weight: 700;"><?= number_format($s['quantity'], 0, ',', '.') ?> cartelas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($isOpen): ?>
                <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="font-size: 1.05rem; padding: 0.75rem 1.5rem;">
                        💾 Salvar Vendas e Premiações da Rodada
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Datalist para autocomplete de vendedores dos prêmios -->
    <datalist id="sellersDatalist">
        <?php foreach ($sellers as $sl): ?>
            <option value="<?= View::e($sl['name']) ?>"><?= !empty($sl['nickname']) ? ' (' . View::e($sl['nickname']) . ')' : '' ?></option>
        <?php endforeach; ?>
    </datalist>

    <!-- Prizes & Winners Card -->
    <div class="card">
        <div class="card-title">Premiação Efetiva, Ganhadores e Vendedores da Rodada</div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <!-- 1º Prêmio -->
            <div class="card" style="border: 2px solid #f59e0b; background: #fffbeb; margin-bottom: 0;">
                <div style="font-weight: 800; font-size: 1.1rem; color: #b45309; margin-bottom: 1rem;">🥇 1º Prêmio (Principal)</div>
                
                <div class="form-group">
                    <label class="form-label editable-label" for="prize_1_title">Nome do Prêmio (Produto / Brinde / Título)</label>
                    <input type="text" id="prize_1_title" name="prize_1_title" class="form-control editable-input" value="<?= View::e($round['prize_1_title'] ?? '') ?>" placeholder="Ex: Lanche no Lanchão da Praia, TV, Bicicleta...">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_1">Valor Estimado / Pago (R$)</label>
                    <?php if ($isOpen): ?>
                        <input type="text" id="prize_1" name="prize_1" class="form-control editable-input" value="<?= number_format($round['prize_1'], 2, ',', '.') ?>" placeholder="0,00" style="font-weight: 800; font-size: 1.2rem;">
                    <?php else: ?>
                        <input type="text" class="form-control auto-input" readonly value="<?= View::money($round['prize_1']) ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="winner_1_name">Nome do(a) Ganhador(a)</label>
                    <input type="text" id="winner_1_name" name="winner_1_name" class="form-control editable-input" value="<?= View::e($round['winner_1_name'] ?? $round['winner_name'] ?? '') ?>" placeholder="Ex: Maria José (Cartela nº 452)">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="seller_1_name">Vendedor(a) da Cartela Premiada</label>
                    <input type="text" id="seller_1_name" name="seller_1_name" list="sellersDatalist" class="form-control editable-input" value="<?= View::e($round['seller_1_name'] ?? '') ?>" placeholder="Selecione ou digite o vendedor">
                </div>
            </div>

            <!-- 2º Prêmio -->
            <div class="card" id="cardPrize2" style="border: 2px solid #0284c7; background: #f0f9ff; margin-bottom: 0;">
                <div style="font-weight: 800; font-size: 1.1rem; color: #0369a1; margin-bottom: 1rem;">🥈 2º Prêmio (Secundário)</div>
                
                <div class="form-group">
                    <label class="form-label editable-label" for="prize_2_title">Nome do Prêmio (Produto / Brinde / Título)</label>
                    <input type="text" id="prize_2_title" name="prize_2_title" class="form-control editable-input" value="<?= View::e($round['prize_2_title'] ?? '') ?>" placeholder="Ex: Pizza na Pizzaria, Batedeira...">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_2">Valor Estimado / Pago (R$)</label>
                    <?php if ($isOpen): ?>
                        <input type="text" id="prize_2" name="prize_2" class="form-control editable-input" value="<?= number_format($round['prize_2'], 2, ',', '.') ?>" placeholder="0,00" style="font-weight: 800; font-size: 1.2rem;">
                    <?php else: ?>
                        <input type="text" class="form-control auto-input" readonly value="<?= View::money($round['prize_2']) ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="winner_2_name">Nome do(a) Ganhador(a)</label>
                    <input type="text" id="winner_2_name" name="winner_2_name" class="form-control editable-input" value="<?= View::e($round['winner_2_name'] ?? '') ?>" placeholder="Ex: José Carlos (Cartela nº 120)">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="seller_2_name">Vendedor(a) da Cartela Premiada</label>
                    <input type="text" id="seller_2_name" name="seller_2_name" list="sellersDatalist" class="form-control editable-input" value="<?= View::e($round['seller_2_name'] ?? '') ?>" placeholder="Selecione ou digite o vendedor">
                </div>
            </div>

            <!-- 3º Prêmio -->
            <div class="card" id="cardPrize3" style="border: 2px solid #059669; background: #ecfdf5; margin-bottom: 0; display: none;">
                <div style="font-weight: 800; font-size: 1.1rem; color: #047857; margin-bottom: 1rem;">🥉 3º Prêmio (Extra)</div>
                
                <div class="form-group">
                    <label class="form-label editable-label" for="prize_3_title">Nome do Prêmio (Produto / Brinde / Título)</label>
                    <input type="text" id="prize_3_title" name="prize_3_title" class="form-control editable-input" value="<?= View::e($round['prize_3_title'] ?? '') ?>" placeholder="Ex: Liquidificador, Ferro elétrico...">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_3">Valor Estimado / Pago (R$)</label>
                    <?php if ($isOpen): ?>
                        <input type="text" id="prize_3" name="prize_3" class="form-control editable-input" value="<?= number_format((float)($round['prize_3'] ?? 0), 2, ',', '.') ?>" placeholder="0,00" style="font-weight: 800; font-size: 1.2rem;">
                    <?php else: ?>
                        <input type="text" class="form-control auto-input" readonly value="<?= View::money($round['prize_3'] ?? 0) ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="winner_3_name">Nome do(a) Ganhador(a)</label>
                    <input type="text" id="winner_3_name" name="winner_3_name" class="form-control editable-input" value="<?= View::e($round['winner_3_name'] ?? '') ?>" placeholder="Ex: Ana Paula (Cartela nº 380)">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="seller_3_name">Vendedor(a) da Cartela Premiada</label>
                    <input type="text" id="seller_3_name" name="seller_3_name" list="sellersDatalist" class="form-control editable-input" value="<?= View::e($round['seller_3_name'] ?? '') ?>" placeholder="Selecione ou digite o vendedor">
                </div>
            </div>
        </div>

        <?php if ($isOpen): ?>
            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="font-size: 1.05rem; padding: 0.75rem 1.5rem;">
                    💾 Salvar Vendas e Premiações da Rodada
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<!-- Suggestion for Next Round Highlight -->
<div class="card" style="border-left: 4px solid var(--primary); background: #f8fafc;">
    <div class="card-title">💡 Sugestão para a Próxima Rodada</div>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
        Calculado com base em 50% do valor de vendas da rodada atual, arredondado para múltiplos de R$ 10,00:
    </p>

    <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem;">
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">1º Prêmio Sugerido (65%):</span>
            <div style="font-size: 1.3rem; font-weight: 700; color: #1e40af;" id="sug_p1_display"><?= View::money($suggestion['prize_1']) ?></div>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">2º Prêmio Sugerido (35%):</span>
            <div style="font-size: 1.3rem; font-weight: 700; color: #1e40af;" id="sug_p2_display"><?= View::money($suggestion['prize_2']) ?></div>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">Premiação Total Prevista:</span>
            <div style="font-size: 1.3rem; font-weight: 700; color: #0f172a;" id="sug_total_display"><?= View::money($suggestion['total']) ?></div>
        </div>
    </div>
</div>

<!-- Round Actions (Close / Reopen / WhatsApp) -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= View::url('dia?id=' . $round['operation_day_id']) ?>" class="btn btn-secondary">
            ← Voltar ao Dia
        </a>
        <button type="button" class="btn btn-success" onclick="shareRoundWhatsApp()">
            📱 Compartilhar no WhatsApp
        </button>
        <a href="<?= View::url('telao') ?>" target="_blank" class="btn btn-primary" title="Abrir telão público em nova aba">
            📺 Abrir no Telão
        </a>
    </div>

    <div>
        <?php if ($isOpen): ?>
            <form method="POST" action="<?= View::url('rodadas/fechar') ?>" style="display: inline-block;">
                <?= Csrf::inputField() ?>
                <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                <input type="hidden" name="prize_1" id="close_prize_1" value="<?= $round['prize_1'] ?>">
                <input type="hidden" name="prize_2" id="close_prize_2" value="<?= $round['prize_2'] ?>">
                <input type="hidden" name="winner_name" id="close_winner_name" value="<?= View::e($round['winner_1_name'] ?? $round['winner_name'] ?? '') ?>">
                <button type="submit" class="btn btn-danger" onclick="
                    if (document.getElementById('prize_1')) document.getElementById('close_prize_1').value = document.getElementById('prize_1').value;
                    if (document.getElementById('prize_2')) document.getElementById('close_prize_2').value = document.getElementById('prize_2').value;
                    if (document.getElementById('winner_1_name')) document.getElementById('close_winner_name').value = document.getElementById('winner_1_name').value;
                    return confirm('Tem certeza que deseja fechar esta rodada? Ela será consolidada.');
                ">
                    🔒 FECHAR RODADA <?= $round['round_number'] ?>
                </button>
            </form>
        <?php else: ?>
            <?php if (Auth::isAdmin()): ?>
                <form method="POST" action="<?= View::url('rodadas/reabrir') ?>" style="display: flex; gap: 0.5rem;">
                    <?= Csrf::inputField() ?>
                    <input type="hidden" name="round_id" value="<?= $round['id'] ?>">
                    <input type="text" name="reopen_reason" class="form-control" required placeholder="Motivo da reabertura" style="width: 250px;">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Reabrir rodada registrará auditoria. Prosseguir?');">
                        🔓 Reabrir Rodada (Admin)
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const pricingConfig = {
    bundle_quantity: <?= (int)($pricingRule['bundle_quantity'] ?? 3) ?>,
    bundle_price: <?= (float)($pricingRule['bundle_price'] ?? 5.0) ?>,
    single_price: <?= (float)($pricingRule['single_price'] ?? 2.0) ?>
};

function parseBrl(str) {
    if (!str) return 0;
    if (typeof str === 'number') return str;
    const cleaned = String(str).replace(/[^\d,\.-]/g, '').replace(/\./g, '').replace(',', '.');
    const val = parseFloat(cleaned);
    return isNaN(val) ? 0 : val;
}

function formatBrl(num) {
    return (num || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function selectColor(colorName) {
    const input = document.getElementById('card_color');
    if (input) {
        input.value = colorName;
        input.focus();
    }
}

function togglePrizesVisibility() {
    const select = document.getElementById('prizes_count');
    if (!select) return;
    const count = parseInt(select.value, 10) || 2;
    const card2 = document.getElementById('cardPrize2');
    const card3 = document.getElementById('cardPrize3');
    
    if (card2) {
        card2.style.display = count >= 2 ? 'block' : 'none';
    }
    if (card3) {
        card3.style.display = count >= 3 ? 'block' : 'none';
    }
    updateLiveKpis();
}

function setAmt(inputId, value) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.value = formatBrl(value);
    const sellerId = inputId.replace('amt_input_', '');
    handleAmountInput(sellerId, false);
}

function addAmt(inputId, step) {
    const el = document.getElementById(inputId);
    if (!el) return;
    const current = parseBrl(el.value);
    const updated = current + step;
    el.value = formatBrl(updated);
    const sellerId = inputId.replace('amt_input_', '');
    handleAmountInput(sellerId, false);
}

function handleAmountInput(sellerId, userEditedQty = false) {
    const amtInput = document.getElementById('amt_input_' + sellerId);
    const qtyInput = document.getElementById('qty_input_' + sellerId);
    
    if (amtInput && qtyInput && !userEditedQty) {
        const val = parseBrl(amtInput.value);
        if (val <= 0) {
            qtyInput.value = 0;
        } else if (pricingConfig.single_price > 0 && (qtyInput.value === '' || qtyInput.value === '0')) {
            // Sugere quantidade aproximada se estiver zerada
            const approx = Math.round(val / pricingConfig.single_price);
            qtyInput.value = approx;
        }
    }
    updateLiveKpis();
}

function handleQtyInput(sellerId) {
    const amtInput = document.getElementById('amt_input_' + sellerId);
    const qtyInput = document.getElementById('qty_input_' + sellerId);
    
    if (amtInput && qtyInput) {
        const qty = parseInt(qtyInput.value, 10) || 0;
        if (qty <= 0) {
            amtInput.value = '0,00';
        } else {
            // Calcula pelo melhor preco de pacotes/avulsas
            const bQty = pricingConfig.bundle_quantity;
            const bPrice = pricingConfig.bundle_price;
            const sPrice = pricingConfig.single_price;
            
            let total = 0;
            if (bQty > 1 && qty >= bQty) {
                const packages = Math.floor(qty / bQty);
                const singles = qty % bQty;
                total = (packages * bPrice) + (singles * sPrice);
            } else {
                total = qty * sPrice;
            }
            amtInput.value = formatBrl(total);
        }
    }
    updateLiveKpis();
}

function quickAdd(inputId, step, sellerId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    const current = parseInt(el.value, 10) || 0;
    el.value = current + step;
    handleQtyInput(sellerId);
}

function updateLiveKpis() {
    let totalSales = 0;
    let totalQty = 0;

    document.querySelectorAll('.sale-amt-input').forEach(inp => {
        totalSales += parseBrl(inp.value);
    });

    document.querySelectorAll('.sale-qty-input').forEach(inp => {
        totalQty += parseInt(inp.value, 10) || 0;
    });

    const p1Inp = document.getElementById('prize_1');
    const p2Inp = document.getElementById('prize_2');
    const p3Inp = document.getElementById('prize_3');
    const prizesCount = parseInt(document.getElementById('prizes_count')?.value || 2, 10);

    const p1 = p1Inp ? parseBrl(p1Inp.value) : 0;
    const p2 = (p2Inp && prizesCount >= 2) ? parseBrl(p2Inp.value) : 0;
    const p3 = (p3Inp && prizesCount >= 3) ? parseBrl(p3Inp.value) : 0;
    const totalPrizes = p1 + p2 + p3;

    const profit = totalSales - totalPrizes;
    const margin = totalSales > 0 ? (profit / totalSales) * 100 : 0;

    // Atualiza cards superiores
    const salesEl = document.getElementById('total_sales_display');
    if (salesEl) salesEl.textContent = 'R$ ' + formatBrl(totalSales);

    const qtyEl = document.getElementById('total_qty_display');
    if (qtyEl) qtyEl.textContent = totalQty.toLocaleString('pt-BR');

    const profitEl = document.getElementById('total_profit_display');
    if (profitEl) {
        profitEl.textContent = 'R$ ' + formatBrl(profit);
        profitEl.className = 'kpi-value ' + (profit >= 0 ? 'positive' : 'negative');
    }

    const marginEl = document.getElementById('total_margin_display');
    if (marginEl) {
        marginEl.textContent = formatBrl(margin) + '%';
        marginEl.className = 'kpi-value ' + (margin >= 0 ? 'positive' : 'negative');
    }

    // Atualiza sugestao dinamica de premios da proxima rodada
    const sugTotal = Math.round((totalSales * 0.5) / 10) * 10;
    const sugP1 = Math.round((sugTotal * 0.65) / 10) * 10;
    const sugP2 = sugTotal - sugP1;

    const sTotEl = document.getElementById('sug_total_display');
    if (sTotEl) sTotEl.textContent = 'R$ ' + formatBrl(sugTotal);
    const sP1El = document.getElementById('sug_p1_display');
    if (sP1El) sP1El.textContent = 'R$ ' + formatBrl(sugP1);
    const sP2El = document.getElementById('sug_p2_display');
    if (sP2El) sP2El.textContent = 'R$ ' + formatBrl(sugP2);
}

document.addEventListener('DOMContentLoaded', () => {
    togglePrizesVisibility();
    
    // Escuta mudancas nos inputs de premios para recalcular lucro ao vivo
    ['prize_1', 'prize_2', 'prize_3'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateLiveKpis);
        }
    });

    // Enter para avancar de campo em campo
    const inputs = Array.from(document.querySelectorAll('.sale-amt-input'));
    inputs.forEach((inp, idx) => {
        inp.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const next = inputs[idx + 1];
                if (next) {
                    next.focus();
                    next.select();
                } else {
                    document.getElementById('prize_1')?.focus();
                }
            }
        });
    });
});

function shareRoundWhatsApp() {
    const roundNum = '<?= $round["round_number"] ?>';
    const roundName = document.getElementById('round_name') ? document.getElementById('round_name').value.trim() : '<?= View::e($round["round_name"] ?? "") ?>';
    const currentStatus = '<?= $round["status"] ?>';
    const statusText = (currentStatus === 'IN_PROGRESS' || <?= $totalSales > 0 ? 'true' : 'false' ?>) ? 'EM ANDAMENTO' : (currentStatus === 'OPEN' ? 'EM ABERTO' : 'FECHADA');
    
    // Títulos e valores dos prêmios
    const p1Title = document.getElementById('prize_1_title') ? document.getElementById('prize_1_title').value.trim() : '<?= View::e($round["prize_1_title"] ?? "") ?>';
    const p1Val = document.getElementById('prize_1') ? document.getElementById('prize_1').value.trim() : '<?= number_format((float)$round["prize_1"], 2, ",", ".") ?>';
    const p1Desc = p1Title ? (p1Title + (parseBrl(p1Val) > 0 ? ` (R$ ${p1Val})` : '')) : `R$ ${p1Val}`;

    const prizesCount = parseInt(document.getElementById('prizes_count')?.value || '<?= (int)($round["prizes_count"] ?? 2) ?>', 10);

    const p2Title = document.getElementById('prize_2_title') ? document.getElementById('prize_2_title').value.trim() : '<?= View::e($round["prize_2_title"] ?? "") ?>';
    const p2Val = document.getElementById('prize_2') ? document.getElementById('prize_2').value.trim() : '<?= number_format((float)$round["prize_2"], 2, ",", ".") ?>';
    const p2Desc = p2Title ? (p2Title + (parseBrl(p2Val) > 0 ? ` (R$ ${p2Val})` : '')) : `R$ ${p2Val}`;

    const p3Title = document.getElementById('prize_3_title') ? document.getElementById('prize_3_title').value.trim() : '<?= View::e($round["prize_3_title"] ?? "") ?>';
    const p3Val = document.getElementById('prize_3') ? document.getElementById('prize_3').value.trim() : '<?= number_format((float)$round["prize_3"], 2, ",", ".") ?>';
    const p3Desc = p3Title ? (p3Title + (parseBrl(p3Val) > 0 ? ` (R$ ${p3Val})` : '')) : `R$ ${p3Val}`;

    const winner1 = document.getElementById('winner_1_name') ? document.getElementById('winner_1_name').value.trim() : '';
    const seller1 = document.getElementById('seller_1_name') ? document.getElementById('seller_1_name').value.trim() : '';
    const winner2 = document.getElementById('winner_2_name') ? document.getElementById('winner_2_name').value.trim() : '';
    const seller2 = document.getElementById('seller_2_name') ? document.getElementById('seller_2_name').value.trim() : '';
    const winner3 = document.getElementById('winner_3_name') ? document.getElementById('winner_3_name').value.trim() : '';
    const seller3 = document.getElementById('seller_3_name') ? document.getElementById('seller_3_name').value.trim() : '';

    const totalSales = document.getElementById('total_sales_display') ? document.getElementById('total_sales_display').textContent.trim() : '<?= View::money($totalSales) ?>';
    const totalQty = document.getElementById('total_qty_display') ? document.getElementById('total_qty_display').textContent.trim() : '<?= $totalQty ?>';
    const color = document.getElementById('card_color') ? document.getElementById('card_color').value.trim() : '<?= View::e($round["card_color"] ?? "") ?>';

    let text = `*🏆 <?= mb_strtoupper(View::e(View::systemTitle())) ?>*\n` +
               `*Rodada ${roundNum}${roundName ? ' — ' + roundName : ''}*\n` +
               (color ? `*Cartela:* ${color.toUpperCase()}\n` : '') +
               `*Status:* ${statusText}\n` +
               `------------------------------------\n` +
               `*🥇 1º Prêmio:* ${p1Desc}\n` +
               (winner1 ? `  _Ganhador(a):_ ${winner1}\n` : '') +
               (seller1 ? `  _Vendedor(a):_ ${seller1}\n` : '');

    if (prizesCount >= 2) {
        text += `*🥈 2º Prêmio:* ${p2Desc}\n` +
                (winner2 ? `  _Ganhador(a):_ ${winner2}\n` : '') +
                (seller2 ? `  _Vendedor(a):_ ${seller2}\n` : '');
    }

    if (prizesCount >= 3) {
        text += `*🥉 3º Prêmio:* ${p3Desc}\n` +
                (winner3 ? `  _Ganhador(a):_ ${winner3}\n` : '') +
                (seller3 ? `  _Vendedor(a):_ ${seller3}\n` : '');
    }

    text += `------------------------------------\n` +
            `*Total Vendido:* ${totalSales} (${totalQty} cartelas)\n` +
            `------------------------------------\n` +
            `Painel ao vivo: ${window.location.origin}<?= View::url('telao') ?>`;

    const url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}
</script>

