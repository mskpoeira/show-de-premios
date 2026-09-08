// Show de Prêmios - Client Script

function formatMoney(value) {
    return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function parseMoney(str) {
    if (!str) return 0;
    const clean = str.replace(/[R$\s.]/g, '').replace(',', '.');
    return parseFloat(clean) || 0;
}

// Web Audio API Synthesis for native feedback sounds without audio files
function playBeepSound(type = 'click') {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        if (type === 'click') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, ctx.currentTime);
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.05);
        } else if (type === 'cash') {
            [1200, 1600].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + (i * 0.07));
                gain.gain.setValueAtTime(0.12, ctx.currentTime + (i * 0.07));
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (i * 0.07) + 0.15);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + (i * 0.07));
                osc.stop(ctx.currentTime + (i * 0.07) + 0.15);
            });
        } else if (type === 'warning') {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(320, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        }
    } catch (e) {
        // Silent catch if audio is blocked by browser policy
    }
}

// Live Sales Calculator
function initSalesCalculator(config) {
    const bundleQty = config.bundle_quantity || 3;
    const bundlePrice = config.bundle_price || 5.00;
    const singlePrice = config.single_price || 2.00;

    const qtyInputs = document.querySelectorAll('.sale-qty-input');
    const prize1Input = document.getElementById('prize_1');
    const prize2Input = document.getElementById('prize_2');

    // Extract round ID for auto-save draft
    const roundIdInput = document.querySelector('input[name="round_id"]');
    const roundId = roundIdInput ? roundIdInput.value : 'current';
    const draftKey = `showdepremios_draft_round_${roundId}`;

    function saveDraft() {
        const draft = {};
        qtyInputs.forEach(input => {
            const val = parseInt(input.value, 10) || 0;
            if (val > 0) {
                draft[input.name] = val;
            }
        });
        localStorage.setItem(draftKey, JSON.stringify(draft));
    }

    function calculateLine(input) {
        const qty = parseInt(input.value, 10) || 0;
        const packages = Math.floor(qty / bundleQty);
        const singles = qty % bundleQty;
        const total = (packages * bundlePrice) + (singles * singlePrice);

        const row = input.closest('.sales-row') || input.closest('tr');
        if (row) {
            const amountField = row.querySelector('.sale-amount-display');
            if (amountField) {
                amountField.value = formatMoney(total);
            }
            const helperSpan = row.querySelector('.sale-helper-text');
            if (helperSpan) {
                if (qty > 0) {
                    let desc = [];
                    if (packages > 0) desc.push(`${packages} pacote${packages > 1 ? 's' : ''}`);
                    if (singles > 0) desc.push(`${singles} avulsa${singles > 1 ? 's' : ''}`);
                    helperSpan.textContent = desc.join(' + ');
                } else {
                    helperSpan.textContent = '';
                }
            }
        }
        recalculateTotals();
        saveDraft();
    }

    function recalculateTotals() {
        let grandTotalSales = 0;
        let grandTotalQty = 0;

        qtyInputs.forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            const packages = Math.floor(qty / bundleQty);
            const singles = qty % bundleQty;
            const total = (packages * bundlePrice) + (singles * singlePrice);

            grandTotalQty += qty;
            grandTotalSales += total;
        });

        const totalQtyEl = document.getElementById('total_qty_display');
        const totalSalesEl = document.getElementById('total_sales_display');
        if (totalQtyEl) totalQtyEl.textContent = grandTotalQty;
        if (totalSalesEl) totalSalesEl.textContent = formatMoney(grandTotalSales);

        // Profit & Margin
        const p1 = prize1Input ? parseMoney(prize1Input.value) : 0;
        const p2 = prize2Input ? parseMoney(prize2Input.value) : 0;
        const totalPrizes = p1 + p2;
        const profit = grandTotalSales - totalPrizes;
        const margin = grandTotalSales > 0 ? (profit / grandTotalSales) * 100 : 0;

        const profitEl = document.getElementById('total_profit_display');
        const marginEl = document.getElementById('total_margin_display');
        if (profitEl) {
            profitEl.textContent = formatMoney(profit);
            profitEl.className = 'kpi-value ' + (profit >= 0 ? 'positive' : 'negative');
        }
        if (marginEl) {
            marginEl.textContent = margin.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            marginEl.className = 'kpi-value ' + (margin >= 0 ? 'positive' : 'negative');
        }
    }

    qtyInputs.forEach((input, index) => {
        input.addEventListener('input', () => calculateLine(input));
        
        // Keyboard navigation: Enter and Down arrow move to next, Up arrow moves to previous
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                const next = qtyInputs[index + 1];
                if (next) {
                    next.focus();
                    next.select();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = qtyInputs[index - 1];
                if (prev) {
                    prev.focus();
                    prev.select();
                }
            }
        });
    });

    if (prize1Input) prize1Input.addEventListener('input', recalculateTotals);
    if (prize2Input) prize2Input.addEventListener('input', recalculateTotals);

    // Form submit cleans local draft
    const form = document.querySelector('form[action*="salvar-vendas"]');
    if (form) {
        form.addEventListener('submit', () => {
            localStorage.removeItem(draftKey);
            playBeepSound('cash');
        });
    }
}

// Double-click form submission guard
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (submitBtn.dataset.submitted === 'true') {
                    e.preventDefault();
                    return false;
                }
                submitBtn.dataset.submitted = 'true';
                submitBtn.style.opacity = '0.7';
                submitBtn.textContent = 'Processando...';
            }
        });
    });
});
