<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

class MasterMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        if (!Auth::isMaster()) {
            Response::redirect('/painel', null, 'Acesso restrito ao Administrador Master.');
        }
    }
}
