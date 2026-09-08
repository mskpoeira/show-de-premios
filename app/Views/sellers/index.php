<?php
use App\Core\Csrf;
use App\Core\View;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Cadastro de Vendedores(as)</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Gerenciamento dinâmico da equipe de vendas</p>
    </div>

    <div>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modalAddSeller').showModal()">
            + Adicionar Vendedor(a)
        </button>
    </div>
</div>

<!-- Initial 2 Empty Slots Notice if 0 sellers -->
<?php if (count($sellers) === 0): ?>
<div class="card" style="border-left: 4px solid var(--warning); background: #fffbeb;">
    <div style="font-weight: 600; color: #92400e; margin-bottom: 0.5rem;">
        ⚡ Posições Iniciais de Vendedores
    </div>
    <p style="color: #78350f; font-size: 0.9rem; margin-bottom: 1rem;">
        Para iniciar a operação, preencha o nome dos 2 primeiros vendedores nas posições abaixo:
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
        <form method="POST" action="<?= View::url('vendedores/criar') ?>" style="background: #ffffff; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <?= Csrf::inputField() ?>
            <label class="form-label editable-label">Posição 1: Nome do Vendedor(a)</label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="name" class="form-control editable-input" required placeholder="Informe o nome do vendedor 1">
                <button type="submit" class="btn btn-sm btn-primary">Cadastrar</button>
            </div>
        </form>

        <form method="POST" action="<?= View::url('vendedores/criar') ?>" style="background: #ffffff; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <?= Csrf::inputField() ?>
            <label class="form-label editable-label">Posição 2: Nome do Vendedor(a)</label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="name" class="form-control editable-input" required placeholder="Informe o nome do vendedor 2">
                <button type="submit" class="btn btn-sm btn-primary">Cadastrar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Sellers List -->
<div class="card">
    <div class="card-title">Equipe de Vendedores Cadastrados (<?= count($sellers) ?>)</div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Apelido</th>
                    <th>Status</th>
                    <th>Início</th>
                    <th class="text-right">Qtd Total Vendida</th>
                    <th class="text-right">Total em Vendas</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sellers)): ?>
                    <tr><td colspan="7" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhum vendedor cadastrado ainda. Use os campos acima ou o botão de adicionar.</td></tr>
                <?php else: ?>
                    <?php foreach ($sellers as $s): ?>
                    <tr style="<?= !$s['active'] ? 'opacity: 0.6; background: #f8fafc;' : '' ?>">
                        <td><strong><?= View::e($s['name']) ?></strong></td>
                        <td><?= View::e($s['nickname'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $s['active'] ? 'badge-ok' : 'badge-closed' ?>">
                                <?= $s['active'] ? 'ATIVO' : 'INATIVO' ?>
                            </span>
                        </td>
                        <td><?= View::date($s['started_at']) ?></td>
                        <td class="text-right"><?= number_format($s['total_qty'], 0, ',', '.') ?></td>
                        <td class="text-right"><strong><?= View::money($s['total_sales']) ?></strong></td>
                        <td class="text-center" style="display: flex; gap: 0.3rem; justify-content: center; align-items: center;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick='openEditSeller(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)' title="Editar nome, apelido e observações">
                                ✏️ Editar
                            </button>
                            <a href="<?= View::url('relatorios/vendedor?seller_id=' . $s['id']) ?>" class="btn btn-sm btn-primary" title="Ver e imprimir extrato de vendas individual">
                                📑 Extrato
                            </a>
                            <form method="POST" action="<?= View::url('vendedores/toggle') ?>" style="display: inline-block;">
                                <?= Csrf::inputField() ?>
                                <input type="hidden" name="seller_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $s['active'] ? 'btn-secondary' : 'btn-success' ?>" onclick="return confirm('<?= $s['active'] ? 'Deseja inativar este vendedor? O histórico antigo continuará intacto.' : 'Deseja reativar este vendedor?' ?>');">
                                    <?= $s['active'] ? 'Inativar' : 'Reativar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Seller -->
<dialog id="modalAddSeller" style="padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius); max-width: 480px; margin: auto;">
    <h3 style="margin-bottom: 1rem;">+ Cadastrar Novo Vendedor(a)</h3>
    <form method="POST" action="<?= View::url('vendedores/criar') ?>">
        <?= Csrf::inputField() ?>

        <div class="form-group">
            <label class="form-label editable-label" for="seller_name">Nome Completo</label>
            <input type="text" id="seller_name" name="name" class="form-control editable-input" required placeholder="Ex: Alexandre">
        </div>

        <div class="form-group">
            <label class="form-label" for="seller_nickname">Apelido (opcional)</label>
            <input type="text" id="seller_nickname" name="nickname" class="form-control" placeholder="Ex: Xande">
        </div>

        <div class="form-group">
            <label class="form-label" for="seller_notes">Observações</label>
            <textarea id="seller_notes" name="notes" class="form-control" rows="2" placeholder="Contato ou dados adicionais"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalAddSeller').close()">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar Vendedor</button>
        </div>
    </form>
</dialog>

<!-- Modal Edit Seller -->
<dialog id="modalEditSeller" style="padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius); max-width: 480px; margin: auto;">
    <h3 style="margin-bottom: 1rem;">✏️ Editar Vendedor(a)</h3>
    <form method="POST" action="<?= View::url('vendedores/editar') ?>">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="seller_id" id="edit_seller_id">

        <div class="form-group">
            <label class="form-label editable-label" for="edit_seller_name">Nome Completo</label>
            <input type="text" id="edit_seller_name" name="name" class="form-control editable-input" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="edit_seller_nickname">Apelido</label>
            <input type="text" id="edit_seller_nickname" name="nickname" class="form-control">
        </div>

        <div class="form-group">
            <label class="form-label" for="edit_seller_notes">Observações</label>
            <textarea id="edit_seller_notes" name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditSeller').close()">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
    </form>
</dialog>

<script>
function openEditSeller(seller) {
    document.getElementById('edit_seller_id').value = seller.id;
    document.getElementById('edit_seller_name').value = seller.name || '';
    document.getElementById('edit_seller_nickname').value = seller.nickname || '';
    document.getElementById('edit_seller_notes').value = seller.notes || '';
    document.getElementById('modalEditSeller').showModal();
}
</script>
