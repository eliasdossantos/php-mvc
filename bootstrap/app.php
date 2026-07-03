<?php

/**
 * Bootstrap da Aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Ponto de inicialização do framework.
 * Fluxo: public/index.php → bootstrap/app.php → Application::run()
 */

// ── 1. Constantes de caminhos ────────────────────────────────────────────────

define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . '/app');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('VIEW_PATH',    APP_PATH  . '/Views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('ROUTES_PATH',  ROOT_PATH . '/routes');

// ── 2. Autoload Composer ──────────────────────────────────────────────────────

$autoload = ROOT_PATH . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    http_response_code(500);
    die('Dependências não instaladas. Execute: <code>composer install</code>');
}

require $autoload;

// ── 3. Alias global para uso direto de View::... ─────────────────────────────────────
// Nas views, sem precisar de "use Core\View;"
class_alias(\Core\View::class, 'View');

// ── 4. Variáveis de ambiente (.env) ──────────────────────────────────────────

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

// ── 5. Configuração da aplicação ──────────────────────────────────────────────

require CONFIG_PATH . '/app.php';

// ── 6. Sessão + old input aging ───────────────────────────────────────────────

\Core\Session::start();
\Core\Session::ageOldInput();

// ── 7. Recuperação via cookie "lembrar de mim" ───────────────────────────────
// Tenta re-autenticar silenciosamente via cookie se a sessão estiver vazia.
// Isso acontece quando o usuário fecha e reabre o navegador mas tinha marcado
// "lembrar de mim". Só executa se houver o cookie e nenhuma sessão ativa.
if (!empty($_COOKIE['remember_me'])) {
    \Core\Auth::recoverFromCookie();
}

// ── 8. Limpeza periódica de cache de rate limit (1% das requisições) ─────────
// Garante que arquivos de rate limit expirados não acumulem indefinidamente.
if (rand(1, 100) === 1) {
    $rlDir = STORAGE_PATH . '/cache/ratelimit';
    if (is_dir($rlDir)) {
        foreach (glob($rlDir . '/*.json') as $file) {
            $data = @json_decode(@file_get_contents($file), true);
            if (!$data || (isset($data['expires']) && $data['expires'] < time())) {
                @unlink($file);
            }
        }
    }
}

// ── 9. Instancia e retorna a Application ─────────────────────────────────────

return new \Core\Application();
