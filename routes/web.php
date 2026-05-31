<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use Core\Router;

/** @var Router $router */

// ── Raiz ──────────────────────────────────────────────────────────────────────
$router->get('/', [HomeController::class, 'index'])->name('home');

// ── Autenticação (apenas visitantes) ─────────────────────────────────────────
$router->group(['prefix' => '/auth', 'middleware' => ['GuestMiddleware']], function (Router $r) {
    $r->get('/login',            [AuthController::class, 'loginForm'])->name('auth.login');
    $r->post('/login',           [AuthController::class, 'login'],        ['CsrfMiddleware']);
    $r->get('/register',         [AuthController::class, 'registerForm'])->name('auth.register');
    $r->post('/register',        [AuthController::class, 'register'],     ['CsrfMiddleware']);
    $r->get('/forgot-password',  [AuthController::class, 'forgotForm'])->name('auth.forgot');
    $r->post('/forgot-password', [AuthController::class, 'forgotSend'],   ['CsrfMiddleware']);
    $r->get('/reset-password',   [AuthController::class, 'resetForm'])->name('auth.reset');
    $r->post('/reset-password',  [AuthController::class, 'resetSave'],    ['CsrfMiddleware']);
});

$router->get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

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
$router->group(['prefix' => '/api', 'middleware' => ['AuthMiddleware']], function (Router $r) {
    // $r->get('/users',  [UserApiController::class, 'index']);
    // $r->post('/users', [UserApiController::class, 'store']);
});
