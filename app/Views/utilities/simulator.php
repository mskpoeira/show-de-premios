<?php
use App\Core\View;

$bundleQty = (int)($pricingRule['bundle_quantity'] ?? 3);
$bundlePrice = (float)($pricingRule['bundle_price'] ?? 5.00);
$singlePrice = (float)($pricingRule['single_price'] ?? 2.00);

$defaultPoolPct = (int)($settings['prize_pool_percent'] ?? 50);
$defaultP1Pct = (int)($settings['prize_1_percent'] ?? 65);
$defaultP2Pct = (int)($settings['prize_2_percent'] ?? 35);
$defaultRounding = (float)($settings['prize_rounding'] ?? 10.00);
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">🔮 Simulador de Premiações & Vendas</h1>
        <p class="page-subtitle">Projete na hora as premiações sugeridas, lucro estimado e margens antes de abrir ou fechar as rodadas.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir Projeção
        </button>
    </div>
</div>

<!-- Active Rule Banner -->
<div class="card" style="border-left: 4px solid var(--primary); background: #f0f9ff; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <strong>🎟️ Tabela de Preços Vigente:</strong> 
        1 Cartela = <span class="badge badge-ok"><?= View::money($singlePrice) ?></span> &bull; 
        Pacote c/ <?= $bundleQty ?> Cartelas = <span class="badge badge-ok"><?= View::money($bundlePrice) ?></span>
    </div>
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        (Sincronizado automaticamente com as Regras de Preços)
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">

    <!-- Controls Card -->
    <div class="card" style="padding: 2rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            ⚙️ Parâmetros da Simulação
        </h2>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="form-label" style="font-weight: 700; font-size: 1.05rem;">
                Expectativa de Vendas da Rodada (R$):
            </label>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <input type="range" min="100" max="10000" step="50" value="1000" id="salesRange" style="flex: 1; height: 10px; accent-color: var(--primary);" oninput="syncFromRange()">
                <input type="number" min="0" step="10" value="1000" id="salesInput" class="form-control form-control-editable" style="width: 140px; font-weight: 800; font-size: 1.2rem; text-align: right;" oninput="syncFromInput()">
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
                    <input type="number" min="10" max="90" step="1" value="<?= $defaultPoolPct ?>" id="paramPrizePool" class="form-control form-control-editable" style="font-weight: 700;" oninput="recalculateSimulation()">
                    <span style="font-weight: 700;">%</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Arredondar Múltiplo:</label>
                <select id="paramRounding" class="form-control form-control-editable" style="font-weight: 700;" onchange="recalculateSimulation()">
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
                    <input type="number" min="50" max="95" step="1" value="<?= $defaultP1Pct ?>" id="paramP1" class="form-control form-control-editable" style="font-weight: 700;" oninput="syncP1()">
                    <span style="font-weight: 700;">%</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Divisão 2º Prêmio:</label>
                <div style="display: flex; align-items: center; gap: 0.25rem;">
                    <input type="number" min="5" max="50" step="1" value="<?= $defaultP2Pct ?>" id="paramP2" class="form-control" style="font-weight: 700; background: var(--bg-alt);" readonly>
                    <span style="font-weight: 700;">%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Card -->
    <div class="card" style="padding: 2rem; background: #ffffff;">
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            📊 Projeção em Tempo Real
        </h2>

        <!-- KPI Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            
            <div style="background: var(--bg-alt); border-radius: 12px; padding: 1.25rem; border-left: 4px solid var(--warning);">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">🥇 1º Prêmio Projetado</div>
                <div id="resP1" style="font-size: 1.8rem; font-weight: 900; color: #b45309; margin-top: 0.25rem; font-family: monospace;">R$ 0,00</div>
            </div>

            <div style="background: var(--bg-alt); border-radius: 12px; padding: 1.25rem; border-left: 4px solid #06b6d4;">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">🥈 2º Prêmio Projetado</div>
                <div id="resP2" style="font-size: 1.8rem; font-weight: 900; color: #0e7490; margin-top: 0.25rem; font-family: monospace;">R$ 0,00</div>
            </div>

            <div style="background: var(--bg-alt); border-radius: 12px; padding: 1.25rem; border-left: 4px solid var(--primary);">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">🎁 Premiação Total Sugerida</div>
                <div id="resTotalPrize" style="font-size: 1.8rem; font-weight: 900; color: var(--primary); margin-top: 0.25rem; font-family: monospace;">R$ 0,00</div>
            </div>

            <div style="background: var(--bg-alt); border-radius: 12px; padding: 1.25rem; border-left: 4px solid var(--success);">
                <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">💰 Lucro Líquido Projetado</div>
                <div id="resProfit" style="font-size: 1.8rem; font-weight: 900; color: var(--success); margin-top: 0.25rem; font-family: monospace;">R$ 0,00</div>
            </div>

        </div>

        <!-- Profit Margin Progress -->
        <div style="background: var(--bg-alt); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-weight: 700; color: var(--text-main);">Margem Operacional da Rodada:</span>
                <span id="resMargin" style="font-weight: 900; font-size: 1.3rem; color: var(--success);">50,00%</span>
            </div>
            <div style="width: 100%; height: 10px; background: #cbd5e1; border-radius: 5px; overflow: hidden;">
                <div id="resMarginBar" style="width: 50%; height: 100%; background: var(--success); transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- Cartelas breakdown estimation -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; font-size: 0.9rem; color: var(--text-muted);">
            <div>🎟️ Estimativa se 100% for vendida em pacotes de 3 (R$ 5,00): <strong id="estPackOnly" style="color: var(--text-main);">0 cartelas</strong></div>
            <div style="margin-top: 0.25rem;">🎟️ Estimativa se 100% for vendida avulsa (R$ 2,00): <strong id="estSingleOnly" style="color: var(--text-main);">0 cartelas</strong></div>
        </div>

    </div>

</div>

<script>
function formatBRL(val) {
    return 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function syncFromRange() {
    document.getElementById('salesInput').value = document.getElementById('salesRange').value;
    recalculateSimulation();
}

function syncFromInput() {
    document.getElementById('salesRange').value = document.getElementById('salesInput').value;
    recalculateSimulation();
}

function setPreset(val) {
    document.getElementById('salesRange').value = val;
    document.getElementById('salesInput').value = val;
    recalculateSimulation();
}

function syncP1() {
    let p1 = parseInt(document.getElementById('paramP1').value, 10) || 65;
    if (p1 > 95) p1 = 95;
    if (p1 < 50) p1 = 50;
    document.getElementById('paramP1').value = p1;
    document.getElementById('paramP2').value = 100 - p1;
    recalculateSimulation();
}

function recalculateSimulation() {
    const sales = parseFloat(document.getElementById('salesInput').value) || 0;
    const poolPct = (parseFloat(document.getElementById('paramPrizePool').value) || 50) / 100;
    const roundStep = parseFloat(document.getElementById('paramRounding').value);
    const p1Pct = (parseFloat(document.getElementById('paramP1').value) || 65) / 100;

    let totalPrize = 0;
    let p1 = 0;
    let p2 = 0;

    if (isNaN(roundStep) || roundStep <= 0) {
        // Sem arredondamento (exato em centavos)
        totalPrize = Math.round((sales * poolPct) * 100) / 100;
        if (totalPrize < 0) totalPrize = 0;
        p1 = Math.round((totalPrize * p1Pct) * 100) / 100;
        p2 = Math.round((totalPrize - p1) * 100) / 100;
        if (p2 > p1) {
            p1 = Math.round((totalPrize / 2) * 100) / 100;
            p2 = Math.round((totalPrize - p1) * 100) / 100;
        }
    } else {
        // Arredondamento ao múltiplo selecionado
        totalPrize = Math.round((sales * poolPct) / roundStep) * roundStep;
        if (totalPrize < 0) totalPrize = 0;

        p1 = Math.round((totalPrize * p1Pct) / roundStep) * roundStep;
        p2 = totalPrize - p1;

        // Safety: p1 >= p2
        if (p2 > p1) {
            p1 = Math.ceil((totalPrize / 2) / roundStep) * roundStep;
            p2 = totalPrize - p1;
        }
    }

    const profit = sales - totalPrize;
    const margin = sales > 0 ? (profit / sales) * 100 : 0;

    document.getElementById('resTotalPrize').textContent = formatBRL(totalPrize);
    document.getElementById('resP1').textContent = formatBRL(p1);
    document.getElementById('resP2').textContent = formatBRL(p2);
    document.getElementById('resProfit').textContent = formatBRL(profit);
    document.getElementById('resMargin').textContent = margin.toFixed(2).replace('.', ',') + '%';
    document.getElementById('resMarginBar').style.width = Math.min(Math.max(margin, 0), 100) + '%';

    // Estimations
    const bundleQty = <?= $bundleQty ?>;
    const bundlePrice = <?= $bundlePrice ?>;
    const singlePrice = <?= $singlePrice ?>;

    const numPacks = bundlePrice > 0 ? Math.floor(sales / bundlePrice) : 0;
    const packCards = numPacks * bundleQty;
    const singleCards = singlePrice > 0 ? Math.floor(sales / singlePrice) : 0;
    document.getElementById('estPackOnly').textContent = `${packCards} cartelas (${numPacks} pacotes de ${bundleQty})`;
    document.getElementById('estSingleOnly').textContent = `${singleCards} cartelas (a ${formatBRL(singlePrice)})`;
}

recalculateSimulation();
</script>
