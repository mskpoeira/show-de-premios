<?php
use App\Core\View;

$seller = $statement['seller'];
$sales = $statement['sales'];
$summary = $statement['summary'];
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">📑 Extrato do Vendedor: <?= View::e($seller['name']) ?></h1>
        <p class="page-subtitle">Comprovante individual de vendas por rodada para conferência física e prestação de contas.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= View::url('vendedores') ?>" class="btn btn-secondary">
            ← Voltar para Vendedores
        </a>
        <button type="button" class="btn btn-success" onclick="shareSellerWhatsApp()">
            📱 Compartilhar no WhatsApp
        </button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir Canhoto
        </button>
    </div>
</div>

<!-- Statement Sheet -->
<div class="card" style="padding: 2.5rem; background: #ffffff;">

    <!-- Print Header -->
    <div style="border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                🏆 SHOW DE PRÊMIOS — EXTRATO INDIVIDUAL
            </h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">
                Comprovante de Prestação de Contas de Cartelas &bull; Emissão: <strong><?= date('d/m/Y H:i') ?></strong>
            </p>
        </div>
        <div style="text-align: right;">
            <span style="font-size: 1.1rem; font-weight: 700; color: var(--primary);">
                Vendedor(a): <?= View::e($seller['name']) ?> <?= !empty($seller['nickname']) ? '("' . View::e($seller['nickname']) . '")' : '' ?>
            </span>
        </div>
    </div>

    <!-- Summary KPI cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        
        <div style="background: var(--bg-alt); padding: 1rem; border-radius: 10px; border-left: 4px solid var(--success);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Arrecadado</div>
            <div style="font-size: 1.6rem; font-weight: 900; color: var(--success); font-family: monospace; margin-top: 0.25rem;">
                <?= View::money($summary['total_amount']) ?>
            </div>
        </div>

        <div style="background: var(--bg-alt); padding: 1rem; border-radius: 10px; border-left: 4px solid var(--primary);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total de Cartelas</div>
            <div style="font-size: 1.6rem; font-weight: 900; color: var(--primary); margin-top: 0.25rem;">
                <?= $summary['total_qty'] ?> un.
            </div>
        </div>

        <div style="background: var(--bg-alt); padding: 1rem; border-radius: 10px; border-left: 4px solid var(--warning);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Pacotes (3 por R$ 5)</div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #b45309; margin-top: 0.25rem;">
                <?= $summary['total_packages'] ?> pacotes
            </div>
        </div>

        <div style="background: var(--bg-alt); padding: 1rem; border-radius: 10px; border-left: 4px solid #06b6d4;">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Avulsas (R$ 2 cada)</div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #0891b2; margin-top: 0.25rem;">
                <?= $summary['total_singles'] ?> un.
            </div>
        </div>

        <div style="background: var(--bg-alt); padding: 1rem; border-radius: 10px; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Rodadas Participadas</div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #7c3aed; margin-top: 0.25rem;">
                <?= $summary['rounds_count'] ?>
            </div>
        </div>

    </div>

    <!-- Sales Table -->
    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-main);">
        Detalhamento por Rodada
    </h3>

    <?php if (empty($sales)): ?>
        <p style="color: var(--text-muted); padding: 1rem 0;">Nenhuma venda registrada para este vendedor até o momento.</p>
    <?php else: ?>
        <table class="table" style="font-size: 0.95rem;">
            <thead>
                <tr>
                    <th>Data</th>
                    <th style="text-align: center;">Rodada</th>
                    <th style="text-align: center;">Qtd Vendida</th>
                    <th>Composição dos Pacotes</th>
                    <th style="text-align: right;">Valor Total (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): 
                    $q = (int)$s['quantity'];
                    $packs = intdiv($q, 3);
                    $singles = $q % 3;
                ?>
                <tr>
                    <td><?= View::date($s['operation_date']) ?></td>
                    <td style="text-align: center;">
                        <span class="badge" style="background: var(--bg-alt); font-size: 0.95rem; font-weight: 700;">
                            Rodada <?= $s['round_number'] ?>
                        </span>
                    </td>
                    <td style="text-align: center; font-weight: 800; font-size: 1rem; color: var(--primary);">
                        <?= $q ?>
                    </td>
                    <td style="color: var(--text-muted);">
                        <?= $packs ?> pct (<?= $packs * 3 ?> cartelas) + <?= $singles ?> avulsa(s)
                    </td>
                    <td style="text-align: right; font-weight: 800; font-family: monospace; font-size: 1.05rem; color: var(--success);">
                        <?= View::money($s['amount']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-alt); font-weight: 800; font-size: 1rem;">
                    <td colspan="2">TOTAL CONSOLIDADO:</td>
                    <td style="text-align: center; color: var(--primary);"><?= $summary['total_qty'] ?></td>
                    <td><?= $summary['total_packages'] ?> pacotes + <?= $summary['total_singles'] ?> avulsas</td>
                    <td style="text-align: right; color: var(--success); font-family: monospace; font-size: 1.15rem;">
                        <?= View::money($summary['total_amount']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- Signature Fields for physical receipt -->
    <div style="margin-top: 4rem; display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; text-align: center;">
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong><?= View::e($seller['name']) ?></strong><br>
            <span style="font-size: 0.85rem; color: #64748b;">Assinatura do Vendedor(a)</span>
        </div>
        <div style="border-top: 1px solid #0f172a; padding-top: 0.5rem;">
            <strong>Tesouraria / Operador de Caixa</strong><br>
            <span style="font-size: 0.85rem; color: #64748b;">Conferido e Recebido</span>
        </div>
    </div>

</div>

<script>
function shareSellerWhatsApp() {
    const text = `*🏆 SHOW DE PRÊMIOS — EXTRATO DO VENDEDOR*\n` +
                 `*Vendedor(a):* <?= View::e($seller['name']) ?>\n` +
                 `*Data:* <?= date('d/m/Y') ?>\n` +
                 `------------------------------------\n` +
                 `*Total de Cartelas:* <?= $summary['total_qty'] ?> un.\n` +
                 `*Pacotes:* <?= $summary['total_packages'] ?> pacotes | *Avulsas:* <?= $summary['total_singles'] ?> un.\n` +
                 `*Total Arrecadado:* <?= View::money($summary['total_amount']) ?>\n` +
                 `*Rodadas Participadas:* <?= $summary['rounds_count'] ?>\n` +
                 `------------------------------------\n` +
                 `_Prestação de contas conferida com sucesso!_`;

    const url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}
</script>
