<?php

namespace App\Api\Controllers;

/**
 * PreflightController — Alvo das rotas OPTIONS da API
 * ─────────────────────────────────────────────────────────────────────────────
 * Nunca chega a executar de fato: CorsMiddleware intercepta toda requisição
 * OPTIONS e responde 204 antes do Controller ser instanciado. Existe só para
 * dar ao Router uma action válida ao registrar as rotas OPTIONS de preflight.
 */
class PreflightController extends ApiController
{
    public function handle(): void
    {
        http_response_code(204);
    }
}
