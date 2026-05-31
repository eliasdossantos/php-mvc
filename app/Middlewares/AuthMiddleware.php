<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * AuthMiddleware — Protege rotas que requerem login
 */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Session::has('user_id')) {
            Session::flash('error', 'Você precisa estar logado para acessar esta página.');
            redirect('auth/login');
        }
    }
}
