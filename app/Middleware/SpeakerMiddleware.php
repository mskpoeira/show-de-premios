<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Controllers\SpeakerController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;

class SpeakerMiddleware
{
    public function handle(): void
    {
        if (Auth::check() && Auth::canOperateDraw()) {
            return;
        }

        $token = (string)($_GET['speaker_token'] ?? $_POST['speaker_token'] ?? $_GET['token'] ?? $_POST['token'] ?? ($_SERVER['HTTP_X_SPEAKER_TOKEN'] ?? Session::get('speaker_token', '')));
        $roundId = (int)($_GET['id'] ?? $_GET['round_id'] ?? $_POST['round_id'] ?? Session::get('speaker_round_id', 0));

        if ($roundId === 0 && $token !== '') {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("
                    SELECT r.id
                    FROM rounds r
                    JOIN operation_days d ON d.id = r.operation_day_id
                    WHERE d.status = 'OPEN'
                    ORDER BY CASE WHEN r.status = 'IN_PROGRESS' THEN 0 WHEN r.status = 'CHECKING' THEN 1 WHEN r.status = 'OPEN' THEN 2 ELSE 3 END,
                             r.round_number DESC
                    LIMIT 1
                ");
                $roundId = (int)($stmt->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $roundId = 0;
            }
        }

        if ($roundId > 0 && $token !== '' && SpeakerController::validateSpeakerToken($roundId, $token)) {
            Session::set('speaker_token', $token);
            Session::set('speaker_round_id', $roundId);
            return;
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            Response::redirect('/login', null, 'Acesso do locutor exige autenticação ou link seguro válido.');
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso do locutor não autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
