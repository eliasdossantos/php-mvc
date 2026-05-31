<?php

namespace Core;

/**
 * Model Base — Active Record simplificado
 * ─────────────────────────────────────────────────────────────────────────────
 * Fornece métodos CRUD genéricos + query builder fluente sobre PDO.
 * Todos os models da aplicação herdam desta classe.
 *
 * Recursos:
 *  - CRUD básico: all(), find(), create(), update(), delete()
 *  - Query builder fluente: where(), orWhere(), orderBy(), limit(), offset()
 *  - Proteção via fillable whitelist
 *  - Paginação automática
 *  - Timestamps automáticos (created_at, updated_at)
 *  - Soft delete (deleted_at)
 *  - Scopes reutilizáveis
 *
 * Uso:
 *   class User extends Model {
 *       protected string $table    = 'users';
 *       protected array  $fillable = ['name', 'email', 'password'];
 *   }
 *
 *   $users = (new User)->all();
 *   $user  = (new User)->find(1);
 *   $id    = (new User)->create(['name' => 'João']);
 *   (new User)->update(1, ['name' => 'Maria']);
 *   (new User)->delete(1);
 *   (new User)->where('role', 'admin')->get();
 */
abstract class Model
{
    public Database $db;

    /** Nome da tabela no banco */
    protected string $table = '';

    /** Chave primária */
    protected string $primaryKey = 'id';

    /** Campos permitidos para insert/update (whitelist) */
    protected array $fillable = [];

    /** Campos ocultos ao serializar (senhas, tokens) */
    protected array $hidden = [];

    /** Usar timestamps created_at / updated_at automaticamente */
    protected bool $timestamps = true;

    /** Usar soft delete (deleted_at) */
    protected bool $softDelete = false;

    // ── Query builder interno ─────────────────────────────────────────────────
    private array  $wheres      = [];
    private array  $bindings    = [];
    private string $orderByClause = '';
    private ?int   $limitVal    = null;
    private ?int   $offsetVal   = null;
    private array  $selects     = ['*'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── CRUD Básico ───────────────────────────────────────────────────────────

    /** Retorna todos os registros */
    public function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sql = "SELECT * FROM {$this->table}";
        if ($this->softDelete) $sql .= " WHERE deleted_at IS NULL";
        $sql .= " ORDER BY {$orderBy} {$dir}";
        return $this->db->query($sql)->fetchAll();
    }

    /** Busca registro por ID */
    public function find(int $id): object|false
    {
        $where = $this->softDelete ? "AND deleted_at IS NULL" : '';
        return $this->db
            ->query("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id {$where} LIMIT 1")
            ->bind(':id', $id)
            ->fetch();
    }

    /** Busca registro por coluna/valor */
    public function findBy(string $column, mixed $value): object|false
    {
        $where = $this->softDelete ? "AND deleted_at IS NULL" : '';
        return $this->db
            ->query("SELECT * FROM {$this->table} WHERE {$column} = :v {$where} LIMIT 1")
            ->bind(':v', $value)
            ->fetch();
    }

    /** Busca registros por coluna/valor (múltiplos) */
    public function where(string $column, mixed $value, string $op = '='): static
    {
        $param = ':w_' . $column . '_' . count($this->wheres);
        $this->wheres[]   = "{$column} {$op} {$param}";
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $value, string $op = '='): static
    {
        $param = ':ow_' . $column . '_' . count($this->wheres);
        $last  = array_pop($this->wheres) ?? '';
        $this->wheres[]   = "({$last} OR {$column} {$op} {$param})";
        $this->bindings[$param] = $value;
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): static
    {
        $this->orderByClause = " ORDER BY {$column} " . (strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public function limit(int $n): static  { $this->limitVal  = $n; return $this; }
    public function offset(int $n): static { $this->offsetVal = $n; return $this; }
    public function select(string ...$cols): static { $this->selects = $cols; return $this; }

    /** Executa a query e retorna múltiplos resultados */
    public function get(): array
    {
        $sql  = $this->buildSelectSql();
        $stmt = $this->db->query($sql);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $result = $stmt->fetchAll();
        $this->resetBuilder();
        return $result;
    }

    /** Executa a query e retorna o primeiro resultado */
    public function first(): object|false
    {
        $this->limitVal = 1;
        $sql  = $this->buildSelectSql();
        $stmt = $this->db->query($sql);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $result = $stmt->fetch();
        $this->resetBuilder();
        return $result;
    }

    /** Conta registros */
    public function count(): int
    {
        $softWhere = $this->softDelete ? " AND deleted_at IS NULL" : '';
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1 {$softWhere}";
        if ($this->wheres) $sql .= ' AND ' . implode(' AND ', $this->wheres);
        $stmt = $this->db->query($sql);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $r = $stmt->fetch();
        $this->resetBuilder();
        return (int)($r->total ?? 0);
    }

    /** Verifica se existe algum registro com as condições atuais */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    // ── Escrita ───────────────────────────────────────────────────────────────

    /** Insere registro e retorna o ID inserido */
    public function create(array $data): string|false
    {
        $data = $this->filterFillable($data);
        if (empty($data)) return false;

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }

        $cols   = implode(', ', array_keys($data));
        $placeh = ':' . implode(', :', array_keys($data));

        $stmt = $this->db->query("INSERT INTO {$this->table} ({$cols}) VALUES ({$placeh})");
        foreach ($data as $k => $v) $stmt->bind(":{$k}", $v);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    /** Atualiza registro por ID */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if (empty($data)) return false;

        if ($this->timestamps) $data['updated_at'] = date('Y-m-d H:i:s');

        $set  = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $stmt = $this->db->query("UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = :__id");
        foreach ($data as $k => $v) $stmt->bind(":{$k}", $v);
        $stmt->bind(':__id', $id);
        return $stmt->execute();
    }

    /** Remove registro por ID (soft delete se habilitado) */
    public function delete(int $id): bool
    {
        if ($this->softDelete) {
            return $this->db
                ->query("UPDATE {$this->table} SET deleted_at = NOW() WHERE {$this->primaryKey} = :id")
                ->bind(':id', $id)
                ->execute();
        }
        return $this->db
            ->query("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id")
            ->bind(':id', $id)
            ->execute();
    }

    /** Remove permanentemente mesmo com soft delete ativado */
    public function forceDelete(int $id): bool
    {
        return $this->db
            ->query("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id")
            ->bind(':id', $id)
            ->execute();
    }

    // ── Paginação ─────────────────────────────────────────────────────────────

    /**
     * Retorna dados paginados
     *
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total    = $this->count();
        $lastPage = (int) ceil($total / $perPage);
        $page     = max(1, min($page, max(1, $lastPage)));

        $this->limitVal  = $perPage;
        $this->offsetVal = ($page - 1) * $perPage;
        $data = $this->get();

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
            'from'      => ($page - 1) * $perPage + 1,
            'to'        => min($page * $perPage, $total),
        ];
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function buildSelectSql(): string
    {
        $cols      = implode(', ', $this->selects);
        $sql       = "SELECT {$cols} FROM {$this->table} WHERE 1=1";
        if ($this->softDelete) $sql .= " AND deleted_at IS NULL";
        if ($this->wheres)     $sql .= ' AND ' . implode(' AND ', $this->wheres);
        $sql .= $this->orderByClause;
        if ($this->limitVal  !== null) $sql .= " LIMIT {$this->limitVal}";
        if ($this->offsetVal !== null) $sql .= " OFFSET {$this->offsetVal}";
        return $sql;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) return $data;
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function resetBuilder(): void
    {
        $this->wheres       = [];
        $this->bindings     = [];
        $this->orderByClause = '';
        $this->limitVal     = null;
        $this->offsetVal    = null;
        $this->selects      = ['*'];
    }
}
