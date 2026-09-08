<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title">Gestão de Operadores e Equipe</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Cadastre e gerencie os acessos de caixas, gerentes financeiros e gerentes do evento.
        </p>
    </div>
    <button class="btn btn-primary" onclick="openNewUserModal()">
        ➕ Novo Operador
    </button>
</div>

<!-- Quick Stats -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem 1.25rem;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total na Equipe</div>
        <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main);"><?= count($users) ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem 1.25rem;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Caixas Ativos</div>
        <div style="font-size: 1.75rem; font-weight: 800; color: #d97706;">
            <?= count(array_filter($users, fn($u) => $u['role'] === 'CAIXA' && $u['active'])) ?>
        </div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem 1.25rem;">
        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Gerentes Ativos</div>
        <div style="font-size: 1.75rem; font-weight: 800; color: #0284c7;">
            <?= count(array_filter($users, fn($u) => in_array($u['role'], ['GERENTE_EVENTO', 'GERENTE_FINANCEIRO']) && $u['active'])) ?>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Usuário (Login)</th>
                    <th>Cargo / Perfil</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            Nenhum operador cadastrado. Clique no botão acima para adicionar.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--text-main);"><?= View::e($u['name']) ?></strong>
                            <?php if ($u['id'] === Auth::id()): ?>
                                <span class="badge" style="background: #e2e8f0; color: #475569; font-size: 0.7rem; margin-left: 0.4rem;">VOCÊ</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= View::e($u['login']) ?></code></td>
                        <td>
                            <span class="badge" style="<?= Auth::roleBadgeColor($u['role']) ?> font-weight: 700; padding: 0.3rem 0.6rem; border-radius: 6px;">
                                <?= Auth::roleLabel($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['active']): ?>
                                <span class="badge badge-ok">🟢 ATIVO</span>
                            <?php else: ?>
                                <span class="badge badge-closed">⚪ INATIVO</span>
                            <?php endif; ?>
                        </td>
                        <td><?= View::date($u['created_at']) ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn btn-sm btn-secondary" onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar dados">
                                ✏️ Editar
                            </button>

                            <?php if ($u['id'] !== Auth::id()): ?>
                                <form method="POST" action="<?= View::url('operadores/status') ?>" style="display: inline-block; margin-left: 0.25rem;">
                                    <?= Csrf::inputField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $u['active'] ? 'btn-danger' : 'btn-primary' ?>" 
                                            onclick="return confirm('Deseja realmente <?= $u['active'] ? 'desativar' : 'ativar' ?> o acesso de <?= View::e($u['name']) ?>?');">
                                        <?= $u['active'] ? '🔒 Desativar' : '🔓 Ativar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Operador -->
<div id="modalNewUser" class="modal-overlay" style="display: none;">
    <div class="modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.25rem;">➕ Cadastrar Novo Operador</h3>
            <button type="button" class="btn-close" onclick="closeNewUserModal()">&times;</button>
        </div>

        <form method="POST" action="<?= View::url('operadores/novo') ?>">
            <?= Csrf::inputField() ?>

            <div class="form-group">
                <label class="form-label editable-label" for="new_name">Nome Completo</label>
                <input type="text" id="new_name" name="name" class="form-control editable-input" required placeholder="Ex: Maria Santos">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="new_login">Nome de Usuário (Login)</label>
                <input type="text" id="new_login" name="login" class="form-control editable-input" required placeholder="Ex: mariasantos">
                <small style="color: var(--text-muted);">Usado para entrar no sistema.</small>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="new_password">Senha de Acesso</label>
                <input type="password" id="new_password" name="password" class="form-control editable-input" required minlength="6" placeholder="Mínimo 6 caracteres">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="new_role">Função / Cargo</label>
                <select id="new_role" name="role" class="form-control editable-input" required>
                    <option value="CAIXA">💵 Operador de Caixa (guichê, abertura e vendas)</option>
                    <?php if ($isAdmin): ?>
                        <option value="GERENTE_FINANCEIRO">💼 Gerente Financeiro (caixa, relatórios contábeis)</option>
                        <option value="GERENTE_EVENTO">🎪 Gerente do Evento (rodadas, sorteios, vendedores)</option>
                        <option value="ADMIN">🛡️ Administrador Master (acesso total irrevogável)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeNewUserModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Cadastrar Operador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Operador -->
<div id="modalEditUser" class="modal-overlay" style="display: none;">
    <div class="modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.25rem;">✏️ Editar Operador</h3>
            <button type="button" class="btn-close" onclick="closeEditUserModal()">&times;</button>
        </div>

        <form method="POST" action="<?= View::url('operadores/editar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" id="edit_user_id" name="user_id" value="">

            <div class="form-group">
                <label class="form-label editable-label" for="edit_name">Nome Completo</label>
                <input type="text" id="edit_name" name="name" class="form-control editable-input" required>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_login">Nome de Usuário (Login)</label>
                <input type="text" id="edit_login" name="login" class="form-control editable-input" required>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_role">Função / Cargo</label>
                <select id="edit_role" name="role" class="form-control editable-input" required>
                    <option value="CAIXA">💵 Operador de Caixa</option>
                    <?php if ($isAdmin): ?>
                        <option value="GERENTE_FINANCEIRO">💼 Gerente Financeiro</option>
                        <option value="GERENTE_EVENTO">🎪 Gerente do Evento</option>
                        <option value="ADMIN">🛡️ Administrador Master</option>
                        <option value="OPERATOR">⚙️ Operador Geral</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_password">Nova Senha (opcional)</label>
                <input type="password" id="edit_password" name="password" class="form-control editable-input" minlength="6" placeholder="Deixe em branco para manter a atual">
                <small style="color: var(--text-muted);">Preencha apenas se desejar redefinir a senha do operador.</small>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}
.modal-card {
    background: #ffffff;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    padding: 1.75rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid #e2e8f0;
}
.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    line-height: 1;
}
.btn-close:hover {
    color: #0f172a;
}
</style>

<script>
function openNewUserModal() {
    document.getElementById('modalNewUser').style.display = 'flex';
}
function closeNewUserModal() {
    document.getElementById('modalNewUser').style.display = 'none';
}

function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_login').value = user.login;
    document.getElementById('edit_password').value = '';
    
    const roleSelect = document.getElementById('edit_role');
    if (roleSelect) {
        roleSelect.value = user.role;
    }

    document.getElementById('modalEditUser').style.display = 'flex';
}
function closeEditUserModal() {
    document.getElementById('modalEditUser').style.display = 'none';
}

// Fechar modais ao clicar fora
window.addEventListener('click', function(e) {
    const mNew = document.getElementById('modalNewUser');
    const mEdit = document.getElementById('modalEditUser');
    if (e.target === mNew) closeNewUserModal();
    if (e.target === mEdit) closeEditUserModal();
});
</script>
