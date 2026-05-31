<?php

namespace App\Models;

use Core\Model;

/**
 * User Model
 * ─────────────────────────────────────────────────────────────────────────────
 * Modelo de usuário genérico.
 * Expanda o $fillable e adicione métodos conforme as necessidades do projeto.
 */
class User extends Model
{
    protected string $table      = 'users';
    protected array  $fillable   = ['name', 'email', 'password', 'role', 'active'];
    protected array  $hidden     = ['password'];
    protected bool   $timestamps = true;

    public function findByEmail(string $email): object|false
    {
        return $this->findBy('email', $email);
    }

    /**
     * Autentica email + senha.
     * Chamado por Core\Auth::attempt().
     */
    public function authenticate(string $email, string $password): object|false
    {
        $user = $this->findByEmail($email);
        if (!$user)                                        return false;
        if (empty($user->active))                          return false;
        if (!password_verify($password, $user->password)) return false;
        return $user;
    }

    public function createWithHash(array $data): string|false
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $data['role']   ??= 'member';
        $data['active'] ??= 1;
        return $this->create($data);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->update($id, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }
}
