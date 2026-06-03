<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * GuestMiddleware — Redireciona usuários já logados (ex: página de login)
 *
 * ── BUG CORRIGIDO #11 ────────────────────────────────────────────────────────
 * Antes: redirecionava para 'app/dashboard', que NÃO existe nas rotas definidas
 * em routes/web.php. A rota protegida está registrada como '/dashboard' (sem
 * o prefixo 'app/'), então o redirect gerava um 404 após o login bem-sucedido
 * quando o usuário tentava acessar /auth/login estando já autenticado.
 *
 * Solução: corrigir o destino para 'dashboard', que corresponde à rota
 * registrada como $r->get('', [DashboardController::class, 'index']) dentro
 * do grupo com prefix '/dashboard'.
 */
class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (Session::has('user_id')) {
            redirect('dashboard');
        }
    }
}
