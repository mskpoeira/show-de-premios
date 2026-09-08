<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

class OperatorMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        if (!Auth::isOperator()) {
            Response::redirect('/painel', null, 'Acesso restrito para edição. Seu perfil é apenas de consulta.');
        }
    }
}
