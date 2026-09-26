<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use Framework\Router;

/** @var Router $router */

// ── Raiz ──────────────────────────────────────────────────────────────────────
$router->get('/', [HomeController::class, 'index'])->name('home');

// ── Autenticação (apenas visitantes) ─────────────────────────────────────────
$router->group(['prefix' => '/auth', 'middleware' => ['GuestMiddleware']], function (Router $r) {
    $r->get('/login',            [AuthController::class, 'loginForm'])->name('auth.login');
    $r->get('/register',         [AuthController::class, 'registerForm'])->name('auth.register');
    $r->get('/forgot-password',  [AuthController::class, 'forgotForm'])->name('auth.forgot');
    $r->get('/reset-password',   [AuthController::class, 'resetForm'])->name('auth.reset');

    // POST com CSRF + Rate Limit
    $r->post('/login',           [AuthController::class, 'login'],       ['CsrfMiddleware', 'RateLimitMiddleware:login']);
    $r->post('/register',        [AuthController::class, 'register'],    ['CsrfMiddleware', 'RateLimitMiddleware:register']);
    $r->post('/forgot-password', [AuthController::class, 'forgotSend'],  ['CsrfMiddleware', 'RateLimitMiddleware:forgot']);
    $r->post('/reset-password',  [AuthController::class, 'resetSave'],   ['CsrfMiddleware']);
});

$router->post('/auth/logout', [AuthController::class, 'logout'], ['CsrfMiddleware'])->name('auth.logout');

// ── Área protegida ────────────────────────────────────────────────────────────
$router->group(['prefix' => '/dashboard', 'middleware' => ['AuthMiddleware']], function (Router $r) {
    $r->get('', [DashboardController::class, 'index'])->name('dashboard');

    // ── Adicione suas rotas aqui ──────────────────────────────────────────────
    // $r->get('/posts',           [PostController::class, 'index'])->name('posts.index');
    // $r->post('/posts',          [PostController::class, 'store'],   ['CsrfMiddleware']);
    // $r->get('/posts/{id}',      [PostController::class, 'show'])->name('posts.show');
    // $r->put('/posts/{id}',      [PostController::class, 'update'],  ['CsrfMiddleware']);
    // $r->delete('/posts/{id}',   [PostController::class, 'destroy'], ['CsrfMiddleware']);
});

// ── API JSON ──────────────────────────────────────────────────────────────────
// A API REST própria (versionada, autenticada por Bearer Token) em
// routes/api.php — não neste arquivo. Ver docs/API.md.