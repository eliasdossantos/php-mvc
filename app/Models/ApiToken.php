<?php

namespace App\Models;

use Core\Model;
use Core\Session;

/**
 * ApiToken Model
 * ─────────────────────────────────────────────────────────────────────────────
 * Personal access tokens para autenticação Bearer da API (/api/v1). Mapeado
 * para a tabela `api_tokens` (ver migration
 * database/migrations/2026_09_22_100000_create_api_tokens_table.php).
 *
 * O valor em texto puro do token NUNCA é persistido — apenas seu hash SHA-256
 * (mesmo padrão já usado por Core\Auth para o cookie "lembrar de mim").
 */
class ApiToken extends Model
{
    protected string $table      = 'api_tokens';
    protected array  $fillable   = ['user_id', 'token_hash', 'name', 'last_used_at', 'expires_at'];
    protected bool   $timestamps = true;

    /**
     * Gera um novo token para o usuário, persiste o hash e retorna o valor
     * em texto puro — que só existe neste retorno; depois disso é
     * irrecuperável (mesma filosofia do GitHub/Laravel Sanctum).
     *
     * @return array{token:string, id:string|false}
     */
    public function issue(int $userId, string $name = 'api', ?string $expiresAt = null): array
    {
        $plain = Session::generateToken(); // 64 chars hex

        $id = $this->create([
            'user_id'   => $userId,
            'token_hash'   => hash('sha256', $plain),
            'name'         => $name,
            'expires_at'   => $expiresAt,
        ]);

        return ['token' => $plain, 'id' => $id];
    }

    /** Busca um token válido (não expirado) pelo valor em texto puro */
    public function findValidByPlainToken(string $plain): object|false
    {
        $hash = hash('sha256', $plain);

        return $this->db->query(
            "SELECT * FROM {$this->table}
              WHERE token_hash = :hash
                AND (expires_at IS NULL OR expires_at > NOW())
              LIMIT 1"
        )->bind(':hash', $hash)->fetch();
    }

    public function touchLastUsed(int $id): void
    {
        $this->update($id, ['last_used_at' => date('Y-m-d H:i:s')]);
    }

    /** Revoga (apaga) um token específico — usado por logout */
    public function revoke(int $id): bool
    {
        return $this->delete($id);
    }

    /** Revoga todos os tokens de um usuário (ex: "sair de todos os dispositivos") */
    public function revokeAllForUser(int $userId): bool
    {
        return $this->where('user_id', $userId)->deleteWhere();
    }
}
