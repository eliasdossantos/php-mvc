<?php

namespace App\Http\Middlewares;

use Framework\Http\Request;
use Framework\Auth\Auth;
use Framework\Support\Session;

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