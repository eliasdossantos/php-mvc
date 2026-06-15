<?php

namespace App\Services;

use Core\Service;
use Core\Auth;
use Core\Logger;
use App\Repositories\UserRepository;

/**
 * AuthService — Lógica de autenticação
 * Controllers delegam aqui — não contêm lógica de auth.
 */
class AuthService extends Service
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {}

    public function login(string $email, string $password, bool $remember = false): array
    {
        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user->password)) {
            // Log sem expor qual dos dois falhou (evita user enumeration)
            Logger::warning('Login falhou', ['email' => $email]);
            return ['success' => false, 'message' => 'E-mail ou senha incorretos.'];
        }

        if (empty($user->active)) {
            return ['success' => false, 'message' => 'Conta inativa. Entre em contato com o suporte.'];
        }

        Auth::loginUser($user, $remember);
        Logger::info('Login realizado', ['user_id' => $user->id]);
        return ['success' => true];
    }

    public function register(array $data): array
    {
        if ($this->users->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Este e-mail já está cadastrado.'];
        }

        $id = $this->users->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role'     => $data['role'] ?? 'member',
            'active'   => 1,
        ]);

        if (!$id) {
            return ['success' => false, 'message' => 'Erro ao criar conta. Tente novamente.'];
        }

        Logger::info('Usuário registrado', ['id' => $id, 'email' => $data['email']]);
        return ['success' => true, 'user_id' => $id];
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
