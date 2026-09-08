<?php
use App\Core\Csrf;
use App\Core\View;
?>

<div class="page-header">
    <h1 class="page-title">Backup e Restauração</h1>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Export Card -->
    <div class="card">
        <div class="card-title">📤 Exportar Backup Completo</div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Baixe um arquivo JSON criptograficamente estruturado contendo todos os dados do Show de Prêmios (vendas, rodadas, vendedores, caixa e auditoria).
        </p>

        <a href="<?= View::url('backup/exportar') ?>" class="btn btn-primary" style="width: 100%;">
            Baixar Backup JSON Agora
        </a>
    </div>

    <!-- Restore Card -->
    <div class="card" style="border-top: 4px solid var(--danger);">
        <div class="card-title">📥 Restaurar Dados de Backup</div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Antes de qualquer restauração, o sistema <strong>gerará automaticamente um snapshot prévio</strong> do estado atual para prevenção de perda acidental.
        </p>

        <form method="POST" action="<?= View::url('backup/restaurar') ?>" enctype="multipart/form-data">
            <?= Csrf::inputField() ?>

            <div class="form-group">
                <label class="form-label" for="backup_file">Arquivo JSON de Backup</label>
                <input type="file" id="backup_file" name="backup_file" class="form-control" accept=".json" required>
            </div>

            <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('ATENÇÃO: A restauração substituirá os dados atuais pelo arquivo selecionado. Um backup automático do estado atual será salvo antes da troca. Deseja prosseguir?');">
                Executar Restauração
            </button>
        </form>
    </div>
</div>

<!-- Backups Directory Listing -->
<div class="card">
    <div class="card-title">Backups Armazenados no Servidor</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome do Arquivo</th>
                    <th>Tamanho</th>
                    <th>Data de Geração</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files)): ?>
                    <tr><td colspan="3" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhum backup em arquivo local ainda.</td></tr>
                <?php else: ?>
                    <?php foreach ($files as $f): ?>
                    <tr>
                        <td><strong><?= View::e($f['name']) ?></strong></td>
                        <td><?= round($f['size'] / 1024, 1) ?> KB</td>
                        <td><?= date('d/m/Y H:i:s', $f['date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
