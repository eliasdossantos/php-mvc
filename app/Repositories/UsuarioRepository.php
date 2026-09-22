<?php

namespace App\Repositories;

use Core\Repository;
use App\Models\Usuario;

/**
 * UsuarioRepository
 * Encapsula o acesso a dados de usuários.
 * Estenda com métodos de busca específicos do seu projeto.
 */
class UsuarioRepository extends Repository
{
    protected string $modelClass = Usuario::class;

    public function findByEmail(string $email): object|false
    {
        return $this->model()->findBy('email', $email);
    }

    public function getActive(): array
    {
        return $this->model()->where('ativo', 1)->orderBy('nome')->get();
    }

    public function search(string $term, int $page = 1, int $perPage = 15): array
    {
        $like = '%' . $term . '%';

        return $this->model()
            ->select('id', 'nome', 'email', 'perfil', 'ativo', 'created_at')
            ->where('nome', $like, 'LIKE')
            ->orWhere('email', $like, 'LIKE')
            ->orderBy('nome')
            ->paginate($perPage, $page);
    }
}
