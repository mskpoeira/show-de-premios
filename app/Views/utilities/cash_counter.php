<?php
use App\Core\View;
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">💵 Contador de Cédulas & Moedas</h1>
        <p class="page-subtitle">Ferramenta para conferência de dinheiro físico e fechamento do caixa sem erros de contagem.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <button type="button" class="btn btn-secondary" onclick="resetCounter()">
            🔄 Limpar Tudo
        </button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir Espelho de Contagem
        </button>
        <?php if (!empty($activeDay)): ?>
            <a href="<?= View::url('dia') ?>" class="btn btn-success" id="btnSendToDay">
                ➔ Concluir Fechamento no Dia
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Printable Slip Card -->
<div class="card" style="padding: 2rem;">

    <!-- Print-only header -->
    <div class="print-only" style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">ESPELHO DE CONTAGEM FÍSICA DE DINHEIRO</h2>
        <p style="color: #64748b; font-size: 0.9rem;">
            Show de Prêmios &bull; Data: <strong><?= date('d/m/Y H:i') ?></strong>
            <?php if (!empty($activeDay)): ?>
                &bull; Dia de Operação: <strong><?= View::date($activeDay['operation_date']) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
        
        <!-- Cédulas Section -->
        <div>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
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
                            <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; background: <?= $n['bg'] ?>; color: #0f172a;">
                                <?= $n['label'] ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <input type="number" min="0" step="1" value="0"
                                   class="form-control form-control-editable note-input"
                                   data-value="<?= $n['val'] ?>"
                                   style="text-align: center; font-weight: 700;"
                                   onfocus="this.select()"
                                   oninput="calculateCashCount()">
                        </td>
                        <td style="text-align: right; font-weight: 700; font-family: monospace; font-size: 1rem;" id="sub_note_<?= $n['val'] ?>">
                            R$ 0,00
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--bg-alt); font-weight: 800;">
                        <td colspan="2">Subtotal Cédulas:</td>
                        <td style="text-align: right; color: var(--primary);" id="totalNotes">R$ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Moedas Section -->
        <div>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
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
                            <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; background: <?= $c['bg'] ?>; color: #0f172a;">
                                <?= $c['label'] ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <input type="number" min="0" step="1" value="0"
                                   class="form-control form-control-editable coin-input"
                                   data-value="<?= $c['val'] ?>"
                                   style="text-align: center; font-weight: 700;"
                                   onfocus="this.select()"
                                   oninput="calculateCashCount()">
                        </td>
                        <td style="text-align: right; font-weight: 700; font-family: monospace; font-size: 1rem;" id="sub_coin_<?= str_replace('.', '_', (string)$c['val']) ?>">
                            R$ 0,00
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--bg-alt); font-weight: 800;">
                        <td colspan="2">Subtotal Moedas:</td>
                        <td style="text-align: right; color: var(--primary);" id="totalCoins">R$ 0,00</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Grand Total Display Card -->
            <div style="margin-top: 1.5rem; background: linear-gradient(135deg, #0f172a, #1e293b); color: #ffffff; border-radius: 14px; padding: 1.5rem; text-align: center; box-shadow: var(--shadow-md);">
                <div style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700;">
                    Total Geral Contado
                </div>
                <div id="grandTotalDisplay" style="font-size: 2.5rem; font-weight: 900; color: #4ade80; margin: 0.5rem 0; font-family: monospace;">
                    R$ 0,00
                </div>
                <div style="font-size: 0.85rem; color: #cbd5e1;">
                    <span id="totalItemsCount">0 itens contabilizados</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Print signature line -->
    <div class="print-only" style="margin-top: 4rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; text-align: center;">
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Responsável pela Contagem</strong><br>
            <span style="font-size: 0.85rem; color: #64748b;">Assinatura</span>
        </div>
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Tesouraria / Coordenação</strong><br>
            <span style="font-size: 0.85rem; color: #64748b;">Conferido e De Acordo</span>
        </div>
    </div>

</div>

<script>
function formatBRL(val) {
    return 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function calculateCashCount() {
    let notesTotal = 0;
    let coinsTotal = 0;
    let totalItems = 0;

    document.querySelectorAll('.note-input').forEach(input => {
        const faceVal = parseFloat(input.dataset.value);
        const qty = parseInt(input.value, 10) || 0;
        const sub = faceVal * qty;
        notesTotal += sub;
        totalItems += qty;
        const el = document.getElementById(`sub_note_${faceVal}`);
        if (el) el.textContent = formatBRL(sub);
    });

    document.querySelectorAll('.coin-input').forEach(input => {
        const faceVal = parseFloat(input.dataset.value);
        const qty = parseInt(input.value, 10) || 0;
        const sub = faceVal * qty;
        coinsTotal += sub;
        totalItems += qty;
        const idSuffix = String(faceVal).replace('.', '_');
        const el = document.getElementById(`sub_coin_${idSuffix}`);
        if (el) el.textContent = formatBRL(sub);
    });

    const grandTotal = notesTotal + coinsTotal;

    document.getElementById('totalNotes').textContent = formatBRL(notesTotal);
    document.getElementById('totalCoins').textContent = formatBRL(coinsTotal);
    document.getElementById('grandTotalDisplay').textContent = formatBRL(grandTotal);
    document.getElementById('totalItemsCount').textContent = `${totalItems} itens contabilizados (${notesTotal > 0 ? formatBRL(notesTotal) + ' em notas' : ''} ${coinsTotal > 0 ? '+ ' + formatBRL(coinsTotal) + ' em moedas' : ''})`;

    // Save to localStorage
    localStorage.setItem('showdepremios_cash_counter_total', grandTotal.toFixed(2));

    // Update button link to day closing with auto-fill param
    const btnSend = document.getElementById('btnSendToDay');
    if (btnSend) {
        btnSend.href = '<?= View::url("dia") ?>?counted=' + grandTotal.toFixed(2);
    }
}

function resetCounter() {
    if (confirm('Deseja zerar toda a contagem de cédulas e moedas?')) {
        document.querySelectorAll('.note-input, .coin-input').forEach(input => {
            input.value = 0;
        });
        calculateCashCount();
    }
}
</script>
