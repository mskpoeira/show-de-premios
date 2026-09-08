<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$tab = $tab ?? 'gerais';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">⚙️ Configurações & Administração</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
            Central de controle de regras de preços, equipe de vendedores, operadores do sistema e cópias de segurança.
        </p>
    </div>
</div>

<!-- Sub-Abas Unificadas de Configurações (Botões Visuais) -->
<div class="nav-subtabs no-print">
    <a href="<?= View::url('configuracoes?tab=gerais') ?>" class="tab-pill <?= $tab === 'gerais' ? 'active' : '' ?>" role="button">
        ⚙️ Gerais & Preços
    </a>
    <a href="<?= View::url('configuracoes?tab=vendedores') ?>" class="tab-pill <?= $tab === 'vendedores' ? 'active' : '' ?>" role="button">
        👥 Vendedores(as)
        <?php if (!empty($sellers)): ?>
            <span class="tab-badge"><?= count($sellers) ?></span>
        <?php endif; ?>
    </a>
    <?php if (Auth::canManageUsers()): ?>
        <a href="<?= View::url('configuracoes?tab=operadores') ?>" class="tab-pill <?= $tab === 'operadores' ? 'active' : '' ?>" role="button">
            👤 Operadores
            <?php if (!empty($operators)): ?>
                <span class="tab-badge"><?= count($operators) ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
    <?php if (Auth::isAdmin()): ?>
        <a href="<?= View::url('configuracoes?tab=backup') ?>" class="tab-pill <?= $tab === 'backup' ? 'active' : '' ?>" role="button">
            💾 Backup & Dados
        </a>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- SUB-ABA 1: GERAIS & PREÇOS -->
<!-- ========================================================================= -->
<?php if ($tab === 'gerais'): ?>

    <!-- Regras de Preços Section -->
    <div class="card">
        <div class="card-title">Regras de Preços das Cartelas (Preservação Histórica)</div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
            Alterar os valores abaixo criará uma <strong>nova vigência de preços</strong> a partir da data informada. Todas as vendas anteriores são preservadas com seus valores históricos originais.
        </p>

        <form method="POST" action="<?= View::url('configuracoes/precos') ?>" style="margin-bottom: 2rem;">
            <?= Csrf::inputField() ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div class="form-group">
                    <label class="form-label editable-label" for="single_price">Preço Venda Avulsa (1 un.)</label>
                    <input type="text" id="single_price" name="single_price" class="form-control editable-input" required value="<?= number_format($currentPricing['single_price'] ?? 2.00, 2, ',', '.') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="bundle_quantity">Qtd Pacote Promocional</label>
                    <input type="number" id="bundle_quantity" name="bundle_quantity" class="form-control editable-input" required min="2" value="<?= (int)($currentPricing['bundle_quantity'] ?? 3) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="bundle_price">Preço Pacote Promocional</label>
                    <input type="text" id="bundle_price" name="bundle_price" class="form-control editable-input" required value="<?= number_format($currentPricing['bundle_price'] ?? 5.00, 2, ',', '.') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="effective_from">Vigência a partir de</label>
                    <input type="date" id="effective_from" name="effective_from" class="form-control editable-input" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Salvar Nova Regra de Preço
                    </button>
                </div>
            </div>
        </form>

        <h4 style="font-size: 0.95rem; margin-bottom: 0.6rem; color: #334155; font-weight: 800;">Histórico de Regras de Preço</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Vigência Início</th>
                        <th>Vigência Fim</th>
                        <th>Avulsa (1 un.)</th>
                        <th>Pacote Promocional</th>
                        <th>Status</th>
                        <?php if (Auth::isMasterAdmin()): ?>
                        <th class="text-center" style="width: 110px;">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricingHistory as $ph): ?>
                    <tr>
                        <td><?= View::date($ph['effective_from']) ?></td>
                        <td><?= $ph['effective_to'] ? View::date($ph['effective_to']) : 'Atual (Sem término)' ?></td>
                        <td><strong><?= View::money($ph['single_price']) ?></strong></td>
                        <td><?= $ph['bundle_quantity'] ?> cartelas por <strong><?= View::money($ph['bundle_price']) ?></strong></td>
                        <td>
                            <span class="badge <?= !$ph['effective_to'] ? 'badge-ok' : 'badge-closed' ?>">
                                <?= !$ph['effective_to'] ? 'VIGENTE' : 'HISTÓRICO' ?>
                            </span>
                        </td>
                        <?php if (Auth::isMasterAdmin()): ?>
                        <td class="text-center">
                            <?php if (count($pricingHistory) > 1): ?>
                            <form method="POST" action="<?= View::url('configuracoes/precos/excluir') ?>" style="display: inline;" onsubmit="return confirm('ATENÇÃO: Tem certeza que deseja excluir esta regra de preços do dia <?= View::date($ph['effective_from']) ?>?\n\n<?= empty($ph['effective_to']) ? 'Esta regra é a VIGENTE ATUAL. Ao excluí-la, a regra anterior mais recente se tornará a vigente!' : 'Esta regra histórica será removida permanentemente.' ?>');">
                                <?= Csrf::inputField() ?>
                                <input type="hidden" name="rule_id" value="<?= (int)$ph['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.25rem 0.6rem; font-size: 0.78rem;" title="Excluir esta regra de preço">
                                    🗑️ Excluir
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted" style="font-size: 0.75rem; font-style: italic;" title="O sistema exige ao menos uma regra cadastrada">Regra Única</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Parâmetros do Evento e Cálculos -->
    <div class="card">
        <div class="card-title">Parâmetros do Evento e Cálculos Automáticos</div>

        <form method="POST" action="<?= View::url('configuracoes/parametros') ?>">
            <?= Csrf::inputField() ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label editable-label" for="system_title">Nome Oficial do Show de Prêmios / Evento</label>
                    <input type="text" id="system_title" name="system_title" class="form-control editable-input" required value="<?= View::e($settings['system_title'] ?? View::systemTitle()) ?>" placeholder="Ex: Show de Prêmios — Paróquia São Francisco">
                    <small style="color: var(--text-muted);">Este nome será exibido no topo do sistema, no Telão Oficial e em todos os relatórios impressos.</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_pool_percent">% Vendas p/ Premiação Sugerida</label>
                    <input type="number" id="prize_pool_percent" name="prize_pool_percent" class="form-control editable-input" required min="1" max="100" value="<?= View::e($settings['prize_pool_percent'] ?? '50') ?>">
                    <small style="color: var(--text-muted);">Padrão: 50%</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_1_percent">% Divisão 1º Prêmio</label>
                    <input type="number" id="prize_1_percent" name="prize_1_percent" class="form-control editable-input" required min="1" max="100" value="<?= View::e($settings['prize_1_percent'] ?? '65') ?>">
                    <small style="color: var(--text-muted);">Padrão: 65%</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_2_percent">% Divisão 2º Prêmio</label>
                    <input type="number" id="prize_2_percent" name="prize_2_percent" class="form-control editable-input" required min="1" max="100" value="<?= View::e($settings['prize_2_percent'] ?? '35') ?>">
                    <small style="color: var(--text-muted);">Padrão: 35%</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="prize_rounding">Múltiplo de Arredondamento (R$)</label>
                    <input type="text" id="prize_rounding" name="prize_rounding" class="form-control editable-input" required value="<?= number_format((float)($settings['prize_rounding'] ?? 10.00), 2, ',', '.') ?>">
                    <small style="color: var(--text-muted);">Ex: 10,00 (arredonda prêmios para dezenas inteiras)</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="cash_tolerance">Tolerância de Divergência de Caixa (R$)</label>
                    <input type="text" id="cash_tolerance" name="cash_tolerance" class="form-control editable-input" required value="<?= number_format((float)($settings['cash_tolerance'] ?? 0.01), 2, ',', '.') ?>">
                    <small style="color: var(--text-muted);">Diferenças abaixo deste valor são consideradas normais</small>
                </div>

                <div class="form-group" style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        💾 Salvar Parâmetros
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Configuração Oficial do PIX -->
    <div class="card" style="border-top: 4px solid #0284c7; margin-top: 1.5rem;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span>⚡ Configuração Oficial do PIX (Telão, Caixa e Vendas)</span>
            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: 6px;">
                EXIBIÇÃO OFICIAL NO TELÃO
            </span>
        </div>

        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Configure a Chave PIX oficial e os dados do titular da conta do evento. Durante as rodadas com vendas abertas, o sistema exibirá automaticamente o QR Code de pagamento e a chave no Telão para os participantes realizarem o pagamento direto de seus lugares.
        </p>

        <form method="POST" action="<?= View::url('configuracoes/pix') ?>">
            <?= Csrf::inputField() ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label editable-label" for="pix_key_type">Tipo de Chave PIX</label>
                    <select id="pix_key_type" name="pix_key_type" class="form-control editable-input" onchange="updatePixPreview()">
                        <option value="EMAIL" <?= ($settings['pix_key_type'] ?? '') === 'EMAIL' ? 'selected' : '' ?>>📧 E-mail</option>
                        <option value="CPF" <?= ($settings['pix_key_type'] ?? '') === 'CPF' ? 'selected' : '' ?>>🪪 CPF</option>
                        <option value="CNPJ" <?= ($settings['pix_key_type'] ?? '') === 'CNPJ' ? 'selected' : '' ?>>🏢 CNPJ</option>
                        <option value="TELEFONE" <?= ($settings['pix_key_type'] ?? '') === 'TELEFONE' ? 'selected' : '' ?>>📱 Celular / Telefone</option>
                        <option value="CHAVE_ALEATORIA" <?= in_array($settings['pix_key_type'] ?? '', ['CHAVE_ALEATORIA', 'ALEATORIA'], true) ? 'selected' : '' ?>>🔑 Chave Aleatória (EVP)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="pix_key">Chave PIX Oficial</label>
                    <input type="text" id="pix_key" name="pix_key" class="form-control editable-input" required 
                           value="<?= View::e($settings['pix_key'] ?? 'mskpoeira@gmail.com') ?>" 
                           placeholder="Ex: financeiro@paroquia.com.br ou 12.345.678/0001-90"
                           oninput="updatePixPreview()">
                    <small style="color: var(--text-muted);">Esta chave será enviada diretamente ao banco pelo padrão BCB.</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="pix_receiver_name">Nome do Favorecido / Recebedor (Titular)</label>
                    <input type="text" id="pix_receiver_name" name="pix_receiver_name" class="form-control editable-input" required maxlength="25"
                           value="<?= View::e($settings['pix_receiver_name'] ?? 'Show de Prêmios Retiro') ?>" 
                           placeholder="Ex: Paróquia Exaltação Santa Cruz"
                           oninput="updatePixPreview()">
                    <small style="color: var(--text-muted);">Padrão BCB: nome exibido no app do banco (máx. 25 caracteres sem acentos).</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="pix_receiver_city">Cidade</label>
                    <input type="text" id="pix_receiver_city" name="pix_receiver_city" class="form-control editable-input" maxlength="15"
                           value="<?= View::e($settings['pix_receiver_city'] ?? 'São Paulo') ?>" 
                           placeholder="Ex: Ubatuba ou São Paulo"
                           oninput="updatePixPreview()">
                    <small style="color: var(--text-muted);">Padrão BCB: cidade de liquidação da chave (máx. 15 caracteres sem acentos, sem UF).</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="pix_description">Observação no PIX (Descrição da Transação)</label>
                    <input type="text" id="pix_description" name="pix_description" class="form-control editable-input" maxlength="50"
                           value="<?= View::e($settings['pix_description'] ?? 'Show de Prêmios') ?>" 
                           placeholder="Ex: Show de Prêmios - Cartelas"
                           oninput="updatePixPreview()">
                    <small style="color: var(--text-muted);">Padrão BCB (máx. 50 caracteres): aparece na tela de confirmação e no extrato do pagador.</small>
                </div>

                <div class="form-group">
                    <label class="form-label editable-label" for="pix_banner_title">Texto da Chamada no Telão</label>
                    <input type="text" id="pix_banner_title" name="pix_banner_title" class="form-control editable-input" maxlength="60"
                           value="<?= View::e($settings['pix_banner_title'] ?? 'PAGUE COM PIX DIRETO DO SEU LUGAR') ?>" 
                           placeholder="Ex: PAGUE COM PIX DIRETO DO SEU LUGAR"
                           oninput="updatePixPreview()">
                    <small style="color: var(--text-muted);">Texto de destaque em amarelo/azul no banner do Telão Oficial.</small>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <input type="checkbox" id="pix_show_on_telao" name="pix_show_on_telao" value="1" <?= ($settings['pix_show_on_telao'] ?? 'true') !== 'false' ? 'checked' : '' ?> style="width: 1.25rem; height: 1.25rem;">
                        <span style="font-weight: 700; color: #0f172a;">
                            Exibir banner oficial com QR Code e Chave PIX no Telão enquanto as vendas estiverem ABERTAS
                        </span>
                    </label>
                </div>
            </div>

            <!-- Preview em Tempo Real do Telão com Padrão BCB -->
            <div style="margin-top: 1.5rem; padding: 1.25rem; background: #0f172a; border-radius: 14px; border: 2px solid #0284c7; color: #ffffff;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.8rem; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 1px;">
                        🖥️ Pré-visualização do Telão Oficial & Padrão Banco Central (BCB):
                    </span>
                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                        <span id="pixValidationBadge" class="badge" style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e; font-size: 0.75rem; font-weight: 700;">
                            ✓ ESTRUTURA BR CODE VÁLIDA
                        </span>
                        <div id="pixValidationError" style="display: none; font-size: 0.75rem; color: #f87171; margin-top: 0.3rem; font-weight: 600; text-align: right;"></div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="background: #ffffff; padding: 6px; border-radius: 8px; display: inline-block;">
                        <img id="previewPixQr" src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode(\App\Services\PixService::createPayload($settings['pix_key'] ?? 'mskpoeira@gmail.com', $settings['pix_receiver_name'] ?? 'Show de Premios', $settings['pix_receiver_city'] ?? 'SAO PAULO', $settings['pix_description'] ?? 'Show de Premios', null, '***', $settings['pix_key_type'] ?? null)) ?>" alt="QR Code PIX BCB" style="width: 130px; height: 130px; display: block;">
                    </div>
                    <div style="flex-grow: 1; min-width: 260px;">
                        <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: #0284c7; color: #ffffff; font-weight: 900; font-size: 0.95rem; padding: 0.3rem 0.8rem; border-radius: 20px; margin-bottom: 0.4rem;">
                            ⚡ <span id="previewBannerTitle"><?= View::e(!empty($settings['pix_banner_title']) ? $settings['pix_banner_title'] : 'PAGUE COM PIX DIRETO DO SEU LUGAR') ?></span>
                        </div>
                        <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.15rem;">Chave PIX Oficial:</div>
                        <div id="previewPixKey" style="font-size: 1.35rem; font-weight: 900; color: #38bdf8; font-family: monospace;">
                            <?= View::e(!empty($settings['pix_key']) ? $settings['pix_key'] : 'mskpoeira@gmail.com') ?>
                        </div>
                        <div id="previewPixReceiver" style="font-size: 0.95rem; color: #cbd5e1; font-weight: 700; margin-top: 0.25rem;">
                            Recebedor: <?= View::e(!empty($settings['pix_receiver_name']) ? $settings['pix_receiver_name'] : 'Show de Prêmios Retiro') ?>
                        </div>
                        <div id="previewPixDesc" style="font-size: 0.9rem; color: #fef08a; font-weight: 600; margin-top: 0.2rem;">
                            Obs: <?= View::e(!empty($settings['pix_description']) ? $settings['pix_description'] : 'Show de Prêmios') ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.15); padding-top: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700;">Código PIX Copia e Cola Oficial (Padrão BCB / EMVCo):</span>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="copyPixPayload()" style="font-size: 0.75rem; padding: 0.2rem 0.6rem;">
                            📋 Copiar Código
                        </button>
                    </div>
                    <textarea id="previewPixPayload" class="form-control" readonly rows="2" style="font-family: monospace; font-size: 0.75rem; background: #090d16; color: #38bdf8; border-color: #334155; resize: none;"><?= \App\Services\PixService::createPayload($settings['pix_key'] ?? 'mskpoeira@gmail.com', $settings['pix_receiver_name'] ?? 'Show de Premios', $settings['pix_receiver_city'] ?? 'SAO PAULO', $settings['pix_description'] ?? 'Show de Premios', null, '***', $settings['pix_key_type'] ?? null) ?></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="background: #0284c7; border-color: #0284c7; padding: 0.75rem 2.5rem; font-weight: 800;">
                    💾 Salvar Configurações do PIX
                </button>
            </div>
        </form>
    </div>

    <!-- Limpeza Seletiva do Banco de Dados (Master Admin) -->
    <?php if (Auth::isMasterAdmin()): ?>
    <div class="card" style="border: 2px solid #ef4444; background: #fffcfc; border-radius: 12px; margin-top: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="card-title" style="color: #991b1b; margin: 0; font-size: 1.15rem; font-weight: 800;">
                ⚠️ Manutenção: Limpeza Seletiva do Banco de Dados
            </div>
            <span class="badge badge-closed" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 0.75rem;">
                Acesso Exclusivo Master Admin
            </span>
        </div>
        
        <p style="color: #4b5563; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem;">
            Selecione individualmente quais categorias de dados deseja excluir do banco de dados (para zerar testes antes do evento oficial ou limpar dados específicos). 
            <strong style="color: #991b1b;">Um backup preventivo automático de segurança é sempre gerado antes de qualquer exclusão.</strong>
        </p>

        <!-- Botões de Seleção Rápida -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem;">
            <button type="button" class="btn btn-sm" onclick="selectCleanPreset('test_data')" style="background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; font-weight: 700; font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                ⚡ Selecionar Apenas Movimentações de Teste (Recomendado)
            </button>
            <button type="button" class="btn btn-sm" onclick="selectCleanPreset('all')" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                ☑️ Marcar Todos
            </button>
            <button type="button" class="btn btn-sm" onclick="selectCleanPreset('none')" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                ⬜ Desmarcar Todos
            </button>
        </div>

        <form method="POST" action="<?= View::url('configuracoes/limpar-banco') ?>" id="formCleanDatabase" onsubmit="return handleCleanSubmit(event);">
            <?= Csrf::inputField() ?>

            <!-- Grade de Opções Clicáveis -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.85rem; margin-bottom: 1.5rem;">
                
                <!-- 1. Vendas -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="sales" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;" checked>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">🛒 Vendas de Cartelas</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['sales'] ?? 0) ?> reg.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Exclui todas as vendas avulsas e pacotes promocionais registrados.
                        </div>
                    </div>
                </label>

                <!-- 2. Rodadas -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="rounds" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;" checked>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">🎯 Rodadas e Sorteios</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['rounds'] ?? 0) ?> reg.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Exclui todas as rodadas cadastradas, cartelas sorteadas e ganhadores.
                        </div>
                    </div>
                </label>

                <!-- 3. Caixa -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="cash" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;" checked>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">💰 Caixa e Fechamentos</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['cash'] ?? 0) ?> reg.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Exclui sangrias, aportes, suprimentos e fechamentos de caixa.
                        </div>
                    </div>
                </label>

                <!-- 4. Dias de Operação -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="operation_days" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;" checked>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">📅 Dias de Operação</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['operation_days'] ?? 0) ?> reg.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Exclui abertura e fechamento de dias de evento anteriores.
                        </div>
                    </div>
                </label>

                <!-- 5. Histórico Antigo de Preços -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="pricing_history" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;">
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">🏷️ Histórico Antigo de Preços</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['pricing_history'] ?? 0) ?> antigo(s)</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Exclui regras antigas cadastradas, mantendo a regra atual como vigente.
                        </div>
                    </div>
                </label>

                <!-- 6. Vendedores -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="sellers" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;">
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">👤 Equipe de Vendedores(as)</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['sellers'] ?? 0) ?> cad.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Apaga os cadastros dos voluntários da equipe de vendas.
                        </div>
                    </div>
                </label>

                <!-- 7. Operadores -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="operators" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;">
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">👥 Operadores do Sistema</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['operators'] ?? 0) ?> usuár.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Apaga outros operadores. O seu login e tcardozo NUNCA são apagados.
                        </div>
                    </div>
                </label>

                <!-- 8. Logs de Auditoria -->
                <label class="clean-option-card" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; cursor: pointer; transition: all 0.2s ease;">
                    <input type="checkbox" name="clean_items[]" value="audit_logs" class="clean-checkbox" style="margin-top: 0.25rem; width: 1.15rem; height: 1.15rem; accent-color: #ef4444;">
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                            <strong style="color: #1e293b; font-size: 0.95rem;">📜 Logs de Auditoria</strong>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= (int)($tableCounts['audit_logs'] ?? 0) ?> reg.</span>
                        </div>
                        <div style="color: #64748b; font-size: 0.8rem; line-height: 1.3;">
                            Zera o registro de ações e operações anteriores do sistema.
                        </div>
                    </div>
                </label>

            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding-top: 0.75rem; border-top: 1px solid #fed7d7;">
                <div style="color: #991b1b; font-size: 0.82rem; display: flex; align-items: center; gap: 0.35rem;">
                    <span>🔒</span> <span>Backup preventivo automático de segurança será salvo antes da exclusão.</span>
                </div>
                <button type="submit" class="btn btn-danger" style="padding: 0.65rem 1.5rem; font-weight: 800; font-size: 0.95rem;">
                    🗑️ Excluir Dados Selecionados
                </button>
            </div>
        </form>
    </div>

    <script>
    function selectCleanPreset(preset) {
        const checkboxes = document.querySelectorAll('.clean-checkbox');
        checkboxes.forEach(cb => {
            if (preset === 'all') {
                cb.checked = true;
            } else if (preset === 'none') {
                cb.checked = false;
            } else if (preset === 'test_data') {
                cb.checked = ['sales', 'rounds', 'cash', 'operation_days'].includes(cb.value);
            }
            updateCleanCardStyle(cb);
        });
    }

    function updateCleanCardStyle(checkbox) {
        const card = checkbox.closest('.clean-option-card');
        if (!card) return;
        if (checkbox.checked) {
            card.style.borderColor = '#ef4444';
            card.style.background = '#fef2f2';
        } else {
            card.style.borderColor = '#e2e8f0';
            card.style.background = '#ffffff';
        }
    }

    document.querySelectorAll('.clean-checkbox').forEach(cb => {
        cb.addEventListener('change', () => updateCleanCardStyle(cb));
        updateCleanCardStyle(cb);
    });

    function handleCleanSubmit(e) {
        const checked = Array.from(document.querySelectorAll('.clean-checkbox:checked'));
        if (checked.length === 0) {
            alert('Por favor, selecione ao menos um item para excluir.');
            e.preventDefault();
            return false;
        }

        const labels = checked.map(cb => {
            const titleEl = cb.closest('.clean-option-card').querySelector('strong');
            return '• ' + (titleEl ? titleEl.innerText.trim() : cb.value);
        }).join('\n');

        const msg = 'ATENÇÃO - OPERAÇÃO DEFINITIVA:\n\nVocê selecionou as seguintes categorias para exclusão permanente do banco de dados:\n\n' +
            labels + '\n\n' +
            'Um backup de segurança automático será gerado antes da exclusão.\n\n' +
            'Deseja realmente prosseguir com a exclusão?';

        if (!confirm(msg)) {
            e.preventDefault();
            return false;
        }
        return true;
    }
    </script>
    <?php endif; ?>

<!-- ========================================================================= -->
<!-- SUB-ABA 2: VENDEDORES(AS) -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'vendedores'): ?>

    <div class="card">
        <div class="card-title">
            <span>Equipe de Vendedores(as) (<?= count($sellers) ?>)</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modalAddSeller').showModal()">
                + Adicionar Vendedor(a)
            </button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Apelido</th>
                        <th>Status</th>
                        <th>Início</th>
                        <th class="text-right">Cartelas Vendidas</th>
                        <th class="text-right">Total Arrecadado</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sellers)): ?>
                        <tr><td colspan="7" class="text-center" style="color: var(--text-muted); padding: 2rem;">Nenhum vendedor cadastrado ainda. Clique no botão acima para adicionar.</td></tr>
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
                            <td class="text-right font-mono"><?= number_format($s['total_qty'], 0, ',', '.') ?></td>
                            <td class="text-right font-mono font-bold"><?= View::money($s['total_sales']) ?></td>
                            <td class="text-center" style="display: flex; gap: 0.35rem; justify-content: center; align-items: center;">
                                <button type="button" class="btn btn-sm btn-secondary" onclick='openEditSeller(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)' title="Editar dados">
                                    ✏️ Editar
                                </button>
                                <a href="<?= View::url('relatorios/vendedor?seller_id=' . $s['id']) ?>" target="_blank" class="btn btn-sm btn-primary" title="Ver e imprimir extrato de vendas individual">
                                    📑 Extrato
                                </a>
                                <form method="POST" action="<?= View::url('vendedores/toggle') ?>" style="display: inline-block;">
                                    <?= Csrf::inputField() ?>
                                    <input type="hidden" name="seller_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $s['active'] ? 'btn-secondary' : 'btn-success' ?>" onclick="return confirm('<?= $s['active'] ? 'Deseja inativar este vendedor?' : 'Deseja reativar este vendedor?' ?>');">
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

    <!-- Modal Adicionar Vendedor -->
    <dialog id="modalAddSeller">
        <form method="POST" action="<?= View::url('vendedores/criar') ?>">
            <?= Csrf::inputField() ?>
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.2rem; color: #0f172a;">
                + Adicionar Novo(a) Vendedor(a)
            </h3>

            <div class="form-group">
                <label class="form-label editable-label" for="seller_name">Nome Completo</label>
                <input type="text" id="seller_name" name="name" class="form-control editable-input" required placeholder="Ex: Maria das Graças">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="seller_nickname">Apelido / Crachá (Opcional)</label>
                <input type="text" id="seller_nickname" name="nickname" class="form-control editable-input" placeholder="Ex: Graça">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="seller_notes">Observações / Telefone (Opcional)</label>
                <textarea id="seller_notes" name="notes" class="form-control editable-input" rows="2" placeholder="Ex: Celular (11) 99999-9999"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalAddSeller').close()">Cancelar</button>
                <button type="submit" class="btn btn-success">Cadastrar Vendedor(a)</button>
            </div>
        </form>
    </dialog>

    <!-- Modal Editar Vendedor -->
    <dialog id="modalEditSeller">
        <form method="POST" action="<?= View::url('vendedores/editar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" id="edit_seller_id" name="seller_id">

            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.2rem; color: #0f172a;">
                ✏️ Editar Vendedor(a)
            </h3>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_seller_name">Nome Completo</label>
                <input type="text" id="edit_seller_name" name="name" class="form-control editable-input" required>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_seller_nickname">Apelido / Crachá</label>
                <input type="text" id="edit_seller_nickname" name="nickname" class="form-control editable-input">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_seller_notes">Observações / Telefone</label>
                <textarea id="edit_seller_notes" name="notes" class="form-control editable-input" rows="2"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditSeller').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </dialog>

<!-- ========================================================================= -->
<!-- SUB-ABA 3: OPERADORES DO SISTEMA -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'operadores'): ?>

    <div class="card">
        <div class="card-title">
            <span>Gestão de Operadores e Equipe (<?= count($operators) ?>)</span>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('modalNewUser').showModal()">
                + Novo Operador
            </button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário (Login)</th>
                        <th>Cargo / Perfil</th>
                        <th>Status</th>
                        <th>Cadastrado em</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($operators)): ?>
                        <tr>
                            <td colspan="6" class="text-center" style="color: var(--text-muted); padding: 2rem;">
                                Nenhum operador cadastrado.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($operators as $u): ?>
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
                                <span class="badge <?= $u['active'] ? 'badge-ok' : 'badge-closed' ?>">
                                    <?= $u['active'] ? '🟢 ATIVO' : '⚪ INATIVO' ?>
                                </span>
                            </td>
                            <td><?= View::date($u['created_at']) ?></td>
                            <td class="text-center" style="white-space: nowrap;">
                                <button class="btn btn-sm btn-secondary" onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar dados e senha">
                                    ✏️ Editar
                                </button>

                                <?php if ($u['id'] !== Auth::id()): ?>
                                    <form method="POST" action="<?= View::url('operadores/status') ?>" style="display: inline-block; margin-left: 0.25rem;">
                                        <?= Csrf::inputField() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['active'] ? 'btn-danger' : 'btn-success' ?>" 
                                                onclick="return confirm('Deseja realmente <?= $u['active'] ? 'desativar' : 'ativar' ?> o acesso de <?= View::e($u['name']) ?>?');">
                                            <?= $u['active'] ? 'Desativar' : 'Ativar' ?>
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
    <dialog id="modalNewUser">
        <form method="POST" action="<?= View::url('operadores/novo') ?>">
            <?= Csrf::inputField() ?>
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.2rem; color: #0f172a;">
                + Cadastrar Novo Operador
            </h3>

            <div class="form-group">
                <label class="form-label editable-label" for="user_name">Nome Completo</label>
                <input type="text" id="user_name" name="name" class="form-control editable-input" required placeholder="Ex: João da Silva">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="user_login">Login / Usuário (sem espaços)</label>
                <input type="text" id="user_login" name="login" class="form-control editable-input" required placeholder="Ex: joao.silva">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="user_password">Senha Inicial (mínimo 6 caracteres)</label>
                <input type="password" id="user_password" name="password" class="form-control editable-input" required placeholder="******">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="user_role">Cargo / Perfil de Acesso</label>
                <select id="user_role" name="role" class="form-control editable-input" required>
                    <option value="CAIXA">Operador(a) de Caixa</option>
                    <option value="OPERATOR">Operador Geral</option>
                    <option value="GERENTE_FINANCEIRO">Gerente Financeiro</option>
                    <option value="GERENTE_EVENTO">Gerente Geral do Evento</option>
                    <?php if (Auth::isAdmin()): ?>
                        <option value="ADMIN">Administrador</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalNewUser').close()">Cancelar</button>
                <button type="submit" class="btn btn-success">Cadastrar Operador</button>
            </div>
        </form>
    </dialog>

    <!-- Modal Editar Operador -->
    <dialog id="modalEditUser">
        <form method="POST" action="<?= View::url('operadores/editar') ?>">
            <?= Csrf::inputField() ?>
            <input type="hidden" id="edit_user_id" name="user_id">

            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.2rem; color: #0f172a;">
                ✏️ Editar Operador
            </h3>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_user_name">Nome Completo</label>
                <input type="text" id="edit_user_name" name="name" class="form-control editable-input" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_user_login">Login (Não alterável)</label>
                <input type="text" id="edit_user_login" name="login" class="form-control auto-input" readonly>
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_user_password">Nova Senha (deixe em branco para manter a atual)</label>
                <input type="password" id="edit_user_password" name="password" class="form-control editable-input" placeholder="Deixe em branco para não alterar">
            </div>

            <div class="form-group">
                <label class="form-label editable-label" for="edit_user_role">Cargo / Perfil</label>
                <select id="edit_user_role" name="role" class="form-control editable-input" required>
                    <option value="CAIXA">Operador(a) de Caixa</option>
                    <option value="OPERATOR">Operador Geral</option>
                    <option value="GERENTE_FINANCEIRO">Gerente Financeiro</option>
                    <option value="GERENTE_EVENTO">Gerente Geral do Evento</option>
                    <?php if (Auth::isAdmin()): ?>
                        <option value="ADMIN">Administrador</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditUser').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </dialog>

<!-- ========================================================================= -->
<!-- SUB-ABA 4: BACKUP & DADOS -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'backup'): ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Export Card -->
        <div class="card" style="border-top: 4px solid var(--primary);">
            <div class="card-title">📤 Exportar Backup Completo</div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                Baixe um arquivo JSON estruturado contendo todos os dados do Show de Prêmios (vendas, rodadas, vendedores, caixa e auditoria).
            </p>

            <a href="<?= View::url('backup/exportar') ?>" class="btn btn-primary" style="width: 100%;">
                Baixar Backup JSON Agora
            </a>
        </div>

        <!-- Restore Card -->
        <div class="card" style="border-top: 4px solid var(--danger);">
            <div class="card-title">📥 Restaurar Dados de Backup</div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                Antes de qualquer restauração, o sistema <strong>gerará automaticamente um snapshot prévio</strong> do estado atual para prevenção de perdas.
            </p>

            <form method="POST" action="<?= View::url('backup/restaurar') ?>" enctype="multipart/form-data">
                <?= Csrf::inputField() ?>

                <div class="form-group">
                    <label class="form-label" for="backup_file">Arquivo JSON de Backup</label>
                    <input type="file" id="backup_file" name="backup_file" class="form-control" accept=".json" required>
                </div>

                <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('ATENÇÃO: A restauração substituirá os dados atuais pelo arquivo selecionado. Um backup automático será gerado antes. Deseja prosseguir?');">
                    Executar Restauração
                </button>
            </form>
        </div>
    </div>

    <!-- Backups Directory Listing -->
    <div class="card">
        <div class="card-title">Histórico de Backups Armazenados no Servidor</div>
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
                    <?php if (empty($backupFiles)): ?>
                        <tr><td colspan="3" class="text-center" style="color: var(--text-muted); padding: 1.5rem;">Nenhum backup em arquivo local encontrado no servidor.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $f): ?>
                        <tr>
                            <td><strong><?= View::e($f['name']) ?></strong></td>
                            <td class="font-mono"><?= round($f['size'] / 1024, 1) ?> KB</td>
                            <td><span style="font-family: monospace; font-weight: 700;"><?= date('d/m/Y H:i:s', $f['date']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<script>
function openEditSeller(seller) {
    document.getElementById('edit_seller_id').value = seller.id;
    document.getElementById('edit_seller_name').value = seller.name;
    document.getElementById('edit_seller_nickname').value = seller.nickname || '';
    document.getElementById('edit_seller_notes').value = seller.notes || '';
    document.getElementById('modalEditSeller').showModal();
}

function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_user_name').value = user.name;
    document.getElementById('edit_user_login').value = user.login;
    document.getElementById('edit_user_password').value = '';
    document.getElementById('edit_user_role').value = user.role;
    document.getElementById('modalEditUser').showModal();
}

function formatEmv(id, value) {
    const len = ('00' + value.length).slice(-2);
    return id + len + value;
}

function removeAccentsJs(str) {
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9 \.\-_@]/g, '');
}

function crc16Ccitt(str) {
    let crc = 0xFFFF;
    const polynomial = 0x1021;
    for (let i = 0; i < str.length; i++) {
        crc ^= (str.charCodeAt(i) << 8);
        for (let j = 0; j < 8; j++) {
            if ((crc & 0x8000) !== 0) {
                crc = ((crc << 1) ^ polynomial) & 0xFFFF;
            } else {
                crc = (crc << 1) & 0xFFFF;
            }
        }
    }
    let hex = (crc & 0xFFFF).toString(16).toUpperCase();
    return ('0000' + hex).slice(-4);
}

function normalizePixKeyJs(key, type) {
    let cleanKey = (key || '').trim();
    const t = (type || '').toUpperCase();

    // 1. E-mail (se contém @ ou tipo selecionado é EMAIL)
    if (cleanKey.includes('@') || t === 'EMAIL') {
        return cleanKey.trim();
    }

    // 2. Chave Aleatória (EVP / UUID v4)
    if (t === 'CHAVE_ALEATORIA' || t === 'ALEATORIA' || /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(cleanKey)) {
        return cleanKey.trim();
    }

    // 3. CNPJ (14 caracteres alfanuméricos conforme padrão Bacen/Receita)
    // - remover ".", "/", "-", espaços e outros separadores;
    // - preservar letras caso seja CNPJ alfanumérico;
    // - converter letras para maiúsculas;
    // - resultado deve possuir 14 caracteres alfanuméricos;
    // - nunca enviar a versão mascarada ao payload.
    const alnumClean = cleanKey.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    if (t === 'CNPJ' || (alnumClean.length === 14 && t !== 'CPF' && t !== 'TELEFONE')) {
        return alnumClean;
    }

    // 4. CPF (11 dígitos numéricos)
    const digits = cleanKey.replace(/\D/g, '');
    if (t === 'CPF' || (digits.length === 11 && t !== 'TELEFONE' && t !== 'CELULAR')) {
        return digits;
    }

    // 5. Celular / Telefone (formato internacional DICT +55...)
    if (t === 'TELEFONE' || t === 'PHONE' || t === 'CELULAR' || (digits.length >= 10 && digits.length <= 13)) {
        if (cleanKey.startsWith('+')) {
            return '+' + digits;
        }
        if (digits.startsWith('55') && digits.length >= 12) {
            return '+' + digits;
        }
        if (digits.length >= 10 && digits.length <= 11) {
            return '+55' + digits;
        }
        return '+' + digits;
    }

    return alnumClean || cleanKey;
}

function validatePixSemanticJs(key, type, receiver, city, desc) {
    const errors = [];
    const t = (type || '').toUpperCase();
    const normKey = normalizePixKeyJs(key, t);

    if (!normKey) {
        errors.push('Informe a Chave PIX.');
    } else {
        if (t === 'CNPJ') {
            if (normKey.length !== 14 || !/^[0-9A-Z]{14}$/.test(normKey)) {
                errors.push('CNPJ deve conter exatamente 14 caracteres alfanuméricos normalizados.');
            }
        } else if (t === 'CPF') {
            if (normKey.length !== 11 || !/^\d{11}$/.test(normKey)) {
                errors.push('CPF deve conter exatamente 11 dígitos.');
            }
        } else if (t === 'TELEFONE' || t === 'PHONE' || t === 'CELULAR') {
            if (!/^\+[1-9]\d{10,14}$/.test(normKey)) {
                errors.push('Telefone deve estar no formato internacional (+55 DDD Número).');
            }
        } else if (t === 'EMAIL') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normKey)) {
                errors.push('Formato de e-mail inválido.');
            }
        } else if (t === 'CHAVE_ALEATORIA' || t === 'ALEATORIA') {
            if (!/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(normKey)) {
                errors.push('Chave aleatória (EVP) deve estar no formato UUID.');
            }
        }
    }

    const cleanName = removeAccentsJs(receiver || '').trim();
    if (!cleanName) {
        errors.push('Nome do recebedor é obrigatório.');
    }

    const cleanCity = removeAccentsJs(city || '').trim();
    if (!cleanCity) {
        errors.push('Cidade é obrigatória.');
    }

    if (desc && removeAccentsJs(desc).trim().length > 50) {
        errors.push('Observação no PIX não pode exceder 50 caracteres.');
    }

    return {
        isValid: errors.length === 0,
        errors: errors,
        normalizedKey: normKey
    };
}

function buildBcbPixPayload(key, type, receiver, city, desc) {
    const normKey = normalizePixKeyJs(key, type);
    if (!normKey) return '';

    // Merchant Account Information (Tag 26)
    // 00: GUI br.gov.bcb.pix
    // 01: Chave DICT normalizada (sem pontuação/máscaras de CPF/CNPJ)
    // 02: infoAdicional / Descrição (opcional, máx. 50 caracteres)
    let accountInfo = formatEmv('00', 'br.gov.bcb.pix') + formatEmv('01', normKey);
    const cleanDesc = removeAccentsJs(desc || '').trim().substring(0, 50);
    if (cleanDesc) {
        accountInfo += formatEmv('02', cleanDesc);
    }

    const cleanName = removeAccentsJs(receiver || 'Show de Premios').toUpperCase().substring(0, 25).trim() || 'SHOW DE PREMIOS';
    // Tag 60: Cidade sem UF
    const cleanCity = removeAccentsJs(city || 'SAO PAULO').toUpperCase().substring(0, 15).trim() || 'SAO PAULO';

    let payload = '';
    payload += formatEmv('00', '01');
    payload += formatEmv('26', accountInfo);
    payload += formatEmv('52', '0000');
    payload += formatEmv('53', '986');
    payload += formatEmv('58', 'BR');
    payload += formatEmv('59', cleanName);
    payload += formatEmv('60', cleanCity);
    payload += formatEmv('62', formatEmv('05', '***'));

    const toCrc = payload + '6304';
    const crc = crc16Ccitt(toCrc);
    return toCrc + crc;
}

function updatePixPreview() {
    const keyEl = document.getElementById('pix_key');
    const typeEl = document.getElementById('pix_key_type');
    const recEl = document.getElementById('pix_receiver_name');
    const cityEl = document.getElementById('pix_receiver_city');
    const descEl = document.getElementById('pix_description');
    const titleEl = document.getElementById('pix_banner_title');

    const prevKey = document.getElementById('previewPixKey');
    const prevRec = document.getElementById('previewPixReceiver');
    const prevDesc = document.getElementById('previewPixDesc');
    const prevTitle = document.getElementById('previewBannerTitle');
    const prevQr = document.getElementById('previewPixQr');
    const prevPayload = document.getElementById('previewPixPayload');
    const badgeEl = document.getElementById('pixValidationBadge');
    const errorEl = document.getElementById('pixValidationError');

    if (!keyEl || !prevKey) return;

    const rawKey = keyEl.value.trim() || 'mskpoeira@gmail.com';
    const type = typeEl ? typeEl.value : 'CHAVE_ALEATORIA';
    const rec = (recEl ? recEl.value.trim() : '') || 'Show de Prêmios Retiro';
    const city = (cityEl ? cityEl.value.trim() : '') || 'São Paulo';
    const desc = (descEl ? descEl.value.trim() : '') || 'Show de Prêmios';
    const title = (titleEl ? titleEl.value.trim() : '') || 'PAGUE COM PIX DIRETO DO SEU LUGAR';

    // A máscara digitada continua sendo exibida visualmente ao usuário
    prevKey.textContent = rawKey;
    if (prevRec) prevRec.textContent = 'Recebedor: ' + rec;
    if (prevDesc) prevDesc.textContent = 'Obs: ' + desc;
    if (prevTitle) prevTitle.textContent = title;

    // Validação Semântica Específica Pix antes da geração
    const validation = validatePixSemanticJs(rawKey, type, rec, city, desc);
    if (badgeEl) {
        if (validation.isValid) {
            badgeEl.textContent = '✓ ESTRUTURA BR CODE VÁLIDA';
            badgeEl.style.background = 'rgba(34, 197, 94, 0.2)';
            badgeEl.style.color = '#4ade80';
            badgeEl.style.borderColor = '#22c55e';
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = '';
            }
        } else {
            badgeEl.textContent = '⚠️ DADOS PIX INCOMPLETOS OU INVÁLIDOS';
            badgeEl.style.background = 'rgba(239, 68, 68, 0.2)';
            badgeEl.style.color = '#f87171';
            badgeEl.style.borderColor = '#ef4444';
            if (errorEl) {
                errorEl.style.display = 'block';
                errorEl.textContent = validation.errors.join(' • ');
            }
        }
    }

    const bcbPayload = buildBcbPixPayload(rawKey, type, rec, city, desc);
    if (prevPayload) {
        prevPayload.value = bcbPayload;
    }
    if (prevQr && bcbPayload) {
        prevQr.src = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' + encodeURIComponent(bcbPayload);
    }
}

function copyPixPayload() {
    const payloadEl = document.getElementById('previewPixPayload');
    if (!payloadEl || !payloadEl.value) return;
    navigator.clipboard.writeText(payloadEl.value).then(() => {
        alert('Código PIX Copia e Cola copiado para a área de transferência!');
    }).catch(() => {
        payloadEl.select();
        document.execCommand('copy');
        alert('Código PIX Copia e Cola copiado!');
    });
}
</script>
