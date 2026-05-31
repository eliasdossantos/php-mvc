<?php

namespace Core;

use Core\Interfaces\RepositoryInterface;

/**
 * Repository Base
 * ─────────────────────────────────────────────────────────────────────────────
 * Camada de acesso a dados. Encapsula todas as queries de um Model.
 * Controllers não devem acessar o Model diretamente — usam o Repository.
 *
 * Benefícios:
 *  - Testabilidade (pode ser mockado nos testes)
 *  - Centraliza queries complexas
 *  - Separa acesso a dados da lógica de negócio
 *
 * Como criar:
 *   class UserRepository extends Repository {
 *       protected string $modelClass = User::class;
 *
 *       public function findByEmail(string $email): object|false {
 *           return $this->model->findBy('email', $email);
 *       }
 *   }
 */
abstract class Repository implements RepositoryInterface
{
    protected Model $model;
    protected string $modelClass = '';

    public function __construct()
    {
        if (empty($this->modelClass)) {
            throw new \RuntimeException(get_class($this) . ' deve definir $modelClass.');
        }
        $this->model = new ($this->modelClass)();
    }

    public function all(): array                          { return $this->model->all(); }
    public function findById(int $id): object|false       { return $this->model->find($id); }
    public function create(array $data): string|false     { return $this->model->create($data); }
    public function update(int $id, array $data): bool    { return $this->model->update($id, $data); }
    public function delete(int $id): bool                 { return $this->model->delete($id); }

    /**
     * Paginação via repository
     * @return array{data, total, page, per_page, last_page}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        return $this->model->paginate($perPage, $page);
    }

    /** Acesso direto ao Model para queries customizadas */
    protected function model(): Model { return $this->model; }
    protected function db(): Database { return $this->model->db; }
}
