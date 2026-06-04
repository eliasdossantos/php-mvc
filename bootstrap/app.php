<?php

/**
 * Bootstrap da Aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Este arquivo é o ponto de inicialização do framework.
 * Define constantes, carrega o autoload, configura o ambiente e inicializa
 * todos os componentes necessários antes de processar a requisição.
 *
 * Fluxo:
 *   public/index.php → bootstrap/app.php → Application::run()
 */

// ── 1. Constantes de caminhos ────────────────────────────────────────────────

define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . '/app');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('VIEW_PATH',    APP_PATH  . '/Views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('ROUTES_PATH',  ROOT_PATH . '/routes');

// ── 2. Autoload Composer (PSR-4 + helpers globais) ───────────────────────────

$autoload = ROOT_PATH . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    http_response_code(500);
    die('Dependências não instaladas. Execute: <code>composer install</code>');
}

require $autoload;

// ── 3. Variáveis de ambiente (.env) ──────────────────────────────────────────

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad(); // safeLoad não lança exceção se .env não existir

// ── 4. Configuração da aplicação ──────────────────────────────────────────────

require CONFIG_PATH . '/app.php';

// ── 5. Sessão segura ──────────────────────────────────────────────────────────

\Core\Session::start();

// ── BUG CORRIGIDO #9 (continuação) ───────────────────────────────────────────
// Descarta old_input que foi lido na requisição anterior (mecanismo de aging).
// Deve ser chamado APÓS session_start() e ANTES de qualquer leitura de oldInput().
\Core\Session::ageOldInput();

// ── 6. Instancia e retorna a Application ─────────────────────────────────────

return new \Core\Application();