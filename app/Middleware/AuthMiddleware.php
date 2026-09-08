<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login', null, 'Por favor, faça login para acessar esta área.');
        }
    }
}
