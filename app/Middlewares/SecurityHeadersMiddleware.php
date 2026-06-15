<?php

namespace App\Middlewares;

use Core\Request;

/**
 * SecurityHeadersMiddleware — Headers de segurança HTTP reforçados
 * ─────────────────────────────────────────────────────────────────────────────
 * Complementa os headers básicos já definidos em Application::setSecurityHeaders().
 * Adiciona Content-Security-Policy, Permissions-Policy e HSTS.
 *
 * Uso nas rotas (aplique em grupos protegidos ou globalmente no bootstrap):
 *   $router->group(['middleware' => ['SecurityHeadersMiddleware']], function ($r) { ... });
 *
 * Para aplicar globalmente, chame em bootstrap/app.php antes de Application::run():
 *   (new \App\Middlewares\SecurityHeadersMiddleware())->handle(new \Core\Request());
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request): void
    {
        if (headers_sent()) return;

        // ── Content-Security-Policy ───────────────────────────────────────────
        // Ajuste as diretivas conforme as CDNs e scripts externos que você usa.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",   // remova 'unsafe-inline' se usar nonces
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",               // substitui X-Frame-Options
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
        ]);
        header("Content-Security-Policy: {$csp}");

        // ── HSTS — apenas em produção com HTTPS ───────────────────────────────
        if (
            defined('APP_ENV') && APP_ENV === 'production' &&
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // ── Permissions-Policy ────────────────────────────────────────────────
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        // ── Remove header que expõe tecnologia ────────────────────────────────
        header_remove('X-Powered-By');
    }
}
