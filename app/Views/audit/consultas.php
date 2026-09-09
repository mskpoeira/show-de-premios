<?php
use AppCoreView;
use AppServicesTicketService;
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin: 0; font-size: 1.75rem; color: #0f172a; font-weight: 800;">
            🛡️ Auditoria de Consultas a Dados Pessoais (LGPD)
        </h1>
        <p style="margin: 0.25rem 0 0 0; color: #64748b;">
            Rastreamento completo e imutável de todos os acessos a nomes, CPFs, telefones e cartelas realizados por operadores.
        </p>
    </div>
    <div>
        <a href="/painel?tab=auditoria" style="background: #f1f5f9; color: #475569; padding: 0.6rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; border: 1px solid #cbd5e1;">
            ← Auditoria Geral do Sistema
        </a>
    </div>
</div>

<!-- Filtros de Auditoria -->
<div class="card" style="background: #fff; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
    <form action="/auditoria/consultas" method="GET" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Operador:</label>
            <input type="text" name="user" value="<?= View::e($filters['user']) ?>" placeholder="Nome do operador" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Cartela:</label>
            <input type="text" name="ticket" value="<?= View::e($filters['ticket']) ?>" placeholder="Ex: JDA-0001" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px; text-transform: uppercase;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Comprador Consultado:</label>
            <input type="text" name="buyer" value="<?= View::e($filters['buyer']) ?>" placeholder="Nome do comprador" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">CPF:</label>
            <input type="text" name="cpf" value="<?= View::e($filters['cpf']) ?>" placeholder="Apenas números" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Data Inicial:</label>
            <input type="date" name="date_from" value="<?= View::e($filters['date_from']) ?>" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Data Final:</label>
            <input type="date" name="date_to" value="<?= View::e($filters['date_to']) ?>" style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">Endereço IP:</label>
            <input type="text" name="ip" value="<?= View::e($filters['ip']) ?>" placeholder="Ex: 192.168..." style="width: 100%; padding: 0.55rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" style="background: #0284c7; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 700; cursor: pointer; flex: 1;">
                🔍 Filtrar
            </button>
            <a href="/auditoria/consultas" style="background: #f1f5f9; color: #475569; text-decoration: none; padding: 0.6rem 1rem; border-radius: 6px; font-weight: 600; border: 1px solid #cbd5e1; text-align: center;">
                Limpar
            </a>
        </div>
    </form>
</div>

<!-- Tabela de Logs de Acesso a Dados Pessoais -->
<div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
    <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 700; color: #334155;">Registros de Acesso Auditados: <?= $totalItems ?></span>
        <span style="font-size: 0.85rem; color: #64748b;">Página <?= $page ?> de <?= max(1, $totalPages) ?></span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
        <thead>
            <tr style="background: #f1f5f9; color: #475569; font-weight: 700;">
                <th style="padding: 0.75rem 1rem;">Data/Hora</th>
                <th style="padding: 0.75rem 1rem;">Operador</th>
                <th style="padding: 0.75rem 1rem;">Perfil</th>
                <th style="padding: 0.75rem 1rem;">Tipo de Acesso</th>
                <th style="padding: 0.75rem 1rem;">Método</th>
                <th style="padding: 0.75rem 1rem;">Cartela</th>
                <th style="padding: 0.75rem 1rem;">Comprador Consultado</th>
                <th style="padding: 0.75rem 1rem;">IP / Sessão</th>
                <th style="padding: 0.75rem 1rem;">Resultado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($accessLogs)): ?>
                <tr>
                    <td colspan="9" style="padding: 2.5rem; text-align: center; color: #64748b;">
                        Nenhuma consulta registrada com os filtros aplicados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($accessLogs as $log): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 1rem; color: #64748b; white-space: nowrap;">
                            <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0f172a;">
                            <?= View::e($log['user_name']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <span style="background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                <?= View::e($log['user_role']) ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-weight: 600;">
                            <?php if ($log['access_type'] === 'TICKET_CALL_WINNER'): ?>
                                📞 Ligação para Ganhador
                            <?php elseif ($log['access_type'] === 'TICKET_WHATSAPP_WINNER'): ?>
                                💬 WhatsApp para Ganhador
                            <?php elseif ($log['access_type'] === 'VIEW_DETAILS'): ?>
                                👁️ Visualização de Dados
                            <?php elseif ($log['access_type'] === 'MANUAL_CONFERENCE'): ?>
                                🔍 Conferência Manual
                            <?php else: ?>
                                <?= View::e($log['access_type']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: #64748b;">
                            <?= View::e($log['method']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0284c7;">
                            <?= View::e($log['ticket_number'] ?: '--') ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <?= View::e($log['buyer_name'] ?: 'Não identificado') ?><br>
                            <small style="color: #64748b;">CPF: <?= View::e(TicketService::maskCpf($log['buyer_cpf'] ?? null)) ?></small>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: #64748b; font-family: monospace; font-size: 0.8rem;">
                            <?= View::e($log['ip_address']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <span style="color: #16a34a; font-weight: 700;">✅ <?= View::e($log['result']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0;">
            <?php for ($p = 1; $p <= min(15, $totalPages); $p++): ?>
                <a href="/auditoria/consultas?page=<?= $p ?>&<?= http_build_query($filters) ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-weight: 600; <?= $p === $page ? 'background:#0284c7; color:#fff;' : 'background:#fff; color:#334155; border:1px solid #cbd5e1;' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
