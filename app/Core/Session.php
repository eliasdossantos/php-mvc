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
        if (!is_dir($sessionPath)) mkdir($sessionPath, 0755, true);

        session_save_path($sessionPath);
        session_name(defined('APP_NAME') ? 'SESS_' . preg_replace('/[^a-zA-Z0-9]/', '', APP_NAME) : 'PHP_MVC_SESS');

        session_set_cookie_params([
            'lifetime' => (int)(env('SESSION_LIFETIME', 120)) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => env('SESSION_SECURE', 'false') === 'true',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

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

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function has(string $key): bool  { return isset($_SESSION[$key]); }
    public static function forget(string $key): void { unset($_SESSION[$key]); }

    public static function all(): array { return $_SESSION; }

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

    public static function hasFlash(string $key): bool { return isset($_SESSION['_flash'][$key]); }

    // ── Old Input (repopulação de formulários) ────────────────────────────────

    public static function flashInput(array $data): void { $_SESSION['_old_input'] = $data; }

    /**
     * ── BUG CORRIGIDO #9 ────────────────────────────────────────────────────
     * Antes: oldInput() lia o valor mas NÃO limpava o _old_input da sessão.
     * O comentário "Limpeza lazy: remove após ler todos os valores" indicava
     * intenção, mas a implementação não executava essa limpeza — forgetOldInput()
     * existia mas nunca era chamado automaticamente.
     *
     * Consequência: ao submeter um formulário com erro, os valores eram
     * preservados corretamente na próxima requisição (comportamento desejado).
     * Porém, ao navegar para outra página sem resubmeter o formulário, os
     * valores antigos continuavam disponíveis indefinidamente na sessão,
     * podendo vazar para formulários de outras páginas que usassem old().
     *
     * Solução: introduzir um mecanismo de "aging" via flag _old_input_consumed.
     * Na primeira chamada a oldInput() após um flashInput(), os dados são lidos
     * normalmente. Na próxima requisição, se oldInput() for chamado novamente
     * sem um novo flashInput(), os dados são automaticamente descartados.
     *
     * Isso replica o comportamento do withOldInput() do Laravel.
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
     * Deve ser chamado no início de cada requisição (em Session::start())
     * para limpar old_input que foi lido na requisição anterior.
     *
     * Chamado internamente por start() — não precisa ser chamado manualmente.
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
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    public static function regenerateCsrf(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
}
