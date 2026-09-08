<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>🎤 Locutor — Aguardando Rodada</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            text-align: center;
        }
        .waiting-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 2.5rem 1.75rem;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .mic-icon {
            font-size: 3.8rem;
            display: inline-block;
            margin-bottom: 1rem;
            animation: pulse-mic 2s infinite ease-in-out;
        }
        @keyframes pulse-mic {
            0%, 100% { transform: scale(1); filter: drop-shadow(0 0 10px rgba(168, 85, 247, 0.4)); }
            50% { transform: scale(1.1); filter: drop-shadow(0 0 25px rgba(168, 85, 247, 0.8)); }
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #334155;
            border-top-color: #a855f7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 1.5rem auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="waiting-card">
        <div class="mic-icon">🎤</div>
        <h1 style="font-size: 1.6rem; font-weight: 800; margin: 0 0 0.5rem 0; color: #fff;">
            Módulo do Locutor
        </h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Nenhuma rodada aberta no momento.<br>
            Aguardando a abertura da rodada pelo operador...
        </p>

        <div class="spinner"></div>

        <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem;">
            Esta tela conectará automaticamente assim que a rodada for iniciada.
        </p>

        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <button onclick="window.location.reload()" style="background: #a855f7; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; cursor: pointer;">
                🔄 Verificar Agora
            </button>
            <a href="/login" style="background: #334155; color: #cbd5e1; text-decoration: none; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem;">
                🔑 Entrar como Operador
            </a>
        </div>
    </div>

    <script>
        // Checagem automática a cada 3 segundos
        setInterval(() => {
            fetch(window.location.href, { method: 'GET', cache: 'no-store' })
                .then(r => {
                    if (r.ok && !r.redirected) {
                        return r.text();
                    }
                })
                .then(html => {
                    if (html && !html.includes('waiting-card')) {
                        window.location.reload();
                    }
                })
                .catch(() => {});
        }, 3000);
    </script>
</body>
</html>
