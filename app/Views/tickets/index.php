<?php
use App\Core\View;
use App\Services\TicketService;
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin: 0; font-size: 1.75rem; color: #0f172a; font-weight: 800;">🎟️ Gerenciamento de Cartelas</h1>
        <p style="margin: 0.25rem 0 0 0; color: #64748b;">Inventário oficial, controle de lotes, validação e emissão de segunda via.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button onclick="openBatchModal()" class="btn btn-primary" style="background: #0284c7; color: white; border: none; padding: 0.65rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer;">
            ➕ Gerar Lote de Cartelas
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="background: #fff; padding: 1.25rem; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
    <form action="/cartelas" method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Buscar Cartela / Comprador / CPF / Código:</label>
            <input type="text" name="search" value="<?= View::e($search) ?>" placeholder="Ex: JDA-0001, João, K7P4..." style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Situação:</label>
            <select name="status" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                <option value="">Todas as Situações</option>
                <option value="VALID" <?= $status === 'VALID' ? 'selected' : '' ?>>Válida</option>
                <option value="AWARDED" <?= $status === 'AWARDED' ? 'selected' : '' ?>>Premiada</option>
                <option value="RESERVED" <?= $status === 'RESERVED' ? 'selected' : '' ?>>Reservada</option>
                <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pendente</option>
                <option value="CANCELLED" <?= $status === 'CANCELLED' ? 'selected' : '' ?>>Cancelada</option>
                <option value="INVALID" <?= $status === 'INVALID' ? 'selected' : '' ?>>Invalidada</option>
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Lote:</label>
            <select name="batch_id" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                <option value="0">Todos os Lotes</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchId === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= View::e($b['batch_code']) ?> (Prefixo <?= View::e($b['prefix']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <button type="submit" style="background: #0284c7; color: white; border: none; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer;">
                🔍 Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Tabela de Cartelas -->
<div class="card" style="background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden;">
    <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 700; color: #334155;">Total de Cartelas: <?= $totalItems ?></span>
        <span style="font-size: 0.85rem; color: #64748b;">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>
    </div>

    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
        <thead>
            <tr style="background: #f1f5f9; color: #475569; font-weight: 700;">
                <th style="padding: 0.75rem 1rem;">Nº Cartela</th>
                <th style="padding: 0.75rem 1rem;">Código Conf.</th>
                <th style="padding: 0.75rem 1rem;">Lote</th>
                <th style="padding: 0.75rem 1rem;">Comprador</th>
                <th style="padding: 0.75rem 1rem;">Telefone</th>
                <th style="padding: 0.75rem 1rem;">Status</th>
                <th style="padding: 0.75rem 1rem;">Impressões</th>
                <th style="padding: 0.75rem 1rem; text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="8" style="padding: 2rem; text-align: center; color: #64748b;">Nenhuma cartela encontrada com os filtros selecionados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">
                            <?= View::e($t['ticket_number']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <code><?= View::e($t['check_code']) ?></code>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: #64748b;">
                            <?= View::e($t['batch_code']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <?= View::e($t['buyer_name'] ?: 'Sem comprador') ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: #64748b;">
                            <?= View::e(TicketService::maskPhone($t['buyer_phone'] ?? null)) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <?php if ($t['status'] === 'VALID'): ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">VÁLIDA</span>
                            <?php elseif ($t['status'] === 'AWARDED'): ?>
                                <span style="background: #fef08a; color: #854d0e; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">🏆 PREMIADA</span>
                            <?php elseif ($t['status'] === 'PENDING'): ?>
                                <span style="background: #fef3c7; color: #b45309; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">PENDENTE</span>
                            <?php elseif ($t['status'] === 'INVALID'): ?>
                                <span style="background: #fee2e2; color: #b91c1c; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;">INVALIDADA</span>
                            <?php else: ?>
                                <span style="background: #e2e8f0; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;"><?= View::e($t['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: #64748b;">
                            <?= (int)$t['print_count'] ?>x
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            <a href="/cartelas/imprimir/<?= View::e($t['secure_token']) ?>" target="_blank" style="color: #0284c7; text-decoration: none; font-weight: 600; margin-right: 0.75rem;" title="Imprimir cartela">
                                🖨️ Imprimir
                            </a>
                            <a href="/v/<?= View::e($t['secure_token']) ?>" target="_blank" style="color: #0f766e; text-decoration: none; font-weight: 600; margin-right: 0.75rem;" title="Ver validação QR">
                                🔍 QR
                            </a>
                            <?php if ($t['status'] === 'VALID'): ?>
                                <button onclick="invalidateTicket(<?= $t['id'] ?>, '<?= View::e($t['ticket_number']) ?>')" style="background: none; border: none; color: #dc2626; cursor: pointer; font-weight: 600;" title="Invalidar cartela">
                                    🚫 Invalidar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0;">
            <?php for ($p = 1; $p <= min(15, $totalPages); $p++): ?>
                <a href="/cartelas?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&batch_id=<?= $batchId ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-weight: 600; <?= $p === $page ? 'background:#0284c7; color:#fff;' : 'background:#fff; color:#334155; border:1px solid #cbd5e1;' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function invalidateTicket(id, number) {
    const reason = prompt('Informe o motivo da invalidação da cartela ' + number + ':');
    if (!reason) return;

    fetch('/cartelas/invalidar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ticket_id=' + id + '&reason=' + encodeURIComponent(reason)
    }).then(res => res.json()).then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

function openBatchModal() {
    const qty = prompt('Quantas cartelas deseja gerar para o lote?', '100');
    if (!qty) return;

    fetch('/cartelas/gerar-lote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'batch_id=1&quantity=' + encodeURIComponent(qty)
    }).then(res => res.json()).then(data => {
        if (data.success) {
            alert(data.message + '\nPrimeira: ' + data.first + ' | Última: ' + data.last);
            window.location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}
</script>
