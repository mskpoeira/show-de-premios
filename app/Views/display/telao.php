<?php
use App\Core\View;

$dayDate = $day ? View::date($day['operation_date']) : date('d/m/Y');
$roundNum = $round ? (int)$round['round_number'] : 1;
$roundName = $round ? ($round['round_name'] ?? ('Rodada ' . $roundNum)) : ('Rodada ' . $roundNum);
$cardColor = $round ? ($round['card_color'] ?? 'Amarela') : 'Amarela';
$prizesCount = $round ? (int)($round['prizes_count'] ?? 2) : 2;
$roundStatus = $round ? $round['status'] : 'NO_ROUND';

$prize1 = $round ? View::money($round['prize_1'] ?? 0) : 'R$ 0,00';
$prize1Title = $round ? ($round['prize_1_title'] ?? '') : '';
$prize2 = $round ? View::money($round['prize_2'] ?? 0) : 'R$ 0,00';
$prize2Title = $round ? ($round['prize_2_title'] ?? '') : '';
$prize3 = $round ? View::money($round['prize_3'] ?? 0) : 'R$ 0,00';
$prize3Title = $round ? ($round['prize_3_title'] ?? '') : '';

$winner1 = $round ? ($round['winner_1_name'] ?? $round['winner_name'] ?? '') : '';
$seller1 = $round ? ($round['seller_1_name'] ?? '') : '';
$winner2 = $round ? ($round['winner_2_name'] ?? '') : '';
$seller2 = $round ? ($round['seller_2_name'] ?? '') : '';
$winner3 = $round ? ($round['winner_3_name'] ?? '') : '';
$seller3 = $round ? ($round['seller_3_name'] ?? '') : '';

$bundleQty = (int)($pricingRule['bundle_quantity'] ?? 3);
$bundlePrice = View::money($pricingRule['bundle_price'] ?? 5.00);
$singlePrice = View::money($pricingRule['single_price'] ?? 2.00);
$systemTitle = !empty($systemTitle) ? $systemTitle : View::systemTitle();

$isPixEnabled = (($pixShowOnTelao ?? true) !== false && ($pixShowOnTelao ?? 'true') !== 'false' && ($pixShowOnTelao ?? 'true') !== '0');
$pixKeyVal = !empty($pixKey) ? trim($pixKey) : 'mskpoeira@gmail.com';
$shouldShowPix = $isPixEnabled && !empty($pixKeyVal) && ($roundStatus !== 'IN_PROGRESS');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($systemTitle) ?> — Telão Oficial</title>
    <link rel="icon" type="image/svg+xml" href="<?= View::url('assets/icons/icon.svg') ?>">
    <link rel="manifest" href="<?= View::url('manifest.json') ?>">
    <meta name="theme-color" content="#1e1b4b">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        html, body {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }
        body {
            background: radial-gradient(circle at top center, #1e293b, #090d16 85%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(0.35rem, 0.9vh, 0.75rem) clamp(0.8rem, 1.8vw, 1.8rem);
        }

        /* Top bar - Centralizado em duas linhas: linha 1 título/marca, linha 2 botões/relógio */
        .topbar {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding-bottom: clamp(0.2rem, 0.45vh, 0.45rem);
            gap: clamp(0.12rem, 0.35vh, 0.35rem);
            text-align: center;
            flex-shrink: 0;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(0.4rem, 0.9vw, 0.9rem);
            text-align: center;
            width: 100%;
        }
        .brand-icon {
            font-size: clamp(1.5rem, 3vh, 2.4rem);
            filter: drop-shadow(0 0 12px rgba(234, 179, 8, 0.6));
        }
        .brand-title {
            font-size: clamp(0.95rem, 2vh, 1.65rem);
            font-weight: 900;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #fef08a, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
            text-align: center;
            line-height: 1.15;
            max-width: 100%;
        }
        .brand-sub {
            font-size: clamp(0.65rem, 1.15vh, 0.85rem);
            color: #94a3b8;
            letter-spacing: 0.8px;
            font-weight: 600;
            white-space: nowrap;
            text-align: center;
            margin-top: 0.1rem;
        }
        .controls {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: clamp(0.25rem, 0.6vw, 0.6rem);
            flex-wrap: wrap;
            width: 100%;
            position: static !important;
            transform: none !important;
            margin-top: clamp(0.08rem, 0.25vh, 0.2rem);
        }
        .clock-badge {
            font-size: clamp(1rem, 2.1vh, 1.5rem);
            font-weight: 800;
            background: rgba(255, 255, 255, 0.08);
            padding: clamp(0.12rem, 0.3vh, 0.25rem) clamp(0.6rem, 1vw, 1rem);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #38bdf8;
            font-variant-numeric: tabular-nums;
            letter-spacing: 1.5px;
        }
        .btn-fullscreen {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: clamp(0.18rem, 0.35vh, 0.35rem) clamp(0.55rem, 0.9vw, 0.9rem);
            border-radius: 8px;
            cursor: pointer;
            font-size: clamp(0.72rem, 1.25vh, 0.88rem);
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .btn-fullscreen:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.04);
        }

        /* Fullscreen mode: APENAS o relógio visível, todas as outras opções ocultas */
        :fullscreen,
        :-webkit-full-screen,
        :-moz-full-screen,
        :-ms-fullscreen,
        html.is-fullscreen,
        body.is-fullscreen {
            height: 100vh !important;
            max-height: 100vh !important;
            overflow: hidden !important;
        }

        :fullscreen .controls button,
        :-webkit-full-screen .controls button,
        :-moz-full-screen .controls button,
        :-ms-fullscreen .controls button,
        html.is-fullscreen .controls button,
        body.is-fullscreen .controls button,
        .is-fullscreen .controls button,
        .is-fullscreen #btnThemeToggle,
        .is-fullscreen #btnAnnounceRound,
        .is-fullscreen #btnTelaoVoiceToggle,
        .is-fullscreen .btn-fullscreen {
            display: none !important;
        }

        :fullscreen .controls,
        :-webkit-full-screen .controls,
        .is-fullscreen .controls {
            gap: 0 !important;
            margin-top: 0.1rem !important;
        }

        :fullscreen #liveClock,
        :-webkit-full-screen #liveClock,
        .is-fullscreen #liveClock {
            display: block !important;
            font-size: clamp(1.1rem, 2.3vh, 1.6rem) !important;
            padding: clamp(0.15rem, 0.3vh, 0.25rem) clamp(0.8rem, 1.2vw, 1.2rem) !important;
        }

        @media all and (display-mode: fullscreen) {
            html, body {
                height: 100vh !important;
                max-height: 100vh !important;
                overflow: hidden !important;
            }
            .controls button,
            #btnThemeToggle,
            #btnAnnounceRound,
            #btnTelaoVoiceToggle,
            .btn-fullscreen {
                display: none !important;
            }
            .controls {
                gap: 0 !important;
                margin-top: 0.1rem !important;
            }
            #liveClock {
                display: block !important;
                font-size: clamp(1.1rem, 2.3vh, 1.6rem) !important;
            }
        }

        /* Center Main Stage */
        .stage {
            text-align: center;
            padding: clamp(0.1rem, 0.4vh, 0.35rem) 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: clamp(0.2rem, 0.7vh, 0.55rem);
            flex: 1 1 auto;
            min-height: 0;
            justify-content: space-evenly;
            width: 100%;
        }

        /* Round indicator badge */
        .round-header-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(0.5rem, 1.2vw, 1.1rem);
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        .round-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.25), rgba(234, 179, 8, 0.35));
            border: 2px solid #f59e0b;
            padding: clamp(0.18rem, 0.45vh, 0.35rem) clamp(0.8rem, 1.4vw, 1.4rem);
            border-radius: 50px;
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.3);
        }
        .round-text {
            font-size: clamp(0.95rem, 2vh, 1.5rem);
            font-weight: 900;
            letter-spacing: 1.2px;
            color: #fef08a;
            text-transform: uppercase;
        }

        /* Card Color Badge */
        .card-color-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #fef08a;
            color: #854d0e;
            font-size: clamp(0.85rem, 1.8vh, 1.3rem);
            font-weight: 900;
            padding: clamp(0.18rem, 0.4vh, 0.32rem) clamp(0.7rem, 1.1vw, 1.2rem);
            border-radius: 40px;
            border: 2px solid #ca8a04;
            box-shadow: 0 0 20px rgba(254, 240, 138, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            animation: pulse 2.5s infinite ease-in-out;
        }

        /* Status banner */
        .status-tag {
            font-size: clamp(0.68rem, 1.25vh, 0.85rem);
            font-weight: 800;
            padding: clamp(0.12rem, 0.28vh, 0.22rem) clamp(0.6rem, 1vw, 1.1rem);
            border-radius: 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .status-open {
            background: rgba(34, 197, 94, 0.25);
            border: 2px solid #22c55e;
            color: #4ade80;
        }
        .status-closed {
            background: rgba(59, 130, 246, 0.25);
            border: 2px solid #3b82f6;
            color: #60a5fa;
        }

        /* Bingo 75-Ball Board on Telao */
        .bingo-board-container {
            width: 100%;
            max-width: 1300px;
            margin: 0.25rem auto;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(16px);
            border: 2px solid #334155;
            border-radius: clamp(12px, 1.8vh, 18px);
            padding: clamp(0.4rem, 1vh, 0.85rem) clamp(0.6rem, 1.2vw, 1.2rem);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            flex-shrink: 0;
        }
        .bingo-row {
            display: flex;
            align-items: center;
            gap: clamp(0.3rem, 0.8vw, 0.6rem);
            margin-bottom: clamp(0.15rem, 0.4vh, 0.35rem);
        }
        .bingo-letter-badge {
            width: clamp(28px, 4.5vh, 46px);
            height: clamp(28px, 4.5vh, 46px);
            border-radius: 8px;
            font-size: clamp(1.1rem, 2.2vh, 1.7rem);
            font-weight: 900;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        .letter-b { background: #dc2626; border: 2px solid #f87171; }
        .letter-i { background: #0284c7; border: 2px solid #38bdf8; }
        .letter-n { background: #16a34a; border: 2px solid #4ade80; }
        .letter-g { background: #d97706; border: 2px solid #fbbf24; }
        .letter-o { background: #7c3aed; border: 2px solid #a78bfa; }

        .bingo-slots-grid {
            display: grid;
            grid-template-columns: repeat(15, 1fr);
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            flex: 1;
        }
        .bingo-slot {
            aspect-ratio: 1/1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(0.75rem, 1.6vh, 1.15rem);
            font-weight: 900;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            color: #475569;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .bingo-slot.lit-b {
            background: radial-gradient(circle at 35% 35%, #ef4444, #991b1b);
            border-color: #fca5a5;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(239, 68, 68, 0.85);
            transform: scale(1.08);
        }
        .bingo-slot.lit-i {
            background: radial-gradient(circle at 35% 35%, #38bdf8, #0369a1);
            border-color: #bae6fd;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(56, 189, 248, 0.85);
            transform: scale(1.08);
        }
        .bingo-slot.lit-n {
            background: radial-gradient(circle at 35% 35%, #4ade80, #15803d);
            border-color: #bbf7d0;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(74, 222, 128, 0.85);
            transform: scale(1.08);
        }
        .bingo-slot.lit-g {
            background: radial-gradient(circle at 35% 35%, #fbbf24, #b45309);
            border-color: #fef08a;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(251, 191, 36, 0.85);
            transform: scale(1.08);
        }
        .bingo-slot.lit-o {
            background: radial-gradient(circle at 35% 35%, #c084fc, #6b21a8);
            border-color: #e9d5ff;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(192, 132, 252, 0.85);
            transform: scale(1.08);
        }

        /* Giant Ball Pop-Up Overlay with 5s Timer */
        #giantBallModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .giant-sphere {
            width: 320px;
            height: 320px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 0 80px rgba(255, 255, 255, 0.4), inset 0 -20px 40px rgba(0, 0, 0, 0.5), inset 0 20px 40px rgba(255, 255, 255, 0.6);
            border: 8px solid #ffffff;
            animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transition: all 0.8s ease-in-out;
        }
        .giant-sphere.flying {
            transform: scale(0.1) translateY(400px);
            opacity: 0;
        }
        .giant-letter {
            font-size: 4.5rem;
            font-weight: 900;
            line-height: 1;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 4px 15px rgba(0,0,0,0.6);
        }
        .giant-number {
            font-size: 8.5rem;
            font-weight: 900;
            line-height: 1;
            color: #ffffff;
            text-shadow: 0 6px 20px rgba(0,0,0,0.8);
        }
        .timer-progress-bar {
            width: 320px;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            margin-top: 1.5rem;
            overflow: hidden;
        }
        .timer-progress-fill {
            height: 100%;
            background: #eab308;
            width: 100%;
            transition: width 5s linear;
        }

        /* Banner de Conferência de BINGO */
        #conferenceBanner {
            display: none;
            width: 100%;
            background: linear-gradient(90deg, #dc2626, #b91c1c 50%, #dc2626);
            border: 4px solid #fef08a;
            color: #ffffff;
            padding: 1.25rem;
            text-align: center;
            border-radius: 20px;
            margin-bottom: 1rem;
            box-shadow: 0 0 50px rgba(239, 68, 68, 0.8);
            animation: alertGlow 1.2s infinite alternate;
        }
        .conference-title {
            font-size: 2.8rem;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-shadow: 0 3px 10px rgba(0,0,0,0.8);
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes alertGlow {
            from { box-shadow: 0 0 25px rgba(239, 68, 68, 0.6); transform: scale(0.99); }
            to { box-shadow: 0 0 60px rgba(254, 240, 138, 0.9); transform: scale(1.01); }
        }

        /* Prize Cards Grid */
        .prizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: clamp(0.4rem, 1.2vw, 1.2rem);
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            flex-shrink: 0;
        }
        .prize-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(14px);
            border-radius: clamp(12px, 1.8vh, 18px);
            padding: clamp(0.35rem, 1.1vh, 0.85rem) clamp(0.6rem, 1.2vw, 1.2rem);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.3s ease;
        }
        .card-1 {
            border: 3px solid #f59e0b;
            box-shadow: 0 8px 30px rgba(245, 158, 11, 0.3);
        }
        .card-2 {
            border: 3px solid #06b6d4;
            box-shadow: 0 8px 30px rgba(6, 182, 212, 0.3);
        }
        .card-3 {
            border: 3px solid #10b981;
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.3);
        }
        .card-ribbon {
            display: inline-block;
            font-size: clamp(0.75rem, 1.5vh, 1.1rem);
            font-weight: 900;
            padding: 0.12rem 0.75rem;
            border-radius: 16px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: clamp(0.12rem, 0.35vh, 0.35rem);
        }
        .ribbon-1 {
            background: #f59e0b;
            color: #000000;
        }
        .ribbon-2 {
            background: #06b6d4;
            color: #000000;
        }
        .ribbon-3 {
            background: #10b981;
            color: #000000;
        }
        .prize-title-text {
            font-size: clamp(0.95rem, 1.9vh, 1.45rem);
            font-weight: 900;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0.1rem 0;
            text-align: center;
            word-break: break-word;
            line-height: 1.15;
            text-shadow: 0 2px 8px rgba(0,0,0,0.6);
        }
        .prize-amount {
            font-size: clamp(1.8rem, 4vh, 3.2rem) !important;
            font-weight: 900;
            letter-spacing: 1px;
            font-variant-numeric: tabular-nums;
            margin-bottom: clamp(0.12rem, 0.35vh, 0.35rem);
            line-height: 1.1;
        }
        .amount-1 {
            color: #fef08a;
            text-shadow: 0 0 20px rgba(234, 179, 8, 0.6);
        }
        .amount-2 {
            color: #a5f3fc;
            text-shadow: 0 0 20px rgba(6, 182, 212, 0.6);
        }
        .amount-3 {
            color: #a7f3d0;
            text-shadow: 0 0 20px rgba(16, 185, 129, 0.6);
        }

        /* Winner and Seller Spotlight inside Prize Card */
        .person-badge {
            width: 100%;
            border-radius: 12px;
            padding: clamp(0.2rem, 0.5vh, 0.4rem) 0.6rem;
            margin-top: clamp(0.12rem, 0.35vh, 0.3rem);
            text-align: center;
        }
        .winner-box {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(217, 70, 239, 0.25));
            border: 2px solid #f472b6;
            animation: glowPink 2s infinite alternate;
        }
        .seller-box {
            background: rgba(30, 41, 59, 0.85);
            border: 2px solid #64748b;
        }
        .person-label {
            font-size: clamp(0.65rem, 1.15vh, 0.78rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #cbd5e1;
        }
        .person-val {
            font-size: clamp(0.85rem, 1.7vh, 1.3rem);
            font-weight: 900;
            color: #ffffff;
            margin-top: 0.1rem;
            word-break: break-word;
        }

        /* PIX Payment Banner */
        .pix-sales-banner {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: clamp(0.6rem, 1.5vw, 1.5rem);
            background: rgba(15, 23, 42, 0.92);
            border: 2px solid #0284c7;
            border-radius: clamp(12px, 1.8vh, 18px);
            padding: clamp(0.3rem, 0.85vh, 0.65rem) clamp(0.8rem, 1.5vw, 1.6rem);
            margin: clamp(0.12rem, 0.35vh, 0.4rem) auto;
            max-width: 820px;
            box-shadow: 0 8px 25px rgba(2, 132, 199, 0.35);
            flex-wrap: nowrap;
            width: 100%;
            flex-shrink: 0;
        }
        .pix-qr-wrapper {
            background: #ffffff;
            padding: clamp(0.15rem, 0.35vh, 0.35rem);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        #pixQrCodeImg {
            width: clamp(65px, 9.5vh, 100px);
            height: clamp(65px, 9.5vh, 100px);
            display: block;
            border-radius: 6px;
        }
        .pix-info-col {
            text-align: left;
            flex: 1;
            min-width: 0;
        }
        .pix-badge-title {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #0284c7;
            color: #ffffff;
            font-weight: 900;
            font-size: clamp(0.68rem, 1.25vh, 0.88rem);
            padding: clamp(0.12rem, 0.28vh, 0.22rem) clamp(0.55rem, 0.9vw, 0.85rem);
            border-radius: 20px;
            margin-bottom: clamp(0.08rem, 0.25vh, 0.25rem);
        }
        .pix-label-text {
            font-size: clamp(0.68rem, 1.15vh, 0.82rem);
            color: #94a3b8;
            font-weight: 600;
        }
        .pix-key-val {
            font-size: clamp(1rem, 2vh, 1.55rem);
            font-weight: 900;
            color: #38bdf8;
            letter-spacing: 0.5px;
            word-break: break-all;
            line-height: 1.15;
        }
        .pix-receiver-val {
            font-size: clamp(0.68rem, 1.15vh, 0.85rem);
            color: #cbd5e1;
            font-weight: 700;
            margin-top: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pix-desc-val {
            font-size: clamp(0.65rem, 1.05vh, 0.8rem);
            color: #fef08a;
            font-weight: 600;
            margin-top: 0.08rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Footer Ticker - ULTRA VISIBLE FOR ELDERLY PEOPLE */
        .footer-ticker-container {
            margin-top: clamp(0.15rem, 0.4vh, 0.35rem);
            width: 100%;
            flex-shrink: 0;
        }
        .price-hero-bar {
            background: linear-gradient(90deg, #111827, #1e293b 50%, #111827);
            border: 2px solid #eab308;
            border-radius: clamp(10px, 1.5vh, 16px);
            padding: clamp(0.25rem, 0.7vh, 0.55rem) clamp(0.8rem, 1.5vw, 1.6rem);
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: nowrap;
            gap: clamp(0.4rem, 1vw, 1.2rem);
            box-shadow: 0 0 25px rgba(234, 179, 8, 0.2);
        }
        .price-elderly-block {
            display: flex;
            align-items: center;
            gap: clamp(0.4rem, 0.8vw, 0.8rem);
        }
        .price-elderly-title {
            font-size: clamp(0.8rem, 1.6vh, 1.25rem);
            font-weight: 900;
            color: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .price-elderly-badge {
            background: #fbbf24;
            color: #000000;
            font-size: clamp(1.2rem, 2.7vh, 1.95rem);
            font-weight: 900;
            padding: clamp(0.08rem, 0.25vh, 0.22rem) clamp(0.6rem, 1vw, 1.1rem);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.6);
            letter-spacing: 1px;
            font-variant-numeric: tabular-nums;
            border: 2px solid #ffffff;
            white-space: nowrap;
        }
        .footer-luck-text {
            font-size: clamp(0.75rem, 1.4vh, 1.05rem);
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        @keyframes glowPink {
            from { box-shadow: 0 0 10px rgba(244, 114, 182, 0.3); }
            to { box-shadow: 0 0 25px rgba(244, 114, 182, 0.7); }
        }

        /* ========================================================= */
        /* LIGHT THEME (VISUAL CLARO PARA AMBIENTES ILUMINADOS / DIA) */
        /* ========================================================= */
        body.theme-light {
            background: radial-gradient(circle at top center, #f8fafc, #e2e8f0 85%);
            color: #0f172a;
        }
        body.theme-light .topbar {
            border-bottom: 2px solid #cbd5e1;
        }
        body.theme-light .brand-title {
            background: linear-gradient(135deg, #b45309, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        body.theme-light .brand-sub {
            color: #64748b;
        }
        body.theme-light .clock-badge {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #0284c7;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .btn-fullscreen {
            background: #ffffff;
            color: #0f172a;
            border-color: #cbd5e1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .btn-fullscreen:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        body.theme-light .round-pill {
            background: linear-gradient(90deg, #fef08a, #fde047);
            border: 3px solid #eab308;
            box-shadow: 0 0 25px rgba(234, 179, 8, 0.3);
        }
        body.theme-light .round-text {
            color: #713f12;
        }
        body.theme-light .card-color-badge {
            background: #fef9c3;
            color: #854d0e;
            border: 3px solid #ca8a04;
            box-shadow: 0 0 20px rgba(202, 138, 4, 0.25);
        }
        body.theme-light .bingo-board-container {
            background: rgba(255, 255, 255, 0.96);
            border-color: #cbd5e1;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.12);
        }
        body.theme-light .bingo-slot {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #64748b;
        }
        body.theme-light .bingo-slot.lit-b {
            background: radial-gradient(circle at 35% 35%, #ef4444, #991b1b);
            border-color: #f87171;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(239, 68, 68, 0.7);
        }
        body.theme-light .bingo-slot.lit-i {
            background: radial-gradient(circle at 35% 35%, #0284c7, #075985);
            border-color: #38bdf8;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(2, 132, 199, 0.7);
        }
        body.theme-light .bingo-slot.lit-n {
            background: radial-gradient(circle at 35% 35%, #16a34a, #14532d);
            border-color: #4ade80;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(22, 163, 74, 0.7);
        }
        body.theme-light .bingo-slot.lit-g {
            background: radial-gradient(circle at 35% 35%, #d97706, #78350f);
            border-color: #fbbf24;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(217, 119, 6, 0.7);
        }
        body.theme-light .bingo-slot.lit-o {
            background: radial-gradient(circle at 35% 35%, #7c3aed, #4c1d95);
            border-color: #a78bfa;
            color: #ffffff;
            box-shadow: 0 0 16px rgba(124, 58, 237, 0.7);
        }
        body.theme-light .prize-card {
            background: rgba(255, 255, 255, 0.95);
            border: 3px solid #cbd5e1;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }
        body.theme-light .card-p1 {
            border-color: #f59e0b;
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.2);
        }
        body.theme-light .card-p2 {
            border-color: #06b6d4;
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.2);
        }
        body.theme-light .card-p3 {
            border-color: #10b981;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
        }
        body.theme-light .prize-title-text {
            color: #0f172a;
            text-shadow: none;
        }
        body.theme-light .amount-1 {
            color: #b45309;
            text-shadow: 0 0 15px rgba(245, 158, 11, 0.3);
        }
        body.theme-light .amount-2 {
            color: #0891b2;
            text-shadow: 0 0 15px rgba(6, 182, 212, 0.3);
        }
        body.theme-light .amount-3 {
            color: #059669;
            text-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        body.theme-light .seller-box {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        body.theme-light .seller-box .person-label {
            color: #64748b;
        }
        body.theme-light .seller-box .person-val {
            color: #0f172a;
        }
        body.theme-light .winner-box {
            background: linear-gradient(135deg, rgba(244, 114, 182, 0.15), rgba(232, 121, 249, 0.2));
            border-color: #ec4899;
        }
        body.theme-light .winner-box .person-label {
            color: #be185d;
        }
        body.theme-light .winner-box .person-val {
            color: #831843;
        }
        body.theme-light .price-hero-bar {
            background: linear-gradient(90deg, #ffffff, #f1f5f9 50%, #ffffff);
            border-color: #eab308;
            box-shadow: 0 0 30px rgba(234, 179, 8, 0.2);
        }
        body.theme-light .price-elderly-title {
            color: #0f172a;
        }
        body.theme-light .price-elderly-badge {
            background: #fbbf24;
            color: #000000;
            border-color: #eab308;
        }
        body.theme-light #pixSalesBanner,
        body.theme-light .pix-sales-banner {
            background: rgba(255, 255, 255, 0.96) !important;
            border-color: #0284c7 !important;
            box-shadow: 0 8px 25px rgba(2, 132, 199, 0.15) !important;
        }
        body.theme-light #pixKeyDisplay,
        body.theme-light .pix-key-val {
            color: #0369a1 !important;
        }
        body.theme-light #pixReceiverDisplay,
        body.theme-light .pix-receiver-val {
            color: #334155 !important;
        }
        body.theme-light #pixDescriptionDisplay,
        body.theme-light .pix-desc-val {
            color: #854d0e !important;
        }

        @media (max-width: 600px), (max-height: 520px) {
            html, body {
                height: auto;
                max-height: none;
                overflow-y: auto !important;
            }
            .prizes-grid { grid-template-columns: 1fr; gap: 1rem; }
            .pix-sales-banner { flex-wrap: wrap; }
            .price-hero-bar { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

    <!-- Header / Topbar -->
    <header class="topbar">
        <div class="brand">
            <div class="brand-icon">🏆</div>
            <div style="flex: 1; min-width: 0; max-width: 100%;">
                <div class="brand-title" id="telaoBrandTitle"><?= View::e($systemTitle) ?></div>
                <div class="brand-sub">Painel Oficial &bull; Show de Prêmios</div>
            </div>
        </div>

        <div class="controls" id="telaoControls">
            <div class="clock-badge" id="liveClock">00:00:00</div>
            <button class="btn-fullscreen" id="btnThemeToggle" onclick="toggleTelaoTheme()" title="Alternar entre Visual Claro e Escuro (Atalho: Tecla T)">☀️ Visual Claro</button>
            <button class="btn-fullscreen" id="btnAnnounceRound" onclick="speakRoundAnnouncementOnTelao()" style="background: #f59e0b; color: #000; font-weight: 900; border-color: #ca8a04;" title="Narrar em voz animada a rodada, prêmios e regras">📢 Anunciar Rodada</button>
            <button class="btn-fullscreen" id="btnTelaoVoiceToggle" onclick="toggleTelaoVoice()" style="background: rgba(30, 41, 59, 0.9); color: #4ade80; border-color: #22c55e;" title="Ativar ou desativar narração animada no telão">🔊 Voz: LIGADA</button>
            <button class="btn-fullscreen" onclick="toggleFullScreen()">⛶ Tela Cheia</button>
        </div>
    </header>

    <!-- Center Main Stage -->
    <main class="stage">
        <!-- Conference Banner -->
        <div id="conferenceBanner">
            <div class="conference-title">🔔 ATENÇÃO: CARTELA EM CONFERÊNCIA! 🔔</div>
            <div style="font-size: 1.4rem; font-weight: 700; color: #fef08a; margin-top: 0.35rem;">Aguardem a verificação oficial dos números sorteados...</div>
        </div>

        <!-- Round Header & Card Color -->
        <div class="round-header-box">
            <div class="round-pill">
                <span style="font-size: 2.2rem;">🎯</span>
                <span class="round-text" id="roundTitleText"><?= View::e($roundName ?: ('RODADA ' . $roundNum)) ?></span>
            </div>

            <div class="card-color-badge" id="cardColorBadge">
                <span style="font-size: 1.8rem;">🎨</span>
                <span id="cardColorText">CARTELA <?= mb_strtoupper(View::e($cardColor)) ?></span>
            </div>
        </div>

        <!-- Status Tag Centralizada -->
        <div style="display: flex; justify-content: center; align-items: center; width: 100%; text-align: center;">
            <span class="status-tag <?= $roundStatus === 'OPEN' ? 'status-open' : 'status-closed' ?>" id="statusBadge">
                <?= $roundStatus === 'OPEN' ? '🟢 VENDAS ABERTAS' : ($roundStatus === 'CLOSED' ? '🏁 RODADA ENCERRADA' : '⚪ AGUARDANDO') ?>
            </span>
        </div>

        <!-- BINGO 75-BALL BOARD (Occupies screen during game play) -->
        <div class="bingo-board-container" id="bingoBoardSection" style="display: none;">
            <!-- Mini prizes header during game -->
            <div id="miniPrizesBar" style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; padding: 0.6rem 1rem; background: rgba(30, 41, 59, 0.85); border-radius: 12px; border: 2px solid #475569;">
                <div id="miniPrize1" style="font-size: 1.25rem; font-weight: 800; color: #fef08a;">🥇 1º Prêmio: <span id="miniPrize1Text">-</span> <span id="miniWinner1Text" style="color: #4ade80; font-weight: 900; margin-left: 0.5rem;"></span></div>
                <div id="miniPrize2" style="font-size: 1.25rem; font-weight: 800; color: #a5f3fc;">🥈 2º Prêmio: <span id="miniPrize2Text">-</span> <span id="miniWinner2Text" style="color: #4ade80; font-weight: 900; margin-left: 0.5rem;"></span></div>
                <div id="miniPrize3" style="font-size: 1.25rem; font-weight: 800; color: #a7f3d0;">🥉 3º Prêmio: <span id="miniPrize3Text">-</span> <span id="miniWinner3Text" style="color: #4ade80; font-weight: 900; margin-left: 0.5rem;"></span></div>
            </div>

            <!-- B (1-15) -->
            <div class="bingo-row">
                <div class="bingo-letter-badge letter-b">B</div>
                <div class="bingo-slots-grid">
                    <?php for ($n = 1; $n <= 15; $n++): ?>
                        <div class="bingo-slot" id="slot-<?= $n ?>" data-number="<?= $n ?>"><?= $n ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- I (16-30) -->
            <div class="bingo-row">
                <div class="bingo-letter-badge letter-i">I</div>
                <div class="bingo-slots-grid">
                    <?php for ($n = 16; $n <= 30; $n++): ?>
                        <div class="bingo-slot" id="slot-<?= $n ?>" data-number="<?= $n ?>"><?= $n ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- N (31-45) -->
            <div class="bingo-row">
                <div class="bingo-letter-badge letter-n">N</div>
                <div class="bingo-slots-grid">
                    <?php for ($n = 31; $n <= 45; $n++): ?>
                        <div class="bingo-slot" id="slot-<?= $n ?>" data-number="<?= $n ?>"><?= $n ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- G (46-60) -->
            <div class="bingo-row">
                <div class="bingo-letter-badge letter-g">G</div>
                <div class="bingo-slots-grid">
                    <?php for ($n = 46; $n <= 60; $n++): ?>
                        <div class="bingo-slot" id="slot-<?= $n ?>" data-number="<?= $n ?>"><?= $n ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- O (61-75) -->
            <div class="bingo-row">
                <div class="bingo-letter-badge letter-o">O</div>
                <div class="bingo-slots-grid">
                    <?php for ($n = 61; $n <= 75; $n++): ?>
                        <div class="bingo-slot" id="slot-<?= $n ?>" data-number="<?= $n ?>"><?= $n ?></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Prizes Grid (Default for sales info) -->
        <div class="prizes-grid" id="prizesGrid">
            <!-- 1º Prêmio -->
            <div class="prize-card card-1" id="prizeCard1">
                <div class="card-ribbon ribbon-1">🥇 1º Prêmio</div>
                <div class="prize-title-text" id="prize1Title" style="<?= empty($prize1Title) ? 'display: none;' : '' ?>"><?= View::e($prize1Title) ?></div>
                <div class="prize-amount amount-1" id="prize1Val" style="<?= (!empty($prize1Title) && $prize1 === 'R$ 0,00') ? 'display: none;' : '' ?>"><?= $prize1 ?></div>

                <div class="person-badge winner-box" id="winner1Box" style="<?= empty($winner1) ? 'display: none;' : '' ?>">
                    <div class="person-label">🎉 Ganhador(a) 1º Prêmio</div>
                    <div class="person-val" id="winner1Val"><?= View::e($winner1) ?></div>
                </div>

                <div class="person-badge seller-box" id="seller1Box" style="<?= empty($seller1) ? 'display: none;' : '' ?>">
                    <div class="person-label" style="color: #93c5fd;">🤝 Vendedor(a) da Cartela</div>
                    <div class="person-val" id="seller1Val"><?= View::e($seller1) ?></div>
                </div>
            </div>

            <!-- 2º Prêmio -->
            <div class="prize-card card-2" id="prizeCard2" style="<?= $prizesCount < 2 ? 'display: none;' : '' ?>">
                <div class="card-ribbon ribbon-2">🥈 2º Prêmio</div>
                <div class="prize-title-text" id="prize2Title" style="<?= empty($prize2Title) ? 'display: none;' : '' ?>"><?= View::e($prize2Title) ?></div>
                <div class="prize-amount amount-2" id="prize2Val" style="<?= (!empty($prize2Title) && $prize2 === 'R$ 0,00') ? 'display: none;' : '' ?>"><?= $prize2 ?></div>

                <div class="person-badge winner-box" id="winner2Box" style="<?= empty($winner2) ? 'display: none;' : '' ?>">
                    <div class="person-label">🎉 Ganhador(a) 2º Prêmio</div>
                    <div class="person-val" id="winner2Val"><?= View::e($winner2) ?></div>
                </div>

                <div class="person-badge seller-box" id="seller2Box" style="<?= empty($seller2) ? 'display: none;' : '' ?>">
                    <div class="person-label" style="color: #93c5fd;">🤝 Vendedor(a) da Cartela</div>
                    <div class="person-val" id="seller2Val"><?= View::e($seller2) ?></div>
                </div>
            </div>

            <!-- 3º Prêmio -->
            <div class="prize-card card-3" id="prizeCard3" style="<?= $prizesCount < 3 ? 'display: none;' : '' ?>">
                <div class="card-ribbon ribbon-3">🥉 3º Prêmio</div>
                <div class="prize-title-text" id="prize3Title" style="<?= empty($prize3Title) ? 'display: none;' : '' ?>"><?= View::e($prize3Title) ?></div>
                <div class="prize-amount amount-3" id="prize3Val" style="<?= (!empty($prize3Title) && $prize3 === 'R$ 0,00') ? 'display: none;' : '' ?>"><?= $prize3 ?></div>

                <div class="person-badge winner-box" id="winner3Box" style="<?= empty($winner3) ? 'display: none;' : '' ?>">
                    <div class="person-label">🎉 Ganhador(a) 3º Prêmio</div>
                    <div class="person-val" id="winner3Val"><?= View::e($winner3) ?></div>
                </div>

                <div class="person-badge seller-box" id="seller3Box" style="<?= empty($seller3) ? 'display: none;' : '' ?>">
                    <div class="person-label" style="color: #93c5fd;">🤝 Vendedor(a) da Cartela</div>
                    <div class="person-val" id="seller3Val"><?= View::e($seller3) ?></div>
                </div>
            </div>
        </div>

        <!-- PIX Payment Banner -->
        <div id="pixSalesBanner" class="pix-sales-banner" style="display: <?= $shouldShowPix ? 'flex' : 'none' ?>;">
            <div class="pix-qr-wrapper">
                <img id="pixQrCodeImg" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode(!empty($pixPayload) ? $pixPayload : $pixKeyVal) ?>" alt="QR Code PIX Banco Central">
            </div>
            <div class="pix-info-col">
                <div class="pix-badge-title">
                    ⚡ <span id="pixBannerTitleText"><?= View::e(!empty($pixBannerTitle) ? $pixBannerTitle : 'PAGUE COM PIX DIRETO DO SEU LUGAR') ?></span>
                </div>
                <div class="pix-label-text">Chave PIX Oficial:</div>
                <div id="pixKeyDisplay" class="pix-key-val">
                    <?= View::e($pixKeyVal) ?>
                </div>
                <div id="pixReceiverDisplay" class="pix-receiver-val">
                    Recebedor: <?= View::e(!empty($pixReceiver) ? $pixReceiver : 'Show de Prêmios Retiro') ?>
                </div>
                <div id="pixDescriptionDisplay" class="pix-desc-val" style="<?= empty($pixDescription) ? 'display: none;' : '' ?>">
                    Obs: <?= View::e($pixDescription ?? '') ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Giant Ball Pop-Up Overlay with 5s Timer -->
    <div id="giantBallModal">
        <div class="giant-sphere" id="giantSphere">
            <div class="giant-letter" id="giantLetter">B</div>
            <div class="giant-number" id="giantNumber">12</div>
        </div>
        <div class="timer-progress-bar">
            <div class="timer-progress-fill" id="timerProgressFill"></div>
        </div>
        <div style="font-size: 1.6rem; font-weight: 800; color: #f8fafc; margin-top: 1.25rem; text-shadow: 0 2px 10px rgba(0,0,0,0.8); letter-spacing: 1px;">
            🎱 PEDRA CANTADA!
        </div>
    </div>

    <!-- Footer Rules Ticker - HUGE VALUES FOR SENIORS / ELDERLY -->
    <footer class="footer-ticker-container">
        <div class="price-hero-bar">
            <div class="price-elderly-block">
                <div class="price-elderly-title">🎟️ 1 Cartela</div>
                <div class="price-elderly-badge" id="tickerSinglePrice"><?= $singlePrice ?></div>
            </div>

            <div class="price-elderly-block">
                <div class="price-elderly-title">🔥 Pacote c/ <span id="tickerBundleQty"><?= $bundleQty ?></span> Cartelas</div>
                <div class="price-elderly-badge" id="tickerBundlePrice"><?= $bundlePrice ?></div>
            </div>

            <div class="footer-luck-text">
                ✨ Boa sorte a todos!
            </div>
        </div>
    </footer>

    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('liveClock').textContent = `${h}:${m}:${s}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Fullscreen
        function toggleFullScreen() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                const el = document.documentElement;
                if (el.requestFullscreen) {
                    el.requestFullscreen().catch(err => console.log(err));
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
                document.body.classList.add('is-fullscreen');
                document.documentElement.classList.add('is-fullscreen');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
                document.body.classList.remove('is-fullscreen');
                document.documentElement.classList.remove('is-fullscreen');
            }
        }

        // Live Polling & Bingo State
        const statusUrl = '<?= View::url("telao/status") ?>';
        let currentLastCalled = null;
        let isShowingBall = false;

        function getLetterForNumber(n) {
            n = parseInt(n, 10);
            if (n >= 1 && n <= 15) return 'B';
            if (n >= 16 && n <= 30) return 'I';
            if (n >= 31 && n <= 45) return 'N';
            if (n >= 46 && n <= 60) return 'G';
            if (n >= 61 && n <= 75) return 'O';
            return '';
        }

        function getSphereGradient(letter) {
            switch(letter) {
                case 'B': return 'radial-gradient(circle at 35% 35%, #ef4444, #991b1b)';
                case 'I': return 'radial-gradient(circle at 35% 35%, #38bdf8, #0369a1)';
                case 'N': return 'radial-gradient(circle at 35% 35%, #4ade80, #15803d)';
                case 'G': return 'radial-gradient(circle at 35% 35%, #fbbf24, #b45309)';
                case 'O': return 'radial-gradient(circle at 35% 35%, #c084fc, #6b21a8)';
                default: return 'radial-gradient(circle at 35% 35%, #f59e0b, #d97706)';
            }
        }

        function playBallSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.3); // A5
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.5);
            } catch(e) {}
        }

        let telaoVoiceEnabled = true;
        let latestTelaoData = null;

        function toggleTelaoVoice() {
            telaoVoiceEnabled = !telaoVoiceEnabled;
            const btn = document.getElementById('btnTelaoVoiceToggle');
            if (btn) {
                if (telaoVoiceEnabled) {
                    btn.textContent = '🔊 Voz: LIGADA';
                    btn.style.color = '#4ade80';
                    btn.style.borderColor = '#22c55e';
                } else {
                    btn.textContent = '🔇 Voz: DESLIGADA';
                    btn.style.color = '#94a3b8';
                    btn.style.borderColor = '#64748b';
                    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
                }
            }
        }

        function formatMoneyText(valStr) {
            if (!valStr) return '';
            let cleaned = valStr.replace('R$', '').replace('.', '').replace(',', '.').trim();
            let val = parseFloat(cleaned) || 0;
            const reais = Math.floor(val);
            const centavos = Math.round((val - reais) * 100);
            let text = '';
            if (reais > 0) text += `${reais} ${reais === 1 ? 'real' : 'reais'}`;
            if (centavos > 0) {
                if (text) text += ' e ';
                text += `${centavos} ${centavos === 1 ? 'centavo' : 'centavos'}`;
            }
            return text || 'zero reais';
        }

        const telaoBingoPhrases = [
            (l, n) => `Atenção! Letra ${l}, número ${n}! Repetindo: ${l}, ${n}!`,
            (l, n) => `Olha a pedra! Letra ${l}, número ${n}! Letra ${l}, ${n}!`,
            (l, n) => `Saiu a pedra! Letra ${l}, número ${n}! Repetindo: ${l}, ${n}!`,
            (l, n) => `Atenção jogadores! Letra ${l}, número ${n}! ${l}, ${n}!`
        ];

        function speakBallOnTelao(letter, num) {
            if (!telaoVoiceEnabled || !('speechSynthesis' in window)) return;
            try {
                window.speechSynthesis.cancel();
                const phraseFn = telaoBingoPhrases[Math.floor(Math.random() * telaoBingoPhrases.length)];
                const utterance = new SpeechSynthesisUtterance(phraseFn(letter, num));
                utterance.lang = 'pt-BR';
                utterance.rate = 1.05;
                utterance.pitch = 1.15;
                const voices = window.speechSynthesis.getVoices();
                const brVoice = voices.find(v => (v.lang === 'pt-BR' || v.lang === 'pt_BR') && (v.name.includes('Google') || v.name.includes('Luciana') || v.name.includes('Natural') || v.name.includes('Daniel')));
                if (brVoice) utterance.voice = brVoice;
                window.speechSynthesis.speak(utterance);
            } catch(e) {}
        }

        function speakRoundAnnouncementOnTelao() {
            if (!('speechSynthesis' in window)) {
                alert('Navegador sem suporte a voz.');
                return;
            }
            const d = latestTelaoData;
            if (!d) return;

            const parts = [];
            parts.push("Atenção senhoras e senhores! Show de Prêmios!");
            const rTitle = d.round_name && d.round_name.trim() !== '' ? d.round_name : `Rodada ${d.round_number}`;
            parts.push(`Vamos com as informações da ${rTitle}!`);

            if (d.card_color && d.card_color.trim() !== '') {
                parts.push(`Atenção para a cor da cartela em jogo: Cartela ${d.card_color}!`);
            }

            parts.push("Premiações desta rodada:");
            if (d.prize_1_title && d.prize_1_title.trim() !== '') {
                const p1Amount = d.prize_1 && d.prize_1 !== 'R$ 0,00' ? ` no valor de ${formatMoneyText(d.prize_1)}` : '';
                parts.push(`Primeiro prêmio: ${d.prize_1_title}${p1Amount}!`);
            } else if (d.prize_1) {
                parts.push(`Primeiro prêmio: ${formatMoneyText(d.prize_1)} em dinheiro!`);
            }

            const pCount = parseInt(d.prizes_count, 10) || 2;
            if (pCount >= 2) {
                if (d.prize_2_title && d.prize_2_title.trim() !== '') {
                    const p2Amount = d.prize_2 && d.prize_2 !== 'R$ 0,00' ? ` no valor de ${formatMoneyText(d.prize_2)}` : '';
                    parts.push(`Segundo prêmio: ${d.prize_2_title}${p2Amount}!`);
                } else if (d.prize_2) {
                    parts.push(`Segundo prêmio: ${formatMoneyText(d.prize_2)} em dinheiro!`);
                }
            }

            if (pCount >= 3) {
                if (d.prize_3_title && d.prize_3_title.trim() !== '') {
                    const p3Amount = d.prize_3 && d.prize_3 !== 'R$ 0,00' ? ` no valor de ${formatMoneyText(d.prize_3)}` : '';
                    parts.push(`Terceiro prêmio: ${d.prize_3_title}${p3Amount}!`);
                } else if (d.prize_3) {
                    parts.push(`Terceiro prêmio: ${formatMoneyText(d.prize_3)} em dinheiro!`);
                }
            }

            if (d.single_price && d.bundle_price && d.bundle_qty) {
                parts.push(`Aproveitem para comprar suas cartelas com os vendedores! 1 cartela por ${formatMoneyText(d.single_price)}, e pacote de ${d.bundle_qty} cartelas por ${formatMoneyText(d.bundle_price)}!`);
            }
            parts.push("Boa sorte a todos os participantes!");

            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(parts.join(' '));
            utterance.lang = 'pt-BR';
            utterance.rate = 1.02;
            utterance.pitch = 1.1;
            const voices = window.speechSynthesis.getVoices();
            const brVoice = voices.find(v => (v.lang === 'pt-BR' || v.lang === 'pt_BR') && (v.name.includes('Google') || v.name.includes('Natural')));
            if (brVoice) utterance.voice = brVoice;
            window.speechSynthesis.speak(utterance);
        }

        function triggerGiantBallAnimation(num) {
            if (isShowingBall) return;
            isShowingBall = true;
            playBallSound();

            const letter = getLetterForNumber(num);
            speakBallOnTelao(letter, num);

            const modal = document.getElementById('giantBallModal');
            const sphere = document.getElementById('giantSphere');
            const letterEl = document.getElementById('giantLetter');
            const numberEl = document.getElementById('giantNumber');
            const fillEl = document.getElementById('timerProgressFill');

            letterEl.textContent = letter;
            numberEl.textContent = num;
            sphere.style.background = getSphereGradient(letter);
            sphere.classList.remove('flying');

            // Reset timer bar to full then shrink over 5 seconds
            fillEl.style.transition = 'none';
            fillEl.style.width = '100%';
            modal.style.display = 'flex';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    fillEl.style.transition = 'width 5000ms linear';
                    fillEl.style.width = '0%';
                });
            });

            // After 5 seconds, fly/shrink into board
            setTimeout(() => {
                sphere.classList.add('flying');
                setTimeout(() => {
                    modal.style.display = 'none';
                    sphere.classList.remove('flying');
                    isShowingBall = false;

                    // Light up slot on board
                    const slot = document.getElementById(`slot-${num}`);
                    if (slot) {
                        slot.className = `bingo-slot lit-${letter.toLowerCase()}`;
                    }
                }, 700);
            }, 5000);
        }

        async function fetchTelaoStatus() {
            try {
                const res = await fetch(statusUrl, { cache: 'no-store' });
                if (!res.ok) return;
                const json = await res.json();
                if (json && json.status === 'success' && json.data) {
                    const d = json.data;
                    latestTelaoData = d;

                    // Round title or number (SEMPRE VISÍVEL - NUNCA DESAPARECE)
                    const roundTitleEl = document.getElementById('roundTitleText');
                    const currentRoundTitle = roundTitleEl ? roundTitleEl.textContent.trim() : '';
                    let titleText = '';
                    if (d.round_name && d.round_name.trim() !== '') {
                        titleText = d.round_name.trim();
                    } else if (d.round_number) {
                        titleText = `RODADA ${d.round_number}`;
                    } else if (currentRoundTitle && currentRoundTitle !== 'RODADA') {
                        titleText = currentRoundTitle;
                    } else {
                        titleText = 'RODADA 1';
                    }
                    if (roundTitleEl) {
                        roundTitleEl.textContent = titleText;
                    }

                    // Card color (SEMPRE VISÍVEL - NUNCA DESAPARECE)
                    const colorBadge = document.getElementById('cardColorBadge');
                    const currentColorText = document.getElementById('cardColorText') ? document.getElementById('cardColorText').textContent.replace('CARTELA ', '').trim() : '';
                    const cardColorVal = (d.card_color && d.card_color.trim() !== '') ? d.card_color.trim() : (currentColorText || 'AMARELA');

                    document.getElementById('cardColorText').textContent = `CARTELA ${cardColorVal.toUpperCase()}`;
                    colorBadge.style.display = 'inline-flex';

                    // Cores temáticas no badge
                    const lower = cardColorVal.toLowerCase();
                    if (lower.includes('jornal')) {
                        colorBadge.style.background = '#e2e8f0';
                        colorBadge.style.color = '#1e293b';
                        colorBadge.style.borderColor = '#94a3b8';
                    } else if (lower.includes('azul')) {
                        colorBadge.style.background = '#bae6fd';
                        colorBadge.style.color = '#0369a1';
                        colorBadge.style.borderColor = '#38bdf8';
                    } else if (lower.includes('verde')) {
                        colorBadge.style.background = '#bbf7d0';
                        colorBadge.style.color = '#15803d';
                        colorBadge.style.borderColor = '#4ade80';
                    } else if (lower.includes('rosa')) {
                        colorBadge.style.background = '#fbcfe8';
                        colorBadge.style.color = '#be185d';
                        colorBadge.style.borderColor = '#f472b6';
                    } else if (lower.includes('branca')) {
                        colorBadge.style.background = '#ffffff';
                        colorBadge.style.color = '#0f172a';
                        colorBadge.style.borderColor = '#cbd5e1';
                    } else if (lower.includes('vermelh')) {
                        colorBadge.style.background = '#fecaca';
                        colorBadge.style.color = '#991b1b';
                        colorBadge.style.borderColor = '#f87171';
                    } else {
                        colorBadge.style.background = '#fef08a';
                        colorBadge.style.color = '#854d0e';
                        colorBadge.style.borderColor = '#ca8a04';
                    }

                    // Prizes display (titles & values)
                    updatePrizeDisplay('prize1Title', 'prize1Val', d.prize_1_title, d.prize_1);
                    updatePrizeDisplay('prize2Title', 'prize2Val', d.prize_2_title, d.prize_2);
                    updatePrizeDisplay('prize3Title', 'prize3Val', d.prize_3_title, d.prize_3);

                    // Mini prizes inside board
                    document.getElementById('miniPrize1Text').textContent = d.prize_1_title || d.prize_1 || '-';
                    document.getElementById('miniPrize2Text').textContent = d.prize_2_title || d.prize_2 || '-';
                    document.getElementById('miniPrize3Text').textContent = d.prize_3_title || d.prize_3 || '-';
                    document.getElementById('miniWinner1Text').textContent = d.winner_1_name ? `🏆 ${d.winner_1_name}` : '';
                    document.getElementById('miniWinner2Text').textContent = d.winner_2_name ? `🏆 ${d.winner_2_name}` : '';
                    document.getElementById('miniWinner3Text').textContent = d.winner_3_name ? `🏆 ${d.winner_3_name}` : '';

                    const prizesCount = parseInt(d.prizes_count, 10) || 2;
                    document.getElementById('miniPrize2').style.display = prizesCount >= 2 ? 'block' : 'none';
                    document.getElementById('miniPrize3').style.display = prizesCount >= 3 ? 'block' : 'none';

                    // Winner 1 & Seller 1
                    updatePersonBadge('winner1Box', 'winner1Val', d.winner_1_name);
                    updatePersonBadge('seller1Box', 'seller1Val', d.seller_1_name);

                    // Winner 2 & Seller 2
                    updatePersonBadge('winner2Box', 'winner2Val', d.winner_2_name);
                    updatePersonBadge('seller2Box', 'seller2Val', d.seller_2_name);

                    // Winner 3 & Seller 3
                    updatePersonBadge('winner3Box', 'winner3Val', d.winner_3_name);
                    updatePersonBadge('seller3Box', 'seller3Val', d.seller_3_name);

                    // Status & View Modes
                    const statusBadge = document.getElementById('statusBadge');
                    const confBanner = document.getElementById('conferenceBanner');
                    const bingoBoard = document.getElementById('bingoBoardSection');
                    const prizesGrid = document.getElementById('prizesGrid');

                    const isLiveGame = (d.round_status === 'IN_PROGRESS' || d.round_status === 'PAUSED' || d.round_status === 'CHECKING');

                    if (isLiveGame) {
                        bingoBoard.style.display = 'block';
                        prizesGrid.style.display = 'none';
                    } else {
                        bingoBoard.style.display = 'none';
                        prizesGrid.style.display = 'grid';
                    }

                    if (d.round_status === 'CHECKING') {
                        confBanner.style.display = 'block';
                        statusBadge.textContent = '🔔 EM CONFERÊNCIA';
                        statusBadge.className = 'status-tag status-closed';
                        triggerWinnerCelebration();
                    } else {
                        confBanner.style.display = 'none';
                        if (d.round_status === 'IN_PROGRESS') {
                            statusBadge.textContent = '🎤 SORTEIO EM ANDAMENTO';
                            statusBadge.className = 'status-tag status-open';
                        } else if (d.round_status === 'PAUSED') {
                            statusBadge.textContent = '⏸️ SORTEIO PAUSADO';
                            statusBadge.className = 'status-tag status-closed';
                        } else if (d.round_status === 'OPEN') {
                            statusBadge.textContent = '🟢 VENDAS ABERTAS';
                            statusBadge.className = 'status-tag status-open';
                        } else if (d.round_status === 'CLOSED') {
                            statusBadge.textContent = '🏁 RODADA ENCERRADA';
                            statusBadge.className = 'status-tag status-closed';
                        } else {
                            statusBadge.textContent = '⚪ AGUARDANDO';
                            statusBadge.className = 'status-tag';
                        }
                    }

                    // PIX Banner Toggle and Data Sync
                    const pixBanner = document.getElementById('pixSalesBanner');
                    if (pixBanner) {
                        const isPixEnabled = (d.pix_show_on_telao === true || d.pix_show_on_telao === 'true' || d.pix_show_on_telao === '1' || d.pix_show_on_telao === 1 || d.pix_show_on_telao === undefined);
                        const hasPixKey = Boolean(d.pix_key && d.pix_key.trim() !== '');
                        const isNotDrawing = (d.round_status !== 'IN_PROGRESS');
                        const shouldShowPix = (isPixEnabled && (hasPixKey || Boolean(d.pix_payload)) && isNotDrawing);

                        if (shouldShowPix) {
                            pixBanner.style.display = 'flex';
                            const keyVal = (d.pix_key && d.pix_key.trim() !== '') ? d.pix_key : 'mskpoeira@gmail.com';
                            const keyDisp = document.getElementById('pixKeyDisplay');
                            if (keyDisp) keyDisp.textContent = keyVal;

                            const qrImg = document.getElementById('pixQrCodeImg');
                            if (qrImg) {
                                const qrData = d.pix_payload || keyVal;
                                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrData)}`;
                            }
                            if (d.pix_receiver) {
                                const recDisp = document.getElementById('pixReceiverDisplay');
                                if (recDisp) recDisp.textContent = `Recebedor: ${d.pix_receiver}`;
                            }
                            const bannerTitleEl = document.getElementById('pixBannerTitleText');
                            if (bannerTitleEl && d.pix_banner_title) {
                                bannerTitleEl.textContent = d.pix_banner_title;
                            }
                            const descEl = document.getElementById('pixDescriptionDisplay');
                            if (descEl) {
                                if (d.pix_description && d.pix_description.trim() !== '') {
                                    descEl.textContent = `Obs: ${d.pix_description}`;
                                    descEl.style.display = 'block';
                                } else {
                                    descEl.style.display = 'none';
                                }
                            }
                        } else {
                            pixBanner.style.display = 'none';
                        }
                    }

                    // Synchronize Called Numbers on Board
                    const calledList = Array.isArray(d.called_numbers) ? d.called_numbers : [];
                    for (let n = 1; n <= 75; n++) {
                        const slot = document.getElementById(`slot-${n}`);
                        if (!slot) continue;
                        if (calledList.includes(n)) {
                            const letChar = getLetterForNumber(n).toLowerCase();
                            slot.className = `bingo-slot lit-${letChar}`;
                        } else {
                            slot.className = 'bingo-slot';
                        }
                    }

                    // Check for newly called ball
                    const lastBall = d.last_called_number ? parseInt(d.last_called_number, 10) : null;
                    if (lastBall && lastBall > 0 && currentLastCalled !== null && lastBall !== currentLastCalled) {
                        triggerGiantBallAnimation(lastBall);
                    }
                    currentLastCalled = lastBall;

                    // Branding & Prices for elderly
                    if (d.system_title) {
                        document.getElementById('telaoBrandTitle').textContent = d.system_title;
                        document.title = `${d.system_title} — Telão Oficial`;
                    }
                    if (d.single_price) {
                        document.getElementById('tickerSinglePrice').textContent = d.single_price;
                    }
                    if (d.bundle_qty && d.bundle_price) {
                        document.getElementById('tickerBundleQty').textContent = d.bundle_qty;
                        document.getElementById('tickerBundlePrice').textContent = d.bundle_price;
                    }
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }

        function updatePrizeDisplay(titleId, valId, title, amount) {
            const titleEl = document.getElementById(titleId);
            const valEl = document.getElementById(valId);
            if (!titleEl || !valEl) return;

            if (title && title.trim() !== '') {
                titleEl.textContent = title.trim();
                titleEl.style.display = 'block';
                if (amount && amount !== 'R$ 0,00' && amount !== '0,00') {
                    valEl.textContent = amount;
                    valEl.style.display = 'block';
                } else {
                    valEl.style.display = 'none';
                }
            } else {
                titleEl.style.display = 'none';
                valEl.textContent = amount || 'R$ 0,00';
                valEl.style.display = 'block';
            }
        }

        function updatePersonBadge(boxId, valId, value) {
            const box = document.getElementById(boxId);
            const valEl = document.getElementById(valId);
            if (!box || !valEl) return;
            if (value && value.trim() !== '') {
                valEl.textContent = value.trim();
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        }

        let isCelebrating = false;
        function triggerWinnerCelebration() {
            if (isCelebrating) return;
            isCelebrating = true;
            if (typeof confetti === 'function') {
                const duration = 5 * 1000;
                const animationEnd = Date.now() + duration;
                const defaults = { startVelocity: 35, spread: 360, ticks: 70, zIndex: 999999 };

                const interval = setInterval(function() {
                    const timeLeft = animationEnd - Date.now();
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        setTimeout(() => { isCelebrating = false; }, 4000);
                        return;
                    }
                    const particleCount = 60 * (timeLeft / duration);
                    confetti(Object.assign({}, defaults, { particleCount, origin: { x: Math.random() * 0.3 + 0.1, y: Math.random() - 0.2 } }));
                    confetti(Object.assign({}, defaults, { particleCount, origin: { x: Math.random() * 0.3 + 0.6, y: Math.random() - 0.2 } }));
                }, 350);
            } else {
                setTimeout(() => { isCelebrating = false; }, 5000);
            }
        }

        // Alternância de Tema Claro e Escuro
        function applyTelaoTheme(theme) {
            const btn = document.getElementById('btnThemeToggle');
            if (theme === 'light') {
                document.body.classList.add('theme-light');
                if (btn) btn.innerHTML = '🌙 Visual Escuro';
                localStorage.setItem('telao_theme', 'light');
            } else {
                document.body.classList.remove('theme-light');
                if (btn) btn.innerHTML = '☀️ Visual Claro';
                localStorage.setItem('telao_theme', 'dark');
            }
        }

        function toggleTelaoTheme() {
            const isLight = document.body.classList.contains('theme-light');
            applyTelaoTheme(isLight ? 'dark' : 'light');
        }

        // Inicializar tema salvo ou preferência
        applyTelaoTheme(localStorage.getItem('telao_theme') || 'dark');

        // Atalhos de teclado: T para Tema, F para Tela Cheia
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if (e.key === 't' || e.key === 'T') {
                toggleTelaoTheme();
            } else if (e.key === 'f' || e.key === 'F') {
                toggleFullScreen();
            }
        });

        // Detecção de Fullscreen para ocultar botões e deixar só o relógio
        function handleFullscreenChange() {
            const isFs = !!(
                document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.msFullscreenElement ||
                (window.innerHeight === screen.height && screen.height > 0) ||
                (window.matchMedia && window.matchMedia('(display-mode: fullscreen)').matches)
            );
            if (isFs) {
                document.body.classList.add('is-fullscreen');
                document.documentElement.classList.add('is-fullscreen');
            } else {
                document.body.classList.remove('is-fullscreen');
                document.documentElement.classList.remove('is-fullscreen');
            }
        }
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('mozfullscreenchange', handleFullscreenChange);
        document.addEventListener('MSFullscreenChange', handleFullscreenChange);
        window.addEventListener('resize', handleFullscreenChange);
        handleFullscreenChange();

        setInterval(fetchTelaoStatus, 2000);
    </script>
</body>
</html>

