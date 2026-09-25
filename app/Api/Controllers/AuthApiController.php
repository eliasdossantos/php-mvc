<?php

namespace App\Api\Controllers;

use Core\Logger;
use Core\Api\ApiAuthContext;
use App\Requests\Auth\LoginRequest;
use App\Repositories\ApiTokenRepository;
use App\Api\Resources\UserResource;

/**
 * AuthApiController — POST /api/v1/auth/login, /logout, GET /me
 * ─────────────────────────────────────────────────────────────────────────────
 * Reaproveita App\Requests\Auth\LoginRequest (mesmas regras/mensagens do
 * login web) e App\Models\User::authenticate() — não duplica validação de
 * credencial. A diferença é só o resultado: em vez de sessão, emite um
 * Bearer Token.
 */
class AuthApiController extends ApiController
{
    public function login(): void
    {
        $data = $this->validated(new LoginRequest());

        $user = (new \App\Models\User())->authenticate($data['email'], $data['password']);

        if (!$user) {
            Logger::warning('API: login falhou', ['email' => $data['email']]);
            $this->error('E-mail ou senha incorretos.', [], 401);
        }

        if (empty($user->active)) {
            $this->error('Conta inativa. Entre em contato com o suporte.', [], 403);
        }

        $tokenRepo = new ApiTokenRepository();
        $issued    = $tokenRepo->issue((int) $user->id, 'api-login');

        Logger::info('API: login bem-sucedido', ['user_id' => $user->id]);

        $this->success([
            'token'      => $issued['token'],
            'token_type' => 'Bearer',
            'user'       => UserResource::make($user),
        ], 'Login realizado com sucesso.');
    }

    public function logout(): void
    {
        $token = ApiAuthContext::token();

        if ($token) {
            (new ApiTokenRepository())->revoke((int) $token->id);
            Logger::info('API: logout', ['user_id' => ApiAuthContext::id()]);
        }

        $this->success(null, 'Sessão encerrada.');
    }

    public function me(): void
    {
        $this->success(UserResource::make(ApiAuthContext::user()));
    }
}
