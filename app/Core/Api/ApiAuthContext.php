<?php

namespace Core\Api;

/**
 * ApiAuthContext — Usuário autenticado da requisição de API atual
 * ─────────────────────────────────────────────────────────────────────────────
 * A autenticação web (Core\Auth) é baseada em sessão/cookie — não serve para
 * a API, que é stateless e autentica por Bearer Token a CADA requisição.
 *
 * Este é um holder estático simples (válido apenas durante o processo/
 * requisição atual — nada é persistido aqui), preenchido pelo
 * App\Middlewares\ApiAuthMiddleware após validar o token.
 *
 * Uso num Controller de API:
 *   $user = ApiAuthContext::user();
 *   $id      = ApiAuthContext::id();
 */
class ApiAuthContext
{
    protected static ?object $user  = null;
    protected static ?object $token = null;

    public static function set(object $user, object $tokenRecord): void
    {
        static::$user  = $user;
        static::$token = $tokenRecord;
    }

    public static function check(): bool
    {
        return static::$user !== null;
    }

    public static function user(): ?object
    {
        return static::$user;
    }

    public static function id(): ?int
    {
        return static::$user !== null ? (int) static::$user->id : null;
    }

    public static function token(): ?object
    {
        return static::$token;
    }

    public static function clear(): void
    {
        static::$user  = null;
        static::$token = null;
    }
}
