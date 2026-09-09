<?php
use AppCoreView;
$summary = $intelligence['summary'] ?? [];
$called = $intelligence['called_numbers'] ?? [];
$calledValues = array_column($called, 'number_value');
$p = $intelligence['draw'] ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin: 0; font-size: 1.75rem; color: #0f172a; font-weight: 800;">
            🎤 Central Operacional do Sorteio
        </h1>
        <p style="margin: 0.25rem 0 0 0; color: #64748b;">
            <?= View::e($event['name']) ?> &bull; Prêmio em Disputa: <strong style="color: #0284c7;"><?= View::e($p['prize_title'] ?? '1º Prêmio') ?> (<?= View::money($p['prize_value'] ?? 0) ?>)</strong>
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <select id="selectPrize" onchange="changePrize(this.value)" style="padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; background: #fff;">
            <?php foreach ($prizes as $pz): ?>
                <option value="<?= $pz['id'] ?>" <?= ((int)($draw['prize_id'] ?? 1) === (int)$pz['id']) ? 'selected' : '' ?>>
                    🏆 <?= View::e($pz['title']) ?> (<?= View::money($pz['value']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button onclick="undoNumber()" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 700; cursor: pointer;">
            ↩️ Desfazer Última
        </button>
        <a href="/telao" target="_blank" class="btn" style="background: #0284c7; color: #fff; text-decoration: none; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700;">
            📺 Abrir Telão Público ↗
        </a>
    </div>
</div>

<!-- Alerta de Vencedor Detectado Automaticamente -->
<div id="winnerAlertBanner" style="<?= empty($intelligence['winners']) ? 'display: none;' : '' ?> background: linear-gradient(135deg, #15803d 0%, #0f766e 100%); color: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(21, 128, 61, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span style="background: #fef08a; color: #854d0e; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 800; font-size: 0.85rem;">
                🚨 VENCEDOR DETECTADO NO SISTEMA
            </span>
            <h2 id="winnerTitle" style="margin: 0.5rem 0 0 0; font-size: 1.6rem; font-weight: 900;">
                <?php if (!empty($intelligence['winners'])): 
                    $w = $intelligence['winners'][0];
                ?>
                    Cartela <?= View::e($w['ticket_number']) ?> &bull; <?= View::e($w['buyer_name'] ?: 'Comprador não cadastrado') ?>
                <?php endif; ?>
            </h2>
            <p id="winnerSubtitle" style="margin: 0.25rem 0 0 0; font-size: 0.95rem; opacity: 0.9;">
                Aviso restrito à organização! O telão público permanece em suspense.
            </p>
        </div>
        <div id="winnerActions" style="display: flex; gap: 0.75rem;">
            <?php if (!empty($intelligence['winners'])): 
                $w = $intelligence['winners'][0];
            ?>
                <?php if (!empty($w['buyer_phone'])): ?>
                    <button onclick="contactWinner(<?= (int)$w['buyer_id'] ?>, <?= (int)$w['ticket_id'] ?>, 'CALL', '<?= View::e($w['buyer_phone']) ?>')" style="background: #fff; color: #0284c7; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; cursor: pointer;">
                        📞 Ligar para Ganhador
                    </button>
                    <button onclick="contactWinner(<?= (int)$w['buyer_id'] ?>, <?= (int)$w['ticket_id'] ?>, 'WHATSAPP', '<?= View::e($w['buyer_phone']) ?>', '<?= View::e($w['buyer_name']) ?>', '<?= View::e($w['ticket_number']) ?>')" style="background: #22c55e; color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; cursor: pointer;">
                        💬 Abrir WhatsApp
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Painel Superior: Última Pedra & Inteligência Resumida -->
<div style="display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Última Pedra Cantada -->
    <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; text-align: center;">
        <span style="font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Última Pedra Sorteada</span>
        <div id="lastBallDisplay" style="font-size: 4.5rem; font-weight: 900; color: #0284c7; line-height: 1.1; margin: 0.5rem 0;">
            <?php if (!empty($summary['last_called_number'])): ?>
                <?= View::e($summary['last_called_letter']) ?>-<?= (int)$summary['last_called_number'] ?>
            <?php else: ?>
                --
            <?php endif; ?>
        </div>
        <div style="font-size: 0.85rem; color: #64748b;">
            Total Sorteadas: <strong id="totalCalledCount" style="color: #0f172a;"><?= (int)($summary['total_called'] ?? 0) ?></strong> de 75
        </div>

        <div style="margin-top: 1rem; border-top: 1px solid #f1f5f9; padding-top: 0.75rem;">
            <span style="font-size: 0.75rem; color: #64748b; font-weight: 700;">ÚLTIMAS 5:</span>
            <div id="last5Display" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 0.35rem;">
                <?php foreach (($intelligence['last_5_called'] ?? []) as $lc): ?>
                    <span style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                        <?= View::e($lc['letter']) ?><?= (int)$lc['number_value'] ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Cards de Inteligência do Jogo -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">CARTELAS VÁLIDAS</span>
            <h3 id="statValid" style="margin: 0.35rem 0; font-size: 1.8rem; font-weight: 900; color: #0284c7;"><?= (int)($summary['valid_tickets'] ?? 0) ?></h3>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 600;">Concorrendo ao vivo</span>
        </div>

        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">FALTA 1 (ARMADAS)</span>
            <h3 id="statFalta1" style="margin: 0.35rem 0; font-size: 1.8rem; font-weight: 900; color: #dc2626;"><?= (int)($summary['falta_1'] ?? 0) ?></h3>
            <span style="font-size: 0.75rem; color: #64748b;">A uma pedra de bater</span>
        </div>

        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">FALTAM 2</span>
            <h3 id="statFalta2" style="margin: 0.35rem 0; font-size: 1.8rem; font-weight: 900; color: #d97706;"><?= (int)($summary['falta_2'] ?? 0) ?></h3>
            <span style="font-size: 0.75rem; color: #64748b;">Cartelas quase armadas</span>
        </div>

        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">FALTAM 3</span>
            <h3 id="statFalta3" style="margin: 0.35rem 0; font-size: 1.8rem; font-weight: 900; color: #0f766e;"><?= (int)($summary['falta_3'] ?? 0) ?></h3>
            <span style="font-size: 0.75rem; color: #64748b;">Na disputa ativa</span>
        </div>

        <!-- Distribuição de Pontuação e Melhores Cartelas (Abaixo dos cards) -->
        <div class="card" style="grid-column: span 4; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1rem 1.25rem;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #334155;">DISTRIBUIÇÃO DE PONTUAÇÃO (ACERTOS):</span>
            <div id="histDisplay" style="display: flex; gap: 1rem; margin-top: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;">
                <?php foreach (($intelligence['score_distribution'] ?? []) as $sd): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.4rem 0.8rem; text-align: center; min-width: 70px;">
                        <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;"><?= (int)$sd['hits_count'] ?> pts</span>
                        <div style="font-weight: 800; color: #0284c7; font-size: 1.1rem;"><?= (int)$sd['count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Globo de 75 Pedras (B-I-N-G-O) -->
<div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 1.5rem;">
    <h3 style="margin-top: 0; font-size: 1.15rem; color: #0f172a; margin-bottom: 1rem;">
        🎱 Painel Interativo de Pedras (1 a 75) — Clique para Cantar
    </h3>

    <?php
    $ranges = [
        'B' => [1, 15, '#0284c7'],
        'I' => [16, 30, '#0d9488'],
        'N' => [31, 45, '#eab308'],
        'G' => [46, 60, '#16a34a'],
        'O' => [61, 75, '#ef4444'],
    ];
    ?>

    <?php foreach ($ranges as $col => [$start, $end, $color]): ?>
        <div style="display: flex; align-items: center; margin-bottom: 0.6rem; gap: 0.5rem;">
            <div style="width: 38px; height: 38px; background: <?= $color ?>; color: #fff; font-weight: 900; font-size: 1.2rem; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <?= $col ?>
            </div>
            <div style="display: grid; grid-template-columns: repeat(15, 1fr); gap: 0.4rem; flex: 1;">
                <?php for ($num = $start; $num <= $end; $num++): 
                    $isCalled = in_array($num, $calledValues, true);
                ?>
                    <button 
                        id="btnBall_<?= $num ?>" 
                        onclick="callNumber(<?= $num ?>)"
                        style="height: 38px; border-radius: 6px; font-weight: 800; font-size: 1rem; border: 1px solid <?= $isCalled ? '#0284c7' : '#cbd5e1' ?>; background: <?= $isCalled ? '#0284c7' : '#f8fafc' ?>; color: <?= $isCalled ? '#fff' : '#1e293b' ?>; cursor: pointer; transition: all 0.15s;"
                    >
                        <?= $num ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Melhores Cartelas (Apenas Interno) -->
<div class="card" style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem;">
    <h3 style="margin-top: 0; font-size: 1.15rem; color: #0f172a; margin-bottom: 0.75rem;">
        🎯 Cartelas Mais Próximas de Bater (Falta 1, 2 ou 3)
    </h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
        <thead>
            <tr style="background: #f1f5f9; text-align: left; color: #475569;">
                <th style="padding: 0.6rem 1rem;">Cartela</th>
                <th style="padding: 0.6rem 1rem;">Faltantes</th>
                <th style="padding: 0.6rem 1rem;">Acertos</th>
                <th style="padding: 0.6rem 1rem;">Comprador</th>
                <th style="padding: 0.6rem 1rem; text-align: right;">Ações</th>
            </tr>
        </thead>
        <tbody id="bestTicketsBody">
            <?php foreach (($intelligence['best_tickets'] ?? []) as $bt): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.6rem 1rem; font-weight: 700; color: #0284c7;"><?= View::e($bt['ticket_number']) ?></td>
                    <td style="padding: 0.6rem 1rem;">
                        <span style="font-weight: 800; color: <?= $bt['remaining_count'] == 1 ? '#dc2626' : ($bt['remaining_count'] == 2 ? '#d97706' : '#0f766e') ?>;">
                            FALTA <?= (int)$bt['remaining_count'] ?>
                        </span>
                    </td>
                    <td style="padding: 0.6rem 1rem; color: #334155;"><?= (int)$bt['hits_count'] ?> acertos</td>
                    <td style="padding: 0.6rem 1rem;"><?= View::e($bt['buyer_name'] ?: 'Portador') ?></td>
                    <td style="padding: 0.6rem 1rem; text-align: right;">
                        <a href="/cartelas/imprimir/<?= View::e($bt['ticket_number']) ?>" target="_blank" style="color: #0284c7; text-decoration: none; font-weight: 600;">Ver ➔</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
const drawId = <?= (int)$draw['id'] ?>;

function callNumber(number) {
    fetch('/sorteio/cantar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'draw_id=' + drawId + '&number=' + number
    }).then(res => res.json()).then(data => {
        if (data.success) {
            updateUIWithIntelligence(data.intelligence);
            // Destaca botão
            const btn = document.getElementById('btnBall_' + number);
            if (btn) {
                btn.style.background = '#0284c7';
                btn.style.color = '#fff';
                btn.style.borderColor = '#0284c7';
            }
            if (data.new_winners && data.new_winners.length > 0) {
                showWinnerBanner(data.new_winners[0]);
            }
        } else {
            alert('Aviso: ' + data.message);
        }
    });
}

function undoNumber() {
    if (!confirm('Deseja realmente desfazer a última pedra sorteada?')) return;
    fetch('/sorteio/desfazer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'draw_id=' + drawId
    }).then(res => res.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

function changePrize(prizeId) {
    fetch('/sorteio/alterar-premio', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'draw_id=' + drawId + '&prize_id=' + prizeId
    }).then(res => res.json()).then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}

function contactWinner(buyerId, ticketId, type, phone, name = '', ticketNumber = '') {
    fetch('/sorteio/ganhador/contato', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'buyer_id=' + buyerId + '&ticket_id=' + ticketId + '&type=' + type
    }).then(() => {
        if (type === 'CALL') {
            window.location.href = 'tel:' + phone;
        } else {
            const cleanPhone = phone.replace(/\D/g, '');
            const msg = encodeURIComponent('Olá ' + name + '! Parabéns! Sua cartela ' + ticketNumber + ' foi premiada no Show de Prêmios Oficial!');
            window.open('https://wa.me/' + cleanPhone + '?text=' + msg, '_blank');
        }
    });
}

function showWinnerBanner(winner) {
    const banner = document.getElementById('winnerAlertBanner');
    const title = document.getElementById('winnerTitle');
    const bName = winner.buyer ? winner.buyer.name : 'Portador';
    title.textContent = 'Cartela ' + winner.ticket_number + ' • ' + bName;
    banner.style.display = 'block';
}

function updateUIWithIntelligence(intel) {
    if (!intel || !intel.summary) return;
    const s = intel.summary;
    document.getElementById('statValid').textContent = s.valid_tickets;
    document.getElementById('statFalta1').textContent = s.falta_1;
    document.getElementById('statFalta2').textContent = s.falta_2;
    document.getElementById('statFalta3').textContent = s.falta_3;
    document.getElementById('totalCalledCount').textContent = s.total_called;
    if (s.last_called_number) {
        document.getElementById('lastBallDisplay').textContent = s.last_called_letter + '-' + s.last_called_number;
    }
}
</script>
