<?php
use App\Core\View;
use App\Services\AuditService;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Trilha de Auditoria do Sistema</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Registro oficial e imutável de todas as movimentações, acessos e operações com horários exatos no Horário Oficial de Brasília (BRT / UTC-3). Acesso restrito ao Administrador Master.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-title">Histórico de Atividades (<?= $totalLogs ?> registros)</div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Data e Horário (Brasília)</th>
                    <th>Ação Realizada</th>
                    <th>Usuário / Operador</th>
                    <th>Módulo / Entidade</th>
                    <th>ID</th>
                    <th>IP</th>
                    <th>Detalhes da Operação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhum registro de auditoria.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <strong style="color: #0f172a;"><?= View::datetime($l['created_at']) ?></strong>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #1e40af;">
                                <?= AuditService::translateAction($l['action']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= View::e($l['user_name'] ?? 'Sistema') ?></strong>
                            <?php if (!empty($l['user_login'])): ?>
                                <small style="display: block; color: var(--text-muted);">(<?= View::e($l['user_login']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge" style="background: #f1f5f9; color: #334155; font-size: 0.8rem;"><?= AuditService::translateEntity($l['entity_type']) ?></span></td>
                        <td><?= $l['entity_id'] ?: '-' ?></td>
                        <td><small><?= View::e($l['ip_address']) ?></small></td>
                        <td>
                            <?php if ($l['old_values_json']): ?>
                                <small style="display: block; color: #dc2626; word-break: break-word;"><strong>Anterior:</strong> <?= View::e($l['old_values_json']) ?></small>
                            <?php endif; ?>
                            <?php if ($l['new_values_json']): ?>
                                <small style="display: block; color: #059669; word-break: break-word;"><strong>Atualizado:</strong> <?= View::e($l['new_values_json']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= View::url('auditoria?page=' . $p) ?>" class="btn btn-sm <?= $p == $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
