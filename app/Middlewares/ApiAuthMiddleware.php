<?php

namespace App\Middlewares;

use Framework\Request;
use Framework\Api\ApiResponse;
use Framework\Api\ApiAuthContext;
use App\Repositories\ApiTokenRepository;
use App\Repositories\UserRepository;

/**
 * ApiAuthMiddleware — Autenticação Bearer Token para a API (/api/v1)
 * ─────────────────────────────────────────────────────────────────────────────
 * Não reutiliza o AuthMiddleware da Web (baseado em sessão) — a API é
 * stateless por natureza: o cliente (mobile, outro sistema) manda o token em
 * TODA requisição via "Authorization: Bearer <token>", sem depender de
 * cookies.
 *
 * Uso nas rotas:
 *   $r->get('/users', [UserApiController::class, 'index'], ['ApiAuthMiddleware']);
 */
class ApiAuthMiddleware
{
    public function handle(Request $request): void
    {
        $header = $request->header('Authorization');

        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            ApiResponse::error('Não autenticado. Envie o header Authorization: Bearer <token>.', [], 401);
        }

        $plainToken = trim(substr($header, 7));
        if ($plainToken === '') {
            ApiResponse::error('Token não informado.', [], 401);
        }

        $tokenRepo = new ApiTokenRepository();
        $tokenRecord = $tokenRepo->findValidByPlainToken($plainToken);

        if (!$tokenRecord) {
            ApiResponse::error('Token inválido ou expirado.', [], 401);
        }

        $user = (new UserRepository())->findById((int) $tokenRecord->user_id);

        if (!$user || empty($user->active)) {
            ApiResponse::error('Usuário inválido ou inactive.', [], 401);
        }

        ApiAuthContext::set($user, $tokenRecord);
        $tokenRepo->touchLastUsed((int) $tokenRecord->id);
    }
}
