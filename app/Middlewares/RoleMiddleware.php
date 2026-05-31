<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;
use Core\Auth;

/**
 * RoleMiddleware — Verifica a role (perfil) do usuário logado
 *
 * Uso:
 *   $router->get('/admin', [AdminController::class, 'index'], ['AuthMiddleware', 'RoleMiddleware:admin']);
 *
 * O parâmetro após ":" define a role exigida. Default: admin.
 */
class RoleMiddleware
{
    public function handle(Request $request, string $role = 'admin'): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Faça login para continuar.');
            redirect('auth/login');
        }

        if (!Auth::is($role)) {
            if ($request->isAjax() || $request->isJson()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
                exit;
            }

            Session::flash('error', 'Você não tem permissão para acessar esta área.');
            http_response_code(403);
            redirect('app/dashboard');
        }
    }
}
