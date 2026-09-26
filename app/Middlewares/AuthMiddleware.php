<?php

namespace App\Middlewares;

use Framework\Request;
use Framework\Auth;
use Framework\Session;

/**
 * AuthMiddleware — Protege rotas que requerem login
 */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Você precisa estar logado para acessar esta página.');
            redirect('auth/login');
        }
    }
}