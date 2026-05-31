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
        Session::set('user', (object)[
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role ?? 'member',
        ]);

        if ($remember) {
            // Token de "lembrar" — implementar conforme necessário
            $token = bin2hex(random_bytes(32));
            Session::set('remember_token', $token);
        }

        Logger::info('Login bem-sucedido', ['user_id' => $user->id, 'email' => $user->email]);
    }

    public static function logout(): void
    {
        $userId = static::id();
        Session::destroy();
        if ($userId) Logger::info('Logout', ['user_id' => $userId]);
    }

    // ── Verificações ──────────────────────────────────────────────────────────

    public static function check(): bool  { return Session::has('user_id'); }
    public static function guest(): bool  { return !static::check(); }
    public static function user(): ?object { return Session::get('user'); }
    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int)$id : null;
    }
    public static function role(): string { return Session::get('user_role', 'guest'); }

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
}
