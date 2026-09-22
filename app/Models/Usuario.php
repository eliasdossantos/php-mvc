<?php

namespace App\Models;

use Core\Model;

/**
 * Usuario Model
 * ─────────────────────────────────────────────────────────────────────────────
 * Modelo de usuário. Mapeado para a tabela `usuarios`
 * (ver database/migrations/2026_09_21_100000_create_usuarios_table.php).
 *
 * Expanda o $fillable e adicione métodos conforme as necessidades do projeto.
 */
class Usuario extends Model
{
    protected string $table      = 'usuarios';
    protected array  $fillable   = ['nome', 'email', 'password', 'perfil', 'ativo', 'telefone', 'avatar', 'preferencias'];
    protected array  $hidden     = ['password', 'lembrar_token'];
    protected bool   $timestamps = true;
    protected bool   $softDeletes = false;

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
        if (empty($user->ativo))                            return false;
        if (!password_verify($password, $user->password))     return false;
        return $user;
    }

    public function createWithHash(array $data): string|false
    {
        $data['password']    = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $data['perfil'] ??= 'member';
        $data['ativo']  ??= 1;
        return $this->create($data);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->update($id, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }
}
