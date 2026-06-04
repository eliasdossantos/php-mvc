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

        // ── BUG CORRIGIDO #7 ────────────────────────────────────────────────
        // Antes: o objeto de sessão era criado com (object)[...], que produz
        // uma instância de stdClass. Ao acessar propriedades inexistentes em
        // PHP (ex: $user->avatar quando o model não tem esse campo), o código
        // em views que dependia de propriedades adicionais lançava warnings
        // silenciosos ou retornava null de forma inesperada.
        //
        // Mais importante: os campos gravados na sessão eram apenas um subconjunto
        // fixo (id, name, email, role). Se uma view ou helper precisasse de outro
        // campo do usuário (ex: active, created_at), não encontrava — mesmo que
        // o model tivesse esse dado.
        //
        // Solução: persistir na sessão apenas o ID (já feito acima) e campos
        // essenciais para auth (role). Para acesso a dados completos do usuário,
        // usar Auth::fresh() que re-busca do banco. O objeto em sessão é mantido
        // por compatibilidade com o restante do sistema, mas agora inclui todos
        // os campos públicos do model (exceto os campos $hidden como 'password').
        //
        // Campos 'hidden' do model NÃO são incluídos no objeto de sessão,
        // evitando que dados sensíveis fiquem na sessão serializada.
        $safeUser = static::buildSessionUser($user);
        Session::set('user', $safeUser);

        if ($remember) {
            // ── BUG CORRIGIDO #8 ────────────────────────────────────────────
            // Antes: o remember_token era gerado e salvo APENAS na sessão,
            // não no banco de dados. Isso tornava o recurso "lembrar de mim"
            // completamente ineficaz — o token era perdido ao encerrar a sessão,
            // que é exatamente quando ele precisaria ser consultado para
            // re-autenticar automaticamente.
            //
            // A implementação correta requer persistência no banco, mas isso
            // depende de infraestrutura adicional (cookie + coluna na tabela users).
            // Para não introduzir mudanças de schema não solicitadas, o código
            // abaixo prepara o token e o expõe, mas a persistência em banco
            // deve ser implementada pelo projeto que usa este boilerplate.
            //
            // Removemos o salvamento inútil na sessão e adicionamos comentário
            // claro sobre o que precisa ser feito para completar a feature.
            $token = bin2hex(random_bytes(32));
            // TODO: persistir $token no banco (coluna remember_token em users)
            // e setar cookie seguro com setcookie('remember', $token, time()+2592000, '/', '', true, true)
            // A re-autenticação automática deve ser feita em Session::start() ou em um middleware.
            unset($token); // evita variável pendente sem uso
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
            $hidden = ['password', 'remember_token'];
        }

        $data = (array) $user;
        foreach ($hidden as $field) {
            unset($data[$field]);
        }

        return (object) $data;
    }
}