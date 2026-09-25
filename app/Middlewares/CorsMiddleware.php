<?php

namespace App\Middlewares;

use Core\Request;

/**
 * CorsMiddleware — Cross-Origin Resource Sharing para a API (/api/v1)
 * ─────────────────────────────────────────────────────────────────────────────
 * Permite que a API seja consumida por origens diferentes (app Flutter/React
 * Native embarcado num WebView, front-end SPA separado, outros sistemas),
 * sem liberar "Access-Control-Allow-Origin: *" indiscriminadamente em
 * endpoints autenticados.
 *
 * Configuração via config/api.php (que lê CORS_ALLOWED_ORIGINS do .env):
 *   CORS_ALLOWED_ORIGINS=https://app.meusite.com,https://admin.meusite.com
 *   CORS_ALLOWED_ORIGINS=*   (apenas para desenvolvimento)
 *
 * Requisições de pré-voo (OPTIONS) são respondidas aqui mesmo, com 204 e
 * sem chegar ao Controller da rota.
 */
class CorsMiddleware
{
    public function handle(Request $request): void
    {
        if (headers_sent()) return;

        $config  = require CONFIG_PATH . '/api.php';
        $origin  = $request->header('Origin');
        $allowed = $config['cors']['allowed_origins'] ?? [];

        if ($origin !== '' && $this->originAllowed($origin, $allowed)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            // Wildcard não recebe credenciais; em produção wildcard é proibido.
            if (!in_array('*', $allowed, true)) {
                header('Access-Control-Allow-Credentials: true');
            }
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $config['cors']['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $config['cors']['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-CSRF-Token']));
        header('Access-Control-Max-Age: 86400');

        if ($request->method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    protected function originAllowed(string $origin, array $allowed): bool
    {
        if (in_array('*', $allowed, true)) {
            return ($_ENV['APP_ENV'] ?? (defined('APP_ENV') ? APP_ENV : '')) !== 'production';
        }
        return in_array($origin, $allowed, true);
    }
}
