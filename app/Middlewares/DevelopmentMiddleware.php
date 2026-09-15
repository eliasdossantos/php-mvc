<?php

namespace App\Middlewares;

use Core\Request;

/**
 * DevelopmentMiddleware — Permite acesso a rotas exclusivas
 * do ambiente de desenvolvimento.
 *
 * Uso:
 *   $router->group([
 *       'prefix' => '/dev',
 *       'middleware' => ['DevelopmentMiddleware']
 *   ], function (Router $r) {
 *       // Rotas de desenvolvimento
 *   });
 *
 * O acesso é permitido somente quando APP_ENV=local.
 */
class DevelopmentMiddleware
{
    public function handle(Request $request): void
    {
        if (!defined('APP_ENV') || APP_ENV !== 'local') {
            http_response_code(404);

            echo 'Página não encontrada.';
            exit;
        }
    }
}
