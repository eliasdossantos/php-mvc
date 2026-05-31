<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * GuestMiddleware — Redireciona usuários já logados (ex: página de login)
 */
class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (Session::has('user_id')) {
            redirect('app/dashboard');
        }
    }
}
