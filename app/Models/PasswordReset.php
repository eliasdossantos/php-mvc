<?php

namespace App\Models;

use Core\Model;

/**
 * PasswordReset Model
 * Gerencia tokens de redefinição de senha.
 */
class PasswordReset extends Model
{
    protected string $table    = 'password_resets';
    protected array  $fillable = ['email', 'token', 'expires_at', 'used'];
    protected bool   $timestamps = false;

    public function createToken(string $email): string
    {
        // Invalida tokens anteriores do mesmo e-mail
        $this->db->query("UPDATE {$this->table} SET used = 1 WHERE email = :e")
            ->bind(':e', $email)->execute();

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->create(['email' => $email, 'token' => $token, 'expires_at' => $expiresAt, 'used' => 0]);
        return $token;
    }

    public function findValid(string $token): object|false
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE token = :t AND used = 0 AND expires_at > NOW() LIMIT 1"
        )->bind(':t', $token)->fetch();
    }

    public function consume(string $token): void
    {
        $this->db->query("UPDATE {$this->table} SET used = 1 WHERE token = :t")
            ->bind(':t', $token)->execute();
    }
}
