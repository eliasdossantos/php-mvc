<?php

namespace Core\Interfaces;

/**
 * Interface base para Repositories
 * ─────────────────────────────────────────────────────────────────────────────
 * Define o contrato para acesso a dados de uma entidade.
 * Repositories encapsulam as queries e operações de banco,
 * desacoplando a lógica de negócio (Services) da camada de dados.
 */
interface RepositoryInterface
{
    public function all(): array;
    public function findById(int $id): object|false;
    public function create(array $data): string|false;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
