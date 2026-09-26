<?php

namespace App\Models;

use Core\Model;

/**
 * User Model
 * ─────────────────────────────────────────────────────────────────────────────
 * Modelo de usuário. Mapeado para a tabela `users`
 * (ver database/migrations/2026_09_21_100000_create_users_table.php).
 *
 * Expanda o $fillable e adicione métodos conforme as necessidades do projeto.
 */
class User extends Model
{
    protected string $table      = 'users';
    protected array  $fillable   = ['name', 'email', 'password', 'role', 'active', 'phone', 'avatar', 'preferences'];
    protected array  $hidden     = ['password', 'remember_token'];
    protected bool   $timestamps = true;
    protected bool   $softDelete = true;

    public function findByEmail(string $email): object|false
    {
        return $this->findBy('email', $email);
    }

    /**
     * Autentica email + password.
     * Chamado por Core\Auth::attempt().
     */
    public function authenticate(string $email, string $password): object|false
    {
        $user = $this->findByEmail($email);
        if (!$user)                                        return false;
        if (empty($user->active))                            return false;
        if (!password_verify($password, $user->password))     return false;
        return $user;
    }

    public function createWithHash(array $data): string|false
    {
        $data['password']    = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $data['role'] ??= 'member';
        $data['active']  ??= 1;
        return $this->create($data);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->update($id, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }
}
