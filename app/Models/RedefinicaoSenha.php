<?php

namespace App\Models;

use Core\Model;
use Core\Session;

/**
 * RedefinicaoSenha Model
 * Gerencia tokens de redefinição de senha.
 * Mapeado para a tabela `redefinicoes_senha`
 * (ver database/migrations/2026_09_21_100001_create_redefinicoes_senha_table.php).
 */
class RedefinicaoSenha extends Model
{
    protected string $table      = 'redefinicoes_senha';
    protected array  $fillable   = ['email', 'token', 'expira_em', 'usado'];
    protected bool   $timestamps = false;

    public function createToken(string $email): string
    {
        // Invalida tokens anteriores do mesmo e-mail
        $this->db->query("UPDATE {$this->table} SET usado = 1 WHERE email = :e")
            ->bind(':e', $email)->execute();

        $token    = Session::generateToken();
        $expiraEm = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->create(['email' => $email, 'token' => $token, 'expira_em' => $expiraEm, 'usado' => 0]);
        return $token;
    }

    public function findValid(string $token): object|false
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE token = :t AND usado = 0 AND expira_em > NOW() LIMIT 1"
        )->bind(':t', $token)->fetch();
    }

    public function consume(string $token): void
    {
        $this->db->query("UPDATE {$this->table} SET usado = 1 WHERE token = :t")
            ->bind(':t', $token)->execute();
    }
}
