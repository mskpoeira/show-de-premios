<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Controllers\SpeakerController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;

class SpeakerMiddleware
{
    public function handle(): void
    {
        // 1. Usuário logado com permissão de operador/admin
        if (Auth::check() && Auth::isOperator()) {
            return;
        }

        // 2. Acesso via link direto no celular com Token
        $token = $_GET['token'] ?? $_POST['token'] ?? ($_SESSION['speaker_token'] ?? '');
        $roundId = (int)($_GET['id'] ?? $_GET['round_id'] ?? $_POST['round_id'] ?? ($_SESSION['speaker_round_id'] ?? 0));

        // Se roundId não veio explicitamente, busca a rodada mais relevante
        if ($roundId === 0 && !empty($token)) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("
                    SELECT r.id, d.operation_date 
                    FROM rounds r 
                    JOIN operation_days d ON d.id = r.operation_day_id 
                    WHERE d.status = 'OPEN' 
                    ORDER BY 
                        CASE WHEN r.status = 'IN_PROGRESS' THEN 0 WHEN r.status = 'CHECKING' THEN 1 ELSE 2 END,
                        r.round_number DESC 
                    LIMIT 1
                ");
                $r = $stmt->fetch();
                if ($r) {
                    $roundId = (int)$r['id'];
                }
            } catch (\Throwable $e) {}
        }

        if (!empty($token) && $roundId > 0) {
            if (SpeakerController::validateSpeakerToken($roundId, (string)$token)) {
                $_SESSION['speaker_token'] = $token;
                $_SESSION['speaker_round_id'] = $roundId;
                return;
            }
        }

        // 3. Bloqueio com redirecionamento amigável
        if (!Auth::check()) {
            Response::redirect('/login', null, 'Por favor, faça login ou use o link direto do locutor gerado para o celular.');
        }

        Response::redirect('/painel', null, 'Acesso restrito ao módulo de locutor.');
    }
}
