<?php

namespace Core;

/**
 * Session — Gerenciador de Sessões Seguras
 * ─────────────────────────────────────────────────────────────────────────────
 * Encapsula o gerenciamento de sessões PHP com:
 *  - Configurações seguras (httpOnly, SameSite, path personalizado)
 *  - Regeneração periódica de ID (proteção contra session fixation)
 *  - Flash messages (existem por apenas uma requisição)
 *  - Old input (repopulação de formulários após validação)
 *  - CSRF token integrado
 *
 * Uso:
 *   Session::start();
 *   Session::set('user_id', 42);
 *   Session::get('user_id');          // 42
 *   Session::flash('success', 'OK!');
 *   Session::getFlash('success');     // 'OK!' (e remove)
 */
class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        // Armazena sessões fora do public/
        $sessionPath = STORAGE_PATH . '/sessions';

        // ── MELHORIA #1 ──────────────────────────────────────────────────────
        // mkdir() sem checagem: se falhar (permissão, disco cheio), o código
        // seguia em frente apontando session_save_path() pra um diretório que
        // não existe — o PHP então falha silenciosamente ao persistir a
        // sessão (ou emite warning "Failed to write session data"), e o
        // usuário parece "deslogar sozinho" a cada requisição sem pista
        // nenhuma do motivo. Agora, se não conseguir criar/usar o diretório
        // dedicado, cai pro diretório padrão de sessões do PHP em vez de
        // apontar pra um caminho quebrado — a sessão continua funcionando,
        // só não fica isolada do resto do sistema.
        if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0755, true) && !is_dir($sessionPath)) {
            if (class_exists(\Core\Logger::class)) {
                \Core\Logger::error("Session: não foi possível criar {$sessionPath}, usando o session.save_path padrão do PHP.");
            }
        } else {
            session_save_path($sessionPath);
        }

        session_name(defined('APP_NAME') ? 'SESS_' . preg_replace('/[^a-zA-Z0-9]/', '', APP_NAME) : 'PHP_MVC_SESS');

        session_set_cookie_params([
            'lifetime' => (int)(env('SESSION_LIFETIME', 120)) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => static::shouldUseSecureCookies(defined('APP_ENV') ? APP_ENV : 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // ── MELHORIA #2 ──────────────────────────────────────────────────────
        // session_start() pode retornar false (ex: headers já enviados antes
        // de chegar aqui). Antes isso passava batido — o código seguia usando
        // $_SESSION normalmente, só que sem persistir nada entre requisições,
        // e ninguém saberia o porquê. Agora loga o problema, se possível, mas
        // não interrompe a execução: mesmo sem persistir, a aplicação ainda
        // funciona dentro dessa única requisição.
        if (!session_start() && class_exists(\Core\Logger::class)) {
            \Core\Logger::error('Session: session_start() falhou.');
        }

        // Erros de validação: duram somente uma requisição
        $_SESSION['_errors'] = $_SESSION['_errors_next'] ?? [];
        unset($_SESSION['_errors_next']);

        // ── MELHORIA #3 (corrige bug real) ────────────────────────────────────
        // Esta chamada estava faltando. O mecanismo de "aging" do old input
        // (oldInput() marca _old_input_read; ageOldInput() descarta na
        // requisição seguinte) só funciona se ageOldInput() rodar no início
        // de cada requisição — e não rodava em lugar nenhum. Resultado: dados
        // de old input ficavam na sessão indefinidamente após serem lidos uma
        // vez, podendo reaparecer em formulários de páginas completamente
        // diferentes depois.
        static::ageOldInput();

        // Regenera ID periodicamente (a cada 5 min)
        $now = time();
        if (!isset($_SESSION['_regen_at'])) {
            session_regenerate_id(true);
            $_SESSION['_regen_at'] = $now;
        } elseif ($now - $_SESSION['_regen_at'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_regen_at'] = $now;
        }
    }

    /**
     * Decide se cookies (sessão, remember-me, etc.) devem usar a flag Secure.
     * Verdadeiro se o ambiente for produção OU se SESSION_SECURE=true no .env.
     * "Produção força Secure" existe para não depender de ninguém lembrar de
     * configurar a variável corretamente antes de subir para produção.
     *
     * Único ponto de verdade — antes, config/app.php, Session::start() e
     * Auth::setRememberToken() calculavam isso cada um à sua maneira, e o
     * ini_set() de config/app.php era silenciosamente anulado pelo
     * session_set_cookie_params() explícito de Session::start().
     */
    public static function shouldUseSecureCookies(string $env): bool
    {
        return $env === 'production'
            || filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        return $_SESSION;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Flash messages ────────────────────────────────────────────────────────

    /** Armazena mensagem para a próxima requisição */
    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    /** Obtém e remove mensagem flash */
    public static function getFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    // ── Old Input (repopulação de formulários) ────────────────────────────────

    public static function flashInput(array $data): void
    {
        $_SESSION['_old_input'] = $data;

        // ── MELHORIA #4 ──────────────────────────────────────────────────────
        // Um novo flashInput() (novo erro de validação) precisa reiniciar o
        // ciclo de aging — sem isso, se _old_input_read já estivesse setado
        // de uma leitura anterior, o PRÓXIMO ageOldInput() descartaria esses
        // dados recém-flashados antes mesmo de serem exibidos no formulário.
        unset($_SESSION['_old_input_read']);
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors_next'] = $errors;
    }

    /**
     * ── BUG CORRIGIDO #9 (herdado) ────────────────────────────────────────────
     * Introduz o mecanismo de "aging" via flag _old_input_read, pra old input
     * não vazar indefinidamente entre páginas — replica o withOldInput() do
     * Laravel. Ver MELHORIA #3 acima: o pedaço que faltava era chamar
     * ageOldInput() de fato no início de cada requisição.
     */
    public static function oldInput(string $key, mixed $default = ''): mixed
    {
        // Marca os dados como "sendo lidos nesta requisição"
        if (isset($_SESSION['_old_input']) && !isset($_SESSION['_old_input_read'])) {
            $_SESSION['_old_input_read'] = true;
        }

        return $_SESSION['_old_input'][$key] ?? $default;
    }

    /**
     * Deve ser chamado no início de cada requisição para limpar old_input
     * que foi lido na requisição anterior.
     *
     * Chamado internamente por start() (ver MELHORIA #3) — não precisa ser
     * chamado manualmente.
     */
    public static function ageOldInput(): void
    {
        // Se na requisição anterior os dados foram lidos, descarta-os agora
        if (isset($_SESSION['_old_input_read'])) {
            unset($_SESSION['_old_input'], $_SESSION['_old_input_read']);
        }
    }

    public static function forgetOldInput(): void
    {
        unset($_SESSION['_old_input'], $_SESSION['_old_input_read']);
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = static::generateToken();
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    public static function regenerateCsrf(): void
    {
        $_SESSION['_csrf_token'] = static::generateToken();
    }

    // ── Tokens genéricos ──────────────────────────────────────────────────────

    /**
     * Gera um token hexadecimal criptograficamente seguro.
     * Reutilizado por CSRF, remember-me token, token de reset de senha
     * e geração de APP_KEY — evita reimplementar bin2hex(random_bytes()) em
     * cada lugar que precisa de um token aleatório.
     */
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
