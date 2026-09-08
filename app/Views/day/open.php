<?php
use App\Core\Csrf;
use App\Core\View;
?>

<div class="page-header">
    <h1 class="page-title">+ Abertura de Novo Dia</h1>
    <a href="<?= View::url('painel') ?>" class="btn btn-secondary">← Voltar ao Painel</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="<?= View::url('dias/abrir') ?>">
        <?= Csrf::inputField() ?>

        <div class="form-group">
            <label class="form-label editable-label" for="operation_date">Data da Operação</label>
            <input type="date" id="operation_date" name="operation_date" class="form-control editable-input" required min="2026-09-06" value="<?= View::e($defaultDate) ?>">
            <small style="color: var(--text-muted);">Início padrão do sistema: 06/09/2026. Não são aceitas datas anteriores.</small>
        </div>

        <div class="form-group">
            <label class="form-label editable-label" for="initial_cash">Troco / Caixa Inicial (R$)</label>
            <input type="text" id="initial_cash" name="initial_cash" class="form-control editable-input" placeholder="0,00" value="0,00">
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Observações do Dia</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Informações adicionais sobre o local, clima ou equipe"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
            <a href="<?= View::url('painel') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Confirmar Abertura do Dia</button>
        </div>
    </form>
</div>
