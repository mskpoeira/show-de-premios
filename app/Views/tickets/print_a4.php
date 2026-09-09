<?php
use AppCoreView;
use AppServicesTicketService;

// Agrupa os números por colunas B, I, N, G, O para exibição na grade
$grid = ['B' => [], 'I' => [], 'N' => [], 'G' => [], 'O' => []];
foreach ($ticket['numbers'] as $n) {
    $grid[$n['column_letter']][] = $n;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartela <?= View::e($ticket['ticket_number']) ?> — Impressão Oficial</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .page-container {
            display: flex;
            flex-direction: column;
            gap: 12mm;
        }
        .ticket-card {
            border: 2px solid #0284c7;
            border-radius: 12px;
            padding: 12px 16px;
            position: relative;
            background: #fff;
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .cut-line {
            border-top: 1px dashed #94a3b8;
            margin: 8mm 0;
            position: relative;
            text-align: center;
        }
        .cut-line::after {
            content: '✂️ Linha de Corte';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 0 10px;
            font-size: 10px;
            color: #64748b;
        }
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .event-title {
            font-size: 16px;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
        }
        .ticket-id {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .ticket-body {
            display: flex;
            gap: 16px;
        }
        .bingo-grid {
            flex: 1;
            border-collapse: collapse;
            width: 100%;
        }
        .bingo-grid th {
            background: #0284c7;
            color: #fff;
            font-size: 16px;
            font-weight: 900;
            padding: 6px;
            text-align: center;
            border: 1px solid #0284c7;
        }
        .bingo-grid td {
            border: 1px solid #cbd5e1;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            padding: 8px;
            height: 36px;
        }
        .bingo-grid td.center-free {
            background: #f0fdf4;
            color: #16a34a;
            font-size: 11px;
            font-weight: 900;
        }
        .ticket-sidebar {
            width: 170px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 10px;
            border-left: 1px dashed #cbd5e1;
            padding-left: 12px;
        }
        .qr-placeholder {
            width: 120px;
            height: 120px;
            margin: 0 auto 6px auto;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #f8fafc;
        }
        .no-print {
            background: #0f172a;
            color: #fff;
            padding: 12px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .no-print button {
            background: #16a34a;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            margin: 0 6px;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <span>🖨️ Modo de Impressão Oficial de Cartelas</span>
        <button onclick="window.print()">Imprimir Agora</button>
        <button onclick="window.close()" style="background:#475569;">Fechar</button>
    </div>

    <div class="page-container" style="padding: 10px;">
        <?php 
        // Renderiza a quantidade de cartelas conforme o layout solicitado (1 ou 2 por padrão)
        for ($k = 0; $k < $layout; $k++): 
            if ($k > 0): ?>
                <div class="cut-line"></div>
            <?php endif; ?>

            <div class="ticket-card">
                <div class="ticket-header">
                    <div>
                        <div class="event-title"><?= View::e($ticket['event_name']) ?></div>
                        <div style="font-size: 11px; color: #64748b;"><?= View::date($ticket['event_date']) ?> &bull; <?= View::e($ticket['event_location']) ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div class="ticket-id"><?= View::e($ticket['ticket_number']) ?></div>
                        <div style="font-size: 10px; color: #64748b;">Código: <strong><?= View::e($ticket['check_code']) ?></strong></div>
                    </div>
                </div>

                <div class="ticket-body">
                    <!-- Grade 5x5 -->
                    <table class="bingo-grid">
                        <thead>
                            <tr>
                                <th>B</th>
                                <th>I</th>
                                <th>N</th>
                                <th>G</th>
                                <th>O</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($row = 0; $row < 5; $row++): ?>
                                <tr>
                                    <?php foreach (['B', 'I', 'N', 'G', 'O'] as $col): 
                                        $cell = $grid[$col][$row] ?? null;
                                        $val = $cell ? $cell['number_value'] : '';
                                        $isCenter = $cell && $cell['is_center'] == 1;
                                    ?>
                                        <td class="<?= $isCenter ? 'center-free' : '' ?>">
                                            <?= $isCenter ? '★<br>LIVRE' : $val ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <!-- Lateral de Segurança e QR -->
                    <div class="ticket-sidebar">
                        <div style="text-align: center;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=<?= urlencode('https://showdepremios.mskpoeira.com.br/v/' . $ticket['secure_token']) ?>" alt="QR Code" style="width:110px; height:110px; border-radius:4px;">
                            <div style="font-size: 8px; color: #64748b; margin-top: 2px;">Validação Oficial</div>
                        </div>

                        <div>
                            <div style="font-weight: bold; color: #0284c7; margin-bottom: 2px;">PRÊMIOS:</div>
                            <?php foreach ($ticket['prizes'] as $p): ?>
                                <div>• <?= View::e($p['title']) ?> (<?= View::money($p['value']) ?>)</div>
                            <?php endforeach; ?>
                        </div>

                        <div style="border-top: 1px dashed #cbd5e1; padding-top: 4px; color: #64748b;">
                            <div>Comprador: <?= View::e($ticket['buyer_name'] ?: 'Portador') ?></div>
                            <div>CPF: <?= View::e($masked_cpf) ?></div>
                            <div>Emissão: <?= date('d/m/Y H:i') ?> (Via <?= (int)$ticket['print_count'] ?>)</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>

</body>
</html>
