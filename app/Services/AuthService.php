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
        if (Auth::attempt($email, $password, $remember)) {
            return ['success' => true];
        }

        // Auth::attempt() já verificou credencial + status ativo (via User::authenticate()).
        // Esta segunda consulta é só para decidir a mensagem, sem repetir a checagem de senha.
        $user = $this->users->findByEmail($email);
        if ($user && empty($user->active)) {
            return ['success' => false, 'message' => 'Conta inativa. Entre em contato com o suporte.'];
        }

        Logger::warning('Login falhou', ['email' => $email]);
        return ['success' => false, 'message' => 'E-mail ou senha incorretos.'];
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
