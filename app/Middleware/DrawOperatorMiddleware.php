<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

class DrawOperatorMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        if (!Auth::canOperateDraw()) {
            Response::redirect('/painel', null, 'Seu perfil é somente leitura e não pode operar o sorteio.');
        }
    }
}
