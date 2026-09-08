<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$status = $round['status'];
$calledSet = array_flip($calledNumbers);
$prizesCount = (int)($round['prizes_count'] ?? 2);
?>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>

<div class="page-header" style="margin-bottom: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h1 class="page-title" style="font-size: 1.6rem; margin-bottom: 0;">
                🎤 Locutor & Sorteio &bull; Rodada <?= (int)$round['round_number'] ?>
            </h1>
            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 800; font-size: 0.95rem;">
                <?= View::e($round['round_name'] ?? ('Rodada ' . $round['round_number'])) ?>
            </span>
            <?php if (!empty($round['card_color'])): ?>
                <span class="badge" style="background: #fef08a; color: #854d0e; font-weight: 800; font-size: 0.95rem; border: 1px solid #ca8a04;">
                    Cartela <?= View::e($round['card_color']) ?>
                </span>
            <?php endif; ?>
        </div>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">
            Controle de pedras, cantoria oficial do bingo e auditoria de ganhadores em tempo real.
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn btn-sm" onclick="openShareMobileModal()" style="background: #10b981; color: #fff; font-weight: 800;" title="Abrir ou compartilhar link do locutor para celular">
            📱 Link no Celular
        </button>
        <a href="<?= View::url('rodada?id=' . $round['id']) ?>" class="btn btn-secondary btn-sm">
            ⚙️ Vendas da Rodada
        </a>
        <a href="<?= View::url('telao') ?>" target="_blank" class="btn btn-warning btn-sm" style="font-weight: 800;">
            📺 Abrir Telão ao Vivo ↗
        </a>
    </div>
</div>

<!-- 1. Barra Rápida de Controle dos 5 Estados da Rodada -->
<div class="card" style="margin-bottom: 1.25rem; padding: 0.85rem 1.25rem; background: #0f172a; color: #fff; border: 1px solid #334155;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8;">
                Estado da Rodada:
            </span>
            <span id="currentStatusBadge" class="badge" style="font-size: 1rem; padding: 0.4rem 0.85rem; font-weight: 800;">
                <?= match($status) {
                    'OPEN' => '🟢 ABERTA (Vendas)',
                    'IN_PROGRESS' => '⚡ EM ANDAMENTO (Cantoria)',
                    'PAUSED' => '⏸️ PAUSADA (Intervalo)',
                    'CHECKING' => '🔔 EM CONFERÊNCIA (Bingo!)',
                    'CLOSED' => '🏁 FECHADA (Concluída)',
                    default => $status
                } ?>
            </span>
        </div>

        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm" onclick="setStatus('OPEN')" style="background: #16a34a; color: #fff; font-weight: 700;">
                🟢 Abrir Vendas
            </button>
            <button type="button" class="btn btn-sm" onclick="setStatus('IN_PROGRESS')" style="background: #2563eb; color: #fff; font-weight: 700;">
                ⚡ Em Andamento
            </button>
            <button type="button" class="btn btn-sm" onclick="setStatus('PAUSED')" style="background: #eab308; color: #000; font-weight: 700;">
                ⏸️ Pausar
            </button>
            <button type="button" class="btn btn-sm" onclick="openBingoCheckingModal()" style="background: #ef4444; color: #fff; font-weight: 800; animation: pulse-btn 1.5s infinite;">
                🔔 BINGO! / CONFERIR
            </button>
            <button type="button" class="btn btn-sm" onclick="setStatus('CLOSED')" style="background: #475569; color: #fff; font-weight: 700;">
                🏁 Fechar Rodada
            </button>
        </div>
    </div>
</div>

<!-- 2. Barra da Voz Animada do Locutor (Síntese e Anúncios) -->
<div class="card" style="margin-bottom: 1.25rem; padding: 1rem 1.25rem; background: linear-gradient(135deg, #1e1b4b, #312e81); border: 2px solid #6366f1; color: #fff; box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span style="font-size: 1.4rem;">🎙️</span>
                <span style="font-weight: 900; font-size: 1.1rem; color: #fef08a; letter-spacing: 0.5px;">LOCUÇÃO ANIMADA DO SISTEMA</span>
                <span class="badge" style="background: rgba(34, 197, 94, 0.25); color: #86efac; border: 1px solid #22c55e; font-weight: 800; font-size: 0.8rem;">Voz pt-BR</span>
            </div>
            <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: #cbd5e1;">
                O sistema narra em voz alta e animada: números confirmados, rodada, cor da cartela, prêmios e preços de venda!
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <!-- Botão Anunciar Rodada e Regras Completa -->
            <button type="button" class="btn" onclick="speakRoundAnnouncementAnimated()" style="background: #f59e0b; color: #000; font-weight: 900; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); display: flex; align-items: center; gap: 0.4rem;" title="Traduzir e falar em voz animada a rodada, cor da cartela, prêmios e preços de venda">
                <span>📢</span> Anunciar Rodada & Prêmios
            </button>

            <!-- Botão Repetir Última Pedra -->
            <button type="button" class="btn" onclick="repeatLastNumberSpeech()" style="background: #3b82f6; color: #fff; font-weight: 800; display: flex; align-items: center; gap: 0.4rem;">
                <span>🔊</span> Repetir Pedra
            </button>

            <!-- Botão Parar Voz -->
            <button type="button" class="btn" onclick="stopSpeech()" style="background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.25); font-weight: 700;">
                ⏹️ Parar
            </button>

            <!-- Toggle Voz Automática -->
            <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; background: rgba(0,0,0,0.3); padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #4338ca; user-select: none;">
                <input type="checkbox" id="toggleAutoVoice" checked onchange="toggleVoiceEnabled(this.checked)" style="width: 18px; height: 18px; accent-color: #22c55e;">
                <span style="font-size: 0.85rem; font-weight: 800; color: #e0e7ff;">Falar pedras</span>
            </label>
        </div>
    </div>
</div>

<!-- Barra de Abas Exclusiva para Celular -->
<div class="mobile-tabs-bar" style="display: none; margin-bottom: 1rem; gap: 0.5rem;">
    <button type="button" id="tabBtnKeypad" class="btn btn-primary" style="flex: 1; font-weight: 800; padding: 0.65rem;" onclick="switchMobileTab('keypad')">
        ⌨️ Teclado Numérico
    </button>
    <button type="button" id="tabBtnBoard" class="btn btn-secondary" style="flex: 1; font-weight: 800; padding: 0.65rem;" onclick="switchMobileTab('board')">
        🎱 Tabuleiro (75 Bolas)
    </button>
</div>

<!-- Layout Principal do Locutor: Teclado à Esquerda + Tabuleiro 75 Pedras à Direita -->
<div class="speaker-grid" style="display: grid; grid-template-columns: minmax(320px, 400px) 1fr; gap: 1.25rem; align-items: start;">
    
    <!-- Painel do Teclado Numérico & Última Pedra -->
    <div id="keypadPanel" style="display: flex; flex-direction: column; gap: 1rem;">
        
        <!-- Cartão da Última Pedra Cantada -->
        <div class="card" style="text-align: center; padding: 1.5rem 1.25rem; border: 2px solid #3b82f6; background: linear-gradient(180deg, #ffffff 0%, #eff6ff 100%); margin-bottom: 0; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.15);">
            <div style="font-size: 0.85rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 1.5px;">
                Última Pedra Cantada
            </div>
            
            <div style="display: flex; justify-content: center; align-items: center; margin: 1rem 0;">
                <div id="lastBallDisplay" style="width: 125px; height: 125px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #60a5fa, #2563eb 55%, #1e40af 100%); color: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 14px 28px rgba(37, 99, 235, 0.45), inset 0 3px 6px rgba(255, 255, 255, 0.6); border: 4px solid #ffffff; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
                    <span id="lastBallLetter" style="font-size: 1.6rem; font-weight: 900; line-height: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                        <?= !empty($round['last_called_number']) ? \App\Controllers\SpeakerController::getLetterForNumber((int)$round['last_called_number']) : '—' ?>
                    </span>
                    <span id="lastBallNumber" style="font-size: 3.4rem; font-weight: 900; line-height: 1; text-shadow: 0 2px 5px rgba(0,0,0,0.35);">
                        <?= !empty($round['last_called_number']) ? (int)$round['last_called_number'] : '—' ?>
                    </span>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.5rem 0; border-top: 1px solid #dbeafe;">
                <span style="font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                    Chamadas: <span id="calledCount" style="color: #2563eb; font-size: 1.15rem;"><?= count($calledNumbers) ?></span> / 75
                </span>
                <button type="button" class="btn btn-sm btn-secondary" onclick="undoLastNumber()" title="Desfazer última pedra em caso de erro">
                    ↩️ Desfazer
                </button>
            </div>
        </div>

        <!-- Teclado Numérico Exclusivo do Locutor -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 0;">
            <div class="card-title" style="margin-bottom: 0.75rem; font-size: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span>⌨️ Digitar Pedra (1 a 75)</span>
                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Aceita teclado físico (Enter)</span>
            </div>

            <!-- Display do Teclado -->
            <div style="position: relative; margin-bottom: 0.75rem;">
                <input type="text" id="keypadInput" maxlength="2" readonly
                       placeholder="Digite o nº"
                       style="width: 100%; height: 60px; font-size: 2.2rem; font-weight: 900; text-align: center; border: 2px solid #cbd5e1; border-radius: 8px; background: #f8fafc; color: #0f172a; letter-spacing: 2px;">
                <span id="predictedLetter" style="position: absolute; right: 16px; top: 14px; font-size: 1.8rem; font-weight: 900; color: #3b82f6;"></span>
            </div>

            <!-- Grade de Teclas Virtuais Grandes -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.75rem;">
                <?php for ($i = 1; $i <= 9; $i++): ?>
                    <button type="button" class="keypad-btn" onclick="pressKey(<?= $i ?>)"><?= $i ?></button>
                <?php endfor; ?>
                <button type="button" class="keypad-btn keypad-clear" onclick="clearKeypad()" style="background: #f1f5f9; color: #ef4444; font-size: 1.1rem;">
                    LIMPAR
                </button>
                <button type="button" class="keypad-btn" onclick="pressKey(0)">0</button>
                <button type="button" class="keypad-btn keypad-back" onclick="backspaceKeypad()" style="background: #f1f5f9; color: #475569; font-size: 1.2rem;">
                    ⌫
                </button>
            </div>

            <!-- Botão Grande Confirmar / Cantar Pedra -->
            <button type="button" id="btnCallNumber" class="btn btn-primary" onclick="submitNumber()" 
                    style="width: 100%; padding: 0.85rem; font-size: 1.15rem; font-weight: 900; display: flex; justify-content: center; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                📢 CANTAR PEDRA (ENTER)
            </button>
        </div>

        <!-- Botão Gigante de Conferência -->
        <button type="button" class="btn btn-danger" onclick="openBingoCheckingModal()" 
                style="width: 100%; padding: 1rem; font-size: 1.3rem; font-weight: 900; letter-spacing: 1px; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); border: 2px solid #b91c1c;">
            🔔 BINGO! CONFERIR CARTELA
        </button>

    </div>

    <!-- Painel da Grade Geral de Bolas (1 a 75: B-I-N-G-O) -->
    <div id="boardPanel" class="card" style="padding: 1.25rem; margin-bottom: 0;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
            <span>🎱 Tabuleiro das 75 Pedras (B-I-N-G-O)</span>
            <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-muted);">Clique em qualquer número para cantar diretamente</span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.6rem;">
            <?php
            $letters = [
                'B' => ['range' => [1, 15], 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca'],
                'I' => ['range' => [16, 30], 'color' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#bfdbfe'],
                'N' => ['range' => [31, 45], 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
                'G' => ['range' => [46, 60], 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'],
                'O' => ['range' => [61, 75], 'color' => '#7c3aed', 'bg' => '#faf5ff', 'border' => '#e9d5ff'],
            ];

            foreach ($letters as $l => $conf):
            ?>
                <div style="display: flex; align-items: center; gap: 0.5rem; background: <?= $conf['bg'] ?>; border: 1px solid <?= $conf['border'] ?>; border-radius: 8px; padding: 0.4rem 0.6rem;">
                    <!-- Letra Header -->
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: <?= $conf['color'] ?>; color: #fff; font-weight: 900; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.15);">
                        <?= $l ?>
                    </div>

                    <!-- Bolinhas da Linha -->
                    <div style="display: grid; grid-template-columns: repeat(15, 1fr); gap: 0.35rem; flex: 1;">
                        <?php for ($n = $conf['range'][0]; $n <= $conf['range'][1]; $n++): 
                            $isCalled = isset($calledSet[$n]);
                        ?>
                            <button type="button" 
                                    id="board_ball_<?= $n ?>"
                                    class="board-ball-btn <?= $isCalled ? 'called' : '' ?>" 
                                    onclick="directCall(<?= $n ?>)"
                                    data-num="<?= $n ?>"
                                    style="width: 100%; aspect-ratio: 1/1; border-radius: 50%; border: 1px solid <?= $isCalled ? $conf['color'] : '#cbd5e1' ?>; background: <?= $isCalled ? $conf['color'] : '#ffffff' ?>; color: <?= $isCalled ? '#ffffff' : '#334155' ?>; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: all 0.2s;">
                                <?= $n ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Modal de Compartilhar Link do Locutor para Celular -->
<dialog id="modalMobileLink" style="padding: 1.5rem; border: 1px solid var(--border); border-radius: 12px; max-width: 480px; width: 95%; margin: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 0.4rem;">
            <span>📱</span> Módulo do Locutor no Celular
        </h3>
        <button type="button" style="background: none; border: none; font-size: 1.4rem; cursor: pointer;" onclick="document.getElementById('modalMobileLink').close()">&times;</button>
    </div>
    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
        Abra este link no celular para cantar as pedras direto do palco com teclado touch em tela cheia e voz animada:
    </p>
    <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 0.85rem; word-break: break-all; font-weight: 800; font-size: 1.05rem; color: #2563eb; text-align: center; margin-bottom: 1rem;" id="mobileLinkDisplay">
        https://showdepremios.mskpoeira.com.br/locutor
    </div>
    <div style="display: flex; gap: 0.5rem; flex-direction: column;">
        <button type="button" class="btn btn-primary" onclick="copyMobileLink()" style="font-weight: 800; padding: 0.65rem;">
            📋 Copiar Link do Locutor
        </button>
        <button type="button" class="btn btn-success" onclick="shareMobileWhatsApp()" style="background: #22c55e; color: #fff; font-weight: 800; padding: 0.65rem;">
            📲 Enviar Link via WhatsApp
        </button>
    </div>
</dialog>

<!-- Modal de Conferência de BINGO -->
<div id="bingoCheckingModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 99999; justify-content: center; align-items: center; padding: 1rem;">
    <div style="background: #ffffff; width: 100%; max-width: 550px; border-radius: 12px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); border-top: 6px solid #ef4444;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.5rem; font-weight: 900; color: #b91c1c; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                🔔 Conferência de BINGO!
            </h2>
            <button type="button" onclick="closeBingoCheckingModal()" style="border: none; background: transparent; font-size: 1.5rem; cursor: pointer; color: #64748b;">✕</button>
        </div>

        <p style="color: #475569; font-size: 0.95rem; margin-bottom: 1rem;">
            O Telão está exibindo o aviso de <strong>"Cartela em Conferência"</strong>. Verifique a cartela apresentada pelo jogador.
        </p>

        <!-- Validador Rápido de Números da Cartela -->
        <div style="background: #f8fafc; border: 2px dashed #94a3b8; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.4rem;">
                <span style="font-weight: 800; font-size: 0.95rem; color: #1e293b;">⚡ Conferência Expressa das Pedras</span>
                <span style="font-size: 0.8rem; color: #64748b;">(Digite ou cole os nºs da cartela)</span>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="verifyCardNumbersInput" class="form-control" placeholder="Ex: 5 18 32 47 68" style="font-weight: 800; font-size: 1.1rem; letter-spacing: 1px;" onkeyup="handleQuickVerifyCardKey(event)">
                <button type="button" class="btn btn-primary" onclick="verifyCardNumbersQuick()" style="font-weight: 800; white-space: nowrap; padding: 0.5rem 1rem;">
                    🔍 Conferir
                </button>
            </div>
            <div id="verifyCardResultBox" style="margin-top: 0.75rem; display: none;"></div>
        </div>

        <form id="winnerForm" onsubmit="handleSaveWinner(event)">
            <div class="form-group">
                <label class="form-label" for="prize_index" style="font-weight: 800;">Qual Prêmio Bateu?</label>
                <select id="prize_index" name="prize_index" class="form-control" style="font-weight: 700;">
                    <option value="1">🥇 1º Prêmio <?= !empty($round['prize_1_title']) ? '— ' . View::e($round['prize_1_title']) : '' ?> (<?= View::money($round['prize_1']) ?>)</option>
                    <?php if ($prizesCount >= 2): ?>
                        <option value="2">🥈 2º Prêmio <?= !empty($round['prize_2_title']) ? '— ' . View::e($round['prize_2_title']) : '' ?> (<?= View::money($round['prize_2']) ?>)</option>
                    <?php endif; ?>
                    <?php if ($prizesCount >= 3): ?>
                        <option value="3">🥉 3º Prêmio <?= !empty($round['prize_3_title']) ? '— ' . View::e($round['prize_3_title']) : '' ?> (<?= View::money($round['prize_3']) ?>)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="winner_name" style="font-weight: 800;">Nome do(a) Ganhador(a)</label>
                <input type="text" id="winner_name" name="winner_name" class="form-control" placeholder="Ex: Maria José (Cartela 142)" style="font-weight: 700;">
            </div>

            <div class="form-group">
                <label class="form-label" for="seller_name" style="font-weight: 800;">Vendedor(a) da Cartela Premiada</label>
                <input type="text" id="seller_name" name="seller_name" list="sellersList" class="form-control" placeholder="Selecione ou digite o vendedor">
                <datalist id="sellersList">
                    <?php foreach ($sellers as $sl): ?>
                        <option value="<?= View::e($sl['name']) ?>"><?= !empty($sl['nickname']) ? ' (' . View::e($sl['nickname']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem;">
                <!-- Opção 1: Bateu e continua para próximo prêmio -->
                <button type="button" class="btn btn-success" onclick="saveWinnerAndContinue(true)" style="padding: 0.85rem; font-weight: 800; font-size: 1.05rem;">
                    ✅ BATEU! Gravar e Continuar Cantando para o Próximo Prêmio
                </button>

                <!-- Opção 2: Bateu e finaliza a rodada -->
                <button type="button" class="btn btn-primary" onclick="saveWinnerAndContinue(false)" style="padding: 0.75rem; font-weight: 800;">
                    🏆 BATEU! Gravar Ganhador e Encerrar a Rodada
                </button>

                <!-- Opção 3: NÃO bateu - Volta sem perder nenhuma pedra -->
                <button type="button" class="btn btn-secondary" onclick="cancelConferenceResumeSinging()" style="padding: 0.75rem; font-weight: 800; border: 1px solid #cbd5e1;">
                    ❌ NÃO BATEU (Retornar a Cantar Normalmente)
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.keypad-btn {
    height: 58px;
    font-size: 1.6rem;
    font-weight: 800;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.1s ease;
}
.keypad-btn:active {
    transform: scale(0.95);
    background: #e2e8f0;
}
.board-ball-btn:hover {
    transform: scale(1.15);
    z-index: 10;
}
@keyframes pulse-btn {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.9; }
}

@media (max-width: 900px) {
    .speaker-grid {
        grid-template-columns: 1fr !important;
    }
    .mobile-tabs-bar {
        display: flex !important;
    }
    .mobile-hidden {
        display: none !important;
    }
    .keypad-btn {
        height: 65px !important;
        font-size: 1.8rem !important;
    }
    #keypadInput {
        height: 65px !important;
        font-size: 2.5rem !important;
    }
}
</style>

<script>
const roundId = <?= (int)$round['id'] ?>;
const csrfToken = '<?= \App\Core\Csrf::getToken() ?>';
let keypadBuffer = '';

function switchMobileTab(tab) {
    const keypadPanel = document.getElementById('keypadPanel');
    const boardPanel = document.getElementById('boardPanel');
    const btnKeypad = document.getElementById('tabBtnKeypad');
    const btnBoard = document.getElementById('tabBtnBoard');

    if (tab === 'keypad') {
        keypadPanel.classList.remove('mobile-hidden');
        boardPanel.classList.add('mobile-hidden');
        btnKeypad.className = 'btn btn-primary';
        btnBoard.className = 'btn btn-secondary';
    } else {
        keypadPanel.classList.add('mobile-hidden');
        boardPanel.classList.remove('mobile-hidden');
        btnKeypad.className = 'btn btn-secondary';
        btnBoard.className = 'btn btn-primary';
    }
}

function openShareMobileModal() {
    const modal = document.getElementById('modalMobileLink');
    if (modal) modal.showModal();
}

function copyMobileLink() {
    const link = window.location.origin + '<?= View::url("locutor") ?>';
    navigator.clipboard.writeText(link).then(() => {
        alert('✅ Link do Locutor copiado com sucesso! Cole no WhatsApp ou navegador do celular.');
    }).catch(() => {
        prompt('Copie o link abaixo:', link);
    });
}

function shareMobileWhatsApp() {
    const link = window.location.origin + '<?= View::url("locutor") ?>';
    const text = `🎤 *MÓDULO DO LOCUTOR — SHOW DE PRÊMIOS*\n` +
                 `Acesse pelo celular para cantar as pedras e conferir bingo:\n` +
                 link;
    const url = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(url, '_blank');
}

function updateKeypadDisplay() {
    const input = document.getElementById('keypadInput');
    const predEl = document.getElementById('predictedLetter');
    input.value = keypadBuffer;

    const num = parseInt(keypadBuffer, 10);
    if (!isNaN(num) && num >= 1 && num <= 75) {
        predEl.textContent = getLetter(num);
    } else {
        predEl.textContent = '';
    }
}

function pressKey(digit) {
    if (keypadBuffer.length >= 2) return;
    keypadBuffer += digit.toString();
    updateKeypadDisplay();
}

function clearKeypad() {
    keypadBuffer = '';
    updateKeypadDisplay();
}

function backspaceKeypad() {
    keypadBuffer = keypadBuffer.slice(0, -1);
    updateKeypadDisplay();
}

function getLetter(num) {
    if (num >= 1 && num <= 15) return 'B';
    if (num >= 16 && num <= 30) return 'I';
    if (num >= 31 && num <= 45) return 'N';
    if (num >= 46 && num <= 60) return 'G';
    if (num >= 61 && num <= 75) return 'O';
    return '';
}

function getLetterColor(num) {
    if (num >= 1 && num <= 15) return '#dc2626';
    if (num >= 16 && num <= 30) return '#2563eb';
    if (num >= 31 && num <= 45) return '#16a34a';
    if (num >= 46 && num <= 60) return '#d97706';
    if (num >= 61 && num <= 75) return '#7c3aed';
    return '#2563eb';
}

async function submitNumber() {
    const num = parseInt(keypadBuffer, 10);
    if (isNaN(num) || num < 1 || num > 75) {
        alert('Por favor, informe uma pedra válida de 1 a 75.');
        return;
    }
    await sendCallNumber(num);
    clearKeypad();
}

async function directCall(num) {
    const btn = document.getElementById(`board_ball_${num}`);
    if (btn && btn.classList.contains('called')) {
        if (!confirm(`A pedra ${getLetter(num)}-${num} já foi cantada. Deseja chamá-la novamente?`)) {
            return;
        }
    }
    await sendCallNumber(num);
}

async function sendCallNumber(num) {
    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('round_id', roundId);
        formData.append('number', num);

        const res = await fetch('<?= View::url("locutor/cantar-pedra") ?>', {
            method: 'POST',
            body: formData,
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });

        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            console.error('Server response error:', text);
            alert('Aviso do servidor: ' + (text.replace(/<[^>]*>?/gm, '').trim().slice(0, 150) || 'Erro desconhecido.'));
            return;
        }

        if (data.success) {
            // Atualiza display da última pedra
            document.getElementById('lastBallLetter').textContent = data.letter;
            document.getElementById('lastBallNumber').textContent = data.number;
            document.getElementById('lastBallDisplay').style.background = getLetterColor(data.number);
            document.getElementById('calledCount').textContent = data.total_called;

            // Ilumina a pedra no tabuleiro
            const btn = document.getElementById(`board_ball_${data.number}`);
            if (btn) {
                btn.classList.add('called');
                btn.style.background = getLetterColor(data.number);
                btn.style.borderColor = getLetterColor(data.number);
                btn.style.color = '#ffffff';
            }

            // Atualiza status se mudou
            if (data.status) {
                updateStatusBadge(data.status);
            }

            // Som sintetizado animado de chamada
            speakNumberAnimated(data.letter, data.number);
        } else {
            alert(data.error || 'Erro ao cantar pedra.');
        }
    } catch (e) {
        console.error('sendCallNumber error:', e);
        alert('Falha de conexão com o servidor. Verifique a internet e tente novamente.');
    }
}

async function undoLastNumber() {
    if (!confirm('Deseja realmente desfazer a última pedra cantada?')) return;

    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('round_id', roundId);

        const res = await fetch('<?= View::url("locutor/desfazer-pedra") ?>', {
            method: 'POST',
            body: formData,
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });
        
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            alert('Aviso do servidor: ' + (text.replace(/<[^>]*>?/gm, '').trim().slice(0, 150) || 'Erro desconhecido.'));
            return;
        }

        if (data.success) {
            // Apaga a pedra desfeita do tabuleiro
            const btn = document.getElementById(`board_ball_${data.removed_number}`);
            if (btn) {
                btn.classList.remove('called');
                btn.style.background = '#ffffff';
                btn.style.borderColor = '#cbd5e1';
                btn.style.color = '#334155';
            }

            // Atualiza última pedra
            document.getElementById('lastBallLetter').textContent = data.last_letter || '—';
            document.getElementById('lastBallNumber').textContent = data.last_called_number || '—';
            document.getElementById('calledCount').textContent = data.total_called;
        } else {
            alert(data.error || 'Erro ao desfazer.');
        }
    } catch (e) {
        alert('Erro ao desfazer.');
    }
}

async function setStatus(newStatus) {
    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('round_id', roundId);
        formData.append('status', newStatus);
        formData.append('_ajax', '1');

        const res = await fetch('<?= View::url("locutor/alterar-status") ?>', {
            method: 'POST',
            body: formData,
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });
        
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            alert('Aviso do servidor: ' + (text.replace(/<[^>]*>?/gm, '').trim().slice(0, 150) || 'Erro desconhecido.'));
            return;
        }

        if (data.success) {
            updateStatusBadge(newStatus);
        }
    } catch (e) {
        alert('Erro ao alterar status.');
    }
}

function updateStatusBadge(status) {
    const el = document.getElementById('currentStatusBadge');
    if (!el) return;
    const texts = {
        'OPEN': '🟢 ABERTA (Vendas)',
        'IN_PROGRESS': '⚡ EM ANDAMENTO (Cantoria)',
        'PAUSED': '⏸️ PAUSADA (Intervalo)',
        'CHECKING': '🔔 EM CONFERÊNCIA (Bingo!)',
        'CLOSED': '🏁 FECHADA (Concluída)'
    };
    el.textContent = texts[status] || status;
}

function openBingoCheckingModal() {
    setStatus('CHECKING');
    document.getElementById('bingoCheckingModal').style.display = 'flex';
}

function closeBingoCheckingModal() {
    document.getElementById('bingoCheckingModal').style.display = 'none';
}

function cancelConferenceResumeSinging() {
    closeBingoCheckingModal();
    setStatus('IN_PROGRESS');
}

function verifyCardNumbersQuick() {
    const input = document.getElementById('verifyCardNumbersInput');
    const resultBox = document.getElementById('verifyCardResultBox');
    if (!input || !resultBox) return;

    const raw = input.value.trim();
    if (!raw) {
        resultBox.style.display = 'none';
        return;
    }

    const tokens = raw.match(/\d+/g) || [];
    if (tokens.length === 0) {
        resultBox.innerHTML = '<span style="color: #ef4444; font-weight: 700;">Nenhum número válido digitado.</span>';
        resultBox.style.display = 'block';
        return;
    }

    const numbers = tokens.map(t => parseInt(t, 10)).filter(n => n >= 1 && n <= 75);
    if (numbers.length === 0) {
        resultBox.innerHTML = '<span style="color: #ef4444; font-weight: 700;">Digite números válidos entre 1 e 75.</span>';
        resultBox.style.display = 'block';
        return;
    }

    let allCalled = true;
    let missing = [];
    let html = '<div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem;">';

    numbers.forEach(num => {
        const isOut = calledSet[num] !== undefined;
        if (!isOut) {
            allCalled = false;
            missing.push(num);
        }
        const color = isOut ? '#16a34a' : '#dc2626';
        const bg = isOut ? '#dcfce7' : '#fee2e2';
        const icon = isOut ? '✅' : '❌';
        html += `<span style="padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 800; font-size: 0.95rem; background: ${bg}; color: ${color}; border: 1px solid ${color};">${icon} ${num}</span>`;
    });
    html += '</div>';

    if (allCalled) {
        resultBox.innerHTML = `
            <div style="background: #ecfdf5; border: 1px solid #10b981; color: #065f46; padding: 0.6rem 0.85rem; border-radius: 8px; font-weight: 800;">
                🎉 CARTELA 100% VÁLIDA! Todos os ${numbers.length} números conferidos JÁ FORAM CANTADOS!
            </div>
            ${html}
        `;
        if (typeof confetti === 'function') {
            confetti({ particleCount: 70, spread: 70, origin: { y: 0.6 } });
        }
    } else {
        resultBox.innerHTML = `
            <div style="background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; padding: 0.6rem 0.85rem; border-radius: 8px; font-weight: 800;">
                ⚠️ ALERTA: ${missing.length} número(s) AINDA NÃO FORAM CANTADOS (${missing.join(', ')})!
            </div>
            ${html}
        `;
    }
    resultBox.style.display = 'block';
}

function handleQuickVerifyCardKey(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        verifyCardNumbersQuick();
    }
}

async function saveWinnerAndContinue(continueNext) {
    const winnerName = document.getElementById('winner_name').value.trim();
    const sellerName = document.getElementById('seller_name').value.trim();
    const prizeIndex = document.getElementById('prize_index').value;

    if (!winnerName) {
        alert('Por favor, informe o nome do(a) ganhador(a).');
        document.getElementById('winner_name').focus();
        return;
    }

    try {
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('round_id', roundId);
        formData.append('prize_index', prizeIndex);
        formData.append('winner_name', winnerName);
        formData.append('seller_name', sellerName);
        if (continueNext) formData.append('continue_next', '1');

        const res = await fetch('<?= View::url("locutor/salvar-ganhador") ?>', {
            method: 'POST',
            body: formData,
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });
        
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            alert('Aviso do servidor: ' + (text.replace(/<[^>]*>?/gm, '').trim().slice(0, 150) || 'Erro desconhecido.'));
            return;
        }

        if (data.success) {
            if (typeof confetti === 'function') {
                confetti({ particleCount: 150, spread: 100, origin: { y: 0.5 } });
            }
            alert('🎉 ' + data.message);
            closeBingoCheckingModal();
            if (!continueNext) {
                setStatus('CLOSED');
            } else {
                updateStatusBadge('IN_PROGRESS');
            }
        } else {
            alert(data.error || 'Erro ao salvar ganhador.');
        }
    } catch (e) {
        alert('Erro ao salvar ganhador.');
    }
}

// Síntese de Voz Animada do Sistema
let voiceEnabled = true;

function toggleVoiceEnabled(checked) {
    voiceEnabled = !!checked;
}

function stopSpeech() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }
}

function formatMoneyForSpeech(val) {
    val = parseFloat(val) || 0;
    const reais = Math.floor(val);
    const centavos = Math.round((val - reais) * 100);

    let text = '';
    if (reais > 0) {
        text += `${reais} ${reais === 1 ? 'real' : 'reais'}`;
    }
    if (centavos > 0) {
        if (text) text += ' e ';
        text += `${centavos} ${centavos === 1 ? 'centavo' : 'centavos'}`;
    }
    return text || 'zero reais';
}

function getBestPtBrVoice() {
    if (!('speechSynthesis' in window)) return null;
    const voices = window.speechSynthesis.getVoices();
    return voices.find(v => (v.lang === 'pt-BR' || v.lang === 'pt_BR') && (v.name.includes('Google') || v.name.includes('Luciana') || v.name.includes('Natural') || v.name.includes('Daniel') || v.name.includes('Maria') || v.name.includes('Francisca')))
        || voices.find(v => v.lang === 'pt-BR' || v.lang === 'pt_BR')
        || null;
}

// Garante carregamento das vozes do navegador
if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = () => {
        getBestPtBrVoice();
    };
}

const bingoPhrases = [
    (l, n) => `Atenção! Letra ${l}, número ${n}! Repetindo: ${l}, ${n}!`,
    (l, n) => `Olha a pedra cantada! Letra ${l}, número ${n}! Letra ${l}, ${n}!`,
    (l, n) => `Saiu mais uma pedra! Letra ${l}, número ${n}! Repetindo: ${l}, ${n}!`,
    (l, n) => `Atenção jogadores! Letra ${l}, número ${n}! ${l}, ${n}!`,
    (l, n) => `Olha o bingo girando! Letra ${l}, número ${n}! Letra ${l}, ${n}!`
];

function speakNumberAnimated(letter, number) {
    if (!voiceEnabled || !('speechSynthesis' in window)) return;
    try {
        window.speechSynthesis.cancel();

        const phraseFn = bingoPhrases[Math.floor(Math.random() * bingoPhrases.length)];
        const text = phraseFn(letter, number);

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'pt-BR';
        utterance.rate = 1.05; // ritmo ágil e animado
        utterance.pitch = 1.18; // entonação alegre e enérgica

        const bestVoice = getBestPtBrVoice();
        if (bestVoice) utterance.voice = bestVoice;

        window.speechSynthesis.speak(utterance);
    } catch (e) {
        console.error('Speech error:', e);
    }
}

function repeatLastNumberSpeech() {
    const letter = document.getElementById('lastBallLetter').textContent.trim();
    const number = document.getElementById('lastBallNumber').textContent.trim();
    if (!number || number === '—') {
        alert('Nenhuma pedra foi cantada ainda nesta rodada.');
        return;
    }
    speakNumberAnimated(letter, parseInt(number, 10));
}

function buildRoundAnnouncementText() {
    const parts = [];
    parts.push("Atenção senhoras e senhores! Sejam todos bem-vindos ao Show de Prêmios!");

    // Rodada
    const roundTitle = "<?= View::e($round['round_name'] ?: ('Rodada ' . $round['round_number'])) ?>";
    parts.push(`Vamos agora com a ${roundTitle}!`);

    // Cor da cartela
    <?php if (!empty($round['card_color'])): ?>
    parts.push("Atenção para a cor da cartela em jogo: Cartela <?= View::e($round['card_color']) ?>!");
    <?php endif; ?>

    // Prêmios
    <?php
    $prizesCount = (int)($round['prizes_count'] ?? 2);
    ?>
    parts.push("Confira as nossas premiações desta rodada:");

    // 1º Prêmio
    <?php
    $p1Title = trim($round['prize_1_title'] ?? '');
    $p1Val = (float)($round['prize_1'] ?? 0);
    ?>
    let p1 = "Primeiro prêmio: ";
    <?php if (!empty($p1Title) && $p1Val > 0): ?>
    p1 += "<?= View::e($p1Title) ?>, no valor de " + formatMoneyForSpeech(<?= $p1Val ?>) + "!";
    <?php elseif (!empty($p1Title)): ?>
    p1 += "<?= View::e($p1Title) ?>!";
    <?php else: ?>
    p1 += formatMoneyForSpeech(<?= $p1Val ?>) + " em dinheiro!";
    <?php endif; ?>
    parts.push(p1);

    // 2º Prêmio
    <?php if ($prizesCount >= 2): ?>
    <?php
    $p2Title = trim($round['prize_2_title'] ?? '');
    $p2Val = (float)($round['prize_2'] ?? 0);
    ?>
    let p2 = "Segundo prêmio: ";
    <?php if (!empty($p2Title) && $p2Val > 0): ?>
    p2 += "<?= View::e($p2Title) ?>, no valor de " + formatMoneyForSpeech(<?= $p2Val ?>) + "!";
    <?php elseif (!empty($p2Title)): ?>
    p2 += "<?= View::e($p2Title) ?>!";
    <?php else: ?>
    p2 += formatMoneyForSpeech(<?= $p2Val ?>) + " em dinheiro!";
    <?php endif; ?>
    parts.push(p2);
    <?php endif; ?>

    // 3º Prêmio
    <?php if ($prizesCount >= 3): ?>
    <?php
    $p3Title = trim($round['prize_3_title'] ?? '');
    $p3Val = (float)($round['prize_3'] ?? 0);
    ?>
    let p3 = "Terceiro prêmio: ";
    <?php if (!empty($p3Title) && $p3Val > 0): ?>
    p3 += "<?= View::e($p3Title) ?>, no valor de " + formatMoneyForSpeech(<?= $p3Val ?>) + "!";
    <?php elseif (!empty($p3Title)): ?>
    p3 += "<?= View::e($p3Title) ?>!";
    <?php else: ?>
    p3 += formatMoneyForSpeech(<?= $p3Val ?>) + " em dinheiro!";
    <?php endif; ?>
    parts.push(p3);
    <?php endif; ?>

    // Valores das cartelas (ex: 1 cartela por 3 reais e 2 cartelas por 5 reais)
    const singleTxt = formatMoneyForSpeech(<?= $singlePrice ?>);
    const bundleTxt = formatMoneyForSpeech(<?= $bundlePrice ?>);
    const bundleQty = <?= (int)$bundleQty ?>;
    parts.push(`Aproveitem para garantir suas cartelas com nossos vendedores! 1 cartela por ${singleTxt}, e pacote de ${bundleQty} cartelas por ${bundleTxt}!`);
    parts.push("Preparem suas canetas e muito boa sorte a todos!");

    return parts.join(' ');
}

function speakRoundAnnouncementAnimated() {
    if (!('speechSynthesis' in window)) {
        alert('Seu navegador não possui suporte a síntese de voz.');
        return;
    }
    try {
        window.speechSynthesis.cancel();
        const fullText = buildRoundAnnouncementText();

        const utterance = new SpeechSynthesisUtterance(fullText);
        utterance.lang = 'pt-BR';
        utterance.rate = 1.02; // ritmo natural de locutor
        utterance.pitch = 1.12; // tom animado

        const bestVoice = getBestPtBrVoice();
        if (bestVoice) utterance.voice = bestVoice;

        window.speechSynthesis.speak(utterance);
    } catch (e) {
        console.error('Announcement voice error:', e);
    }
}

// Suporte a Teclado Físico
document.addEventListener('keydown', (e) => {
    if (document.getElementById('bingoCheckingModal').style.display === 'flex') {
        return; // Não intercepta se o modal estiver aberto
    }
    if (e.key >= '0' && e.key <= '9') {
        pressKey(e.key);
    } else if (e.key === 'Backspace') {
        backspaceKeypad();
    } else if (e.key === 'Escape' || e.key === 'Delete') {
        clearKeypad();
    } else if (e.key === 'Enter') {
        submitNumber();
    }
});
</script>

