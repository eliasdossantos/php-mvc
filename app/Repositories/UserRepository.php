<?php

namespace App\Repositories;

use Core\Repository;
use App\Models\User;

/**
 * UserRepository
 * Encapsula o acesso a dados de usuários.
 * Estenda com métodos de busca específicos do seu projeto.
 */
class UserRepository extends Repository
{
    protected string $modelClass = User::class;

    public function findByEmail(string $email): object|false
    {
        return $this->model()->findBy('email', $email);
    }

    public function getActive(): array
    {
        return $this->model()->where('active', 1)->orderBy('name')->get();
    }

    public function search(string $term, int $page = 1, int $perPage = 15): array
    {
        $like = '%' . $term . '%';
        $offset = ($page - 1) * $perPage;

        $data = $this->db()
            ->query("SELECT id, name, email, role, active, created_at FROM users
                     WHERE name LIKE :t1 OR email LIKE :t2
                     ORDER BY name ASC LIMIT :lim OFFSET :off")
            ->bind(':t1', $like)->bind(':t2', $like)
            ->bind(':lim', $perPage, \PDO::PARAM_INT)
            ->bind(':off', $offset, \PDO::PARAM_INT)
            ->fetchAll();

        $total = (int) ($this->db()
            ->query("SELECT COUNT(*) AS n FROM users WHERE name LIKE :t1 OR email LIKE :t2")
            ->bind(':t1', $like)->bind(':t2', $like)->fetch()->n ?? 0);

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}
