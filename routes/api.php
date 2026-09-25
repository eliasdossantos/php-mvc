<?php

use App\Api\Controllers\AuthApiController;
use App\Api\Controllers\UserApiController;
use App\Api\Controllers\PreflightController;
use Core\Router;

/** @var Router $router */

// ── API v1 — referência completa em docs/index.html ─────────────────────────
// Todo endpoint aqui vive sob /api/v1 e é completamente separado das rotas
// web em routes/web.php — mesma Service/Repository/Model por baixo, mas
// interface própria (JSON, Bearer Token, sem CSRF de sessão, sem views).
//
// CorsMiddleware e RateLimitMiddleware:api rodam para TODA rota do grupo.
$router->group(['prefix' => '/api/v1', 'middleware' => ['CorsMiddleware', 'RateLimitMiddleware:api']], function (Router $r) {

    // ── Preflight CORS ──────────────────────────────────────────────────────
    // O navegador manda OPTIONS antes de POST/PUT/DELETE e antes de qualquer
    // requisição com header Authorization. CorsMiddleware já responde e
    // encerra — PreflightController::handle() nunca chega a rodar de verdade.
    $r->options('/auth/login',   [PreflightController::class, 'handle']);
    $r->options('/auth/logout',  [PreflightController::class, 'handle']);
    $r->options('/auth/me',      [PreflightController::class, 'handle']);
    $r->options('/users',      [PreflightController::class, 'handle']);
    $r->options('/users/{id}', [PreflightController::class, 'handle']);

    // ── Autenticação (Bearer Token) ──────────────────────────────────────────
    $r->post('/auth/login',  [AuthApiController::class, 'login'], ['RateLimitMiddleware:login']);
    $r->post('/auth/logout', [AuthApiController::class, 'logout'], ['ApiAuthMiddleware']);
    $r->get('/auth/me',      [AuthApiController::class, 'me'], ['ApiAuthMiddleware']);

    // ── Usuários (exemplo de recurso REST completo) ──────────────────────────
    $r->get('/users',      [UserApiController::class, 'index'], ['ApiAuthMiddleware']);
    $r->get('/users/{id}', [UserApiController::class, 'show'],  ['ApiAuthMiddleware']);

    // ── Adicione novos recursos da API aqui ───────────────────────────────────
    // $r->get('/produtos',         [ProdutoApiController::class, 'index'],   ['ApiAuthMiddleware']);
    // $r->post('/produtos',        [ProdutoApiController::class, 'store'],   ['ApiAuthMiddleware']);
    // $r->get('/produtos/{id}',    [ProdutoApiController::class, 'show'],    ['ApiAuthMiddleware']);
    // $r->put('/produtos/{id}',    [ProdutoApiController::class, 'update'],  ['ApiAuthMiddleware']);
    // $r->delete('/produtos/{id}', [ProdutoApiController::class, 'destroy'], ['ApiAuthMiddleware']);
});

// ── API v2 (futuro) ────────────────────────────────────────────────────────────
// Quando existir uma v2, crie routes/api_v2.php com:
//   $router->group(['prefix' => '/api/v2', 'middleware' => [...]], function (Router $r) { ... });
// Core\Application já carrega automaticamente qualquer routes/api*.php —
// nenhuma alteração no Core é necessária.
