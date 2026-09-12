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

        return $this->model()
            ->select('id', 'name', 'email', 'role', 'active', 'created_at')
            ->where('name', $like, 'LIKE')
            ->orWhere('email', $like, 'LIKE')
            ->orderBy('name')
            ->paginate($perPage, $page);
    }
}
