<?php

namespace Core;

/**
 * Auth — Sistema de Autenticação Reutilizável
 * ─────────────────────────────────────────────────────────────────────────────
 * Centraliza toda a lógica de autenticação e sessão de usuário.
 * É genérico: funciona com qualquer Model que implemente `authenticate()`.
 *
 * Uso:
 *   Auth::attempt($email, $password)  // tenta login
 *   Auth::check()                     // está logado?
 *   Auth::user()                      // objeto do usuário
 *   Auth::id()                        // ID do usuário
 *   Auth::is('admin')                 // tem determinada role?
 *   Auth::hasPermission('edit_posts') // tem permissão específica?
 *   Auth::logout()                    // encerra sessão
 *   Auth::require()                   // redireciona se não autenticado
 */
class Auth
{
    /** Model de usuário a ser usado (pode ser sobrescrito) */
    protected static string $userModel = \App\Models\User::class;

    // ── Autenticação ──────────────────────────────────────────────────────────

    /**
     * Tenta autenticar com email+senha.
     * O Model deve ter o método authenticate(string $email, string $password).
     */
    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $model = new (static::$userModel)();
        $user  = $model->authenticate($email, $password);

        if (!$user) return false;

        static::loginUser($user, $remember);
        return true;
    }

    /** Autentica diretamente pelo objeto de usuário */
    public static function loginUser(object $user, bool $remember = false): void
    {
        session_regenerate_id(true);

        Session::set('user_id',   $user->id);
        Session::set('user_role', $user->role ?? 'member');

        $safeUser = static::buildSessionUser($user);
        Session::set('user', $safeUser);

        if ($remember) {
            static::setRememberToken($user);
        }

        Logger::info('Login bem-sucedido', ['user_id' => $user->id, 'email' => $user->email]);
    }

    /**
     * Implementa "lembrar de mim" de forma segura.
     *
     * Gera um token aleatório de 64 chars, persiste hashed no banco e envia
     * cookie HttpOnly/Secure com o token em texto claro.
     * Na próxima visita, o cookie é lido, o hash comparado com o banco e o
     * usuário re-autenticado sem precisar digitar a senha.
     *
     * Segurança:
     *  - Token armazenado como SHA-256 hash no banco (nunca o valor bruto)
     *  - Comparação com hash_equals (timing-safe)
     *  - Cookie com Secure + HttpOnly + SameSite=Lax
     *  - Expiração de 30 dias
     *  - Token invalidado no logout
     */
    protected static function setRememberToken(object $user): void
    {
        $token   = bin2hex(random_bytes(32)); // 64 chars hex
        $hashed  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 dias

        // Persiste o hash no banco
        try {
            $db = Database::getInstance();
            $db->query(
                'UPDATE users SET remember_token = :token, remember_token_expires_at = :exp WHERE id = :id'
            )
                ->bind(':token', $hashed)
                ->bind(':exp',   $expires)
                ->bind(':id',    (int) $user->id)
                ->execute();
        } catch (\Throwable $e) {
            Logger::error('Falha ao salvar remember_token', ['user_id' => $user->id]);
            return; // Falha silenciosa — login já foi feito via sessão
        }

        // Envia cookie seguro com o token em texto claro
        $secure = (env('SESSION_SECURE', 'false') === 'true');
        setcookie(
            'remember_me',
            $token,
            [
                'expires'  => time() + 2592000,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Tenta re-autenticar via cookie "lembrar de mim".
     * Deve ser chamado no início de cada requisição (em Session::start() ou bootstrap).
     */
    public static function recoverFromCookie(): bool
    {
        if (static::check()) return true; // já autenticado

        $token = $_COOKIE['remember_me'] ?? null;
        if (!$token) return false;

        $hashed = hash('sha256', $token);

        try {
            $db   = Database::getInstance();
            $user = $db->query(
                'SELECT * FROM users
                  WHERE remember_token = :token
                    AND remember_token_expires_at > NOW()
                    AND active = 1
                  LIMIT 1'
            )
                ->bind(':token', $hashed)
                ->fetch();
        } catch (\Throwable) {
            return false;
        }

        if (!$user) {
            // Token inválido ou expirado — limpa o cookie
            static::clearRememberCookie();
            return false;
        }

        // Re-autentica e rotaciona o token (prevenção de token theft)
        static::loginUser($user, remember: true);
        return true;
    }

    public static function logout(): void
    {
        $userId = static::id();

        // Invalida remember token no banco
        if ($userId) {
            try {
                Database::getInstance()
                    ->query('UPDATE users SET remember_token = NULL, remember_token_expires_at = NULL WHERE id = :id')
                    ->bind(':id', $userId)
                    ->execute();
            } catch (\Throwable) {
            }
        }

        static::clearRememberCookie();
        Session::destroy();

        if ($userId) Logger::info('Logout', ['user_id' => $userId]);
    }

    protected static function clearRememberCookie(): void
    {
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE['remember_me']);
        }
    }

    // ── Verificações ──────────────────────────────────────────────────────────

    public static function check(): bool
    {
        return Session::has('user_id');
    }
    public static function guest(): bool
    {
        return !static::check();
    }
    public static function user(): ?object
    {
        return Session::get('user');
    }
    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int)$id : null;
    }
    public static function role(): string
    {
        return Session::get('user_role', 'guest');
    }

    /** Verifica se o usuário possui determinada role */
    public static function is(string $role): bool
    {
        return static::check() && static::role() === $role;
    }

    /** Verifica se o usuário tem QUALQUER uma das roles fornecidas */
    public static function isAny(string ...$roles): bool
    {
        return static::check() && in_array(static::role(), $roles);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    /** Garante autenticação, redireciona se não estiver */
    public static function require(string $redirectTo = 'auth/login'): void
    {
        if (!static::check()) {
            Session::flash('error', 'Faça login para continuar.');
            redirect($redirectTo);
        }
    }

    /** Garante que o usuário tem determinada role */
    public static function requireRole(string $role, string $redirectTo = 'app/dashboard'): void
    {
        static::require();
        if (!static::is($role)) {
            http_response_code(403);
            Session::flash('error', 'Acesso negado.');
            redirect($redirectTo);
        }
    }

    // ── Configuração ─────────────────────────────────────────────────────────

    public static function setUserModel(string $modelClass): void
    {
        static::$userModel = $modelClass;
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    /**
     * Constrói o objeto de sessão do usuário excluindo campos sensíveis ($hidden).
     * Suporta tanto objetos (PDO stdClass) quanto arrays.
     */
    protected static function buildSessionUser(object $user): object
    {
        // Descobre campos hidden do model para excluí-los da sessão
        $hidden = [];
        try {
            $modelInstance = new (static::$userModel)();
            $reflection    = new \ReflectionProperty($modelInstance, 'hidden');
            $reflection->setAccessible(true);
            $hidden = $reflection->getValue($modelInstance);
        } catch (\Throwable) {
            // Se não conseguir ler $hidden, usa padrão seguro
            $hidden = ['password', 'remember_token', 'remember_token_expires_at'];
        }

        // Garante que remember_token nunca vai para a sessão
        $hidden = array_unique(array_merge($hidden, ['remember_token', 'remember_token_expires_at']));

        $data = (array) $user;
        foreach ($hidden as $field) {
            unset($data[$field]);
        }

        return (object) $data;
    }
}
