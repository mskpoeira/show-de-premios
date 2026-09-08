<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

class AdminMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        if (!Auth::isAdmin()) {
            Response::redirect('/painel', null, 'Acesso restrito: você não tem permissão de administrador.');
        }
    }
}
