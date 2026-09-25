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
 *  - Query builder fluente: where(), orWhere(), whereIn(), whereNull(),
 *    whereBetween(), whereLike(), whereRaw(), whereGroup(), join(), groupBy()
 *  - Buscas alternativas: pluck(), value(), findMany(), findOrFail(),
 *    firstOrFail(), firstOrCreate(), updateOrCreate()
 *  - Escrita em lote: insertMany(), updateWhere(), deleteWhere()
 *  - Proteção via fillable whitelist
 *  - Paginação automática
 *  - Timestamps automáticos (created_at, updated_at)
 *  - Soft delete (deleted_at)
 *
 * Uso básico:
 *   class User extends Model {
 *       protected string $table    = 'Users';
 *       protected array  $fillable = ['name', 'email', 'password'];
 *   }
 *
 *   $Users = (new User)->all();
 *   $User  = (new User)->find(1);
 *   $id    = (new User)->create(['name' => 'João']);
 *   (new User)->update(1, ['name' => 'Maria']);
 *   (new User)->delete(1);
 *   (new User)->where('role', 'admin')->get();
 *
 * Uso avançado (busca "não normal"):
 *   (new Produto)->whereIn('categoria_id', [1, 2, 3])
 *                ->whereBetween('preco', 10, 50)
 *                ->orderBy('preco')
 *                ->get();
 *
 *   (new Pedido)->whereGroup(function ($q) {
 *       $q->where('status', 'pendente')->orWhere('status', 'preparando');
 *   })->where('cliente_id', 42)->get();
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

    /**
     * Cada condição é ['type' => 'and'|'or', 'sql' => 'coluna = :param'].
     *
     * ── MELHORIA #1 (corrige bug real) ──────────────────────────────────────
     * Antes, orWhere() funcionava fazendo array_pop() da última condição e
     * remontando como "(ultima OR nova)" numa única string. Isso quebrava se
     * orWhere() fosse a PRIMEIRA chamada (array_pop de array vazio = null,
     * virando "( OR coluna = valor)", SQL inválido), e também misturava a
     * lógica de agrupamento de forma imprevisível em cadeias mais longas.
     * Agora cada condição fica isolada com seu tipo (and/or) e o SQL final é
     * montado juntando todas em sequência — o mesmo modelo usado por
     * query builders como o do Laravel.
     */
    private array  $wheres        = [];
    private array  $bindings      = [];
    private array  $joins         = [];
    private array  $groupByCols   = [];
    private string $havingClause  = '';
    private string $orderByClause = '';
    private ?int   $limitVal      = null;
    private ?int   $offsetVal     = null;
    private array  $selects       = ['*'];

    /**
     * ── MELHORIA #2 ──────────────────────────────────────────────────────────
     * Contador estático (compartilhado entre todas as instâncias/models) pra
     * gerar nomes de parâmetro sempre únicos. Antes o nome do parâmetro
     * dependia de count($this->wheres) da própria instância — o que colide
     * quando duas instâncias diferentes têm suas condições combinadas (caso
     * de whereGroup() abaixo, que roda um sub-builder e junta os bindings).
     */
    private static int $paramCounter = 0;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── CRUD Básico ───────────────────────────────────────────────────────────

    /**
     * Retorna todos os registros.
     *
     * ── MELHORIA #3 ──────────────────────────────────────────────────────────
     * Antes duplicava a lógica de sanitização/ORDER BY que já existe em
     * orderBy()+get(). Agora reaproveita o builder fluente — uma lógica só,
     * menos chance de os dois caminhos divergirem no futuro.
     */
    public function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return $this->orderBy($orderBy, $direction)->get();
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

    /** Busca registro por ID ou lança exceção — útil quando o registro é obrigatório */
    public function findOrFail(int $id): object
    {
        $record = $this->find($id);
        if ($record === false) {
            throw new \RuntimeException(static::class . " #{$id} não encontrado.");
        }
        return $record;
    }

    /** Busca registro por coluna/valor */
    public function findBy(string $column, mixed $value): object|false
    {
        $column = $this->sanitizeIdentifier($column);
        $where  = $this->softDelete ? "AND deleted_at IS NULL" : '';
        return $this->db
            ->query("SELECT * FROM {$this->table} WHERE {$column} = :v {$where} LIMIT 1")
            ->bind(':v', $value)
            ->fetch();
    }

    /** Busca vários registros pelo ID de uma vez (WHERE id IN (...)) */
    public function findMany(array $ids): array
    {
        if (empty($ids)) return [];
        return $this->whereIn($this->primaryKey, $ids)->get();
    }

    // ── Condições (WHERE) ────────────────────────────────────────────────────

    public function where(string $column, mixed $value, string $op = '='): static
    {
        return $this->addWhere($column, $op, $value, 'and');
    }

    public function orWhere(string $column, mixed $value, string $op = '='): static
    {
        return $this->addWhere($column, $op, $value, 'or');
    }

    /**
     * WHERE coluna IN (...). Se $values vier vazio, adiciona uma condição que
     * nunca bate (1 = 0) em vez de gerar "IN ()", que é SQL inválido.
     * Uso: $produtos->whereIn('categoria_id', [1, 2, 3]);
     */
    public function whereIn(string $column, array $values, string $boolean = 'and'): static
    {
        if (empty($values)) {
            $this->wheres[] = ['type' => $boolean, 'sql' => '1 = 0'];
            return $this;
        }

        $column = $this->sanitizeIdentifier($column);
        $placeholders = [];
        foreach (array_values($values) as $value) {
            $param = $this->nextParam('in_' . $column);
            $placeholders[] = $param;
            $this->bindings[$param] = $value;
        }

        $this->wheres[] = ['type' => $boolean, 'sql' => "{$column} IN (" . implode(', ', $placeholders) . ")"];
        return $this;
    }

    /**
     * WHERE coluna NOT IN (...). Se $values vier vazio, não adiciona restrição
     * nenhuma (NOT IN vazio bateria com tudo mesmo).
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        if (empty($values)) {
            return $this;
        }

        $column = $this->sanitizeIdentifier($column);
        $placeholders = [];
        foreach (array_values($values) as $value) {
            $param = $this->nextParam('nin_' . $column);
            $placeholders[] = $param;
            $this->bindings[$param] = $value;
        }

        $this->wheres[] = ['type' => $boolean, 'sql' => "{$column} NOT IN (" . implode(', ', $placeholders) . ")"];
        return $this;
    }

    /** WHERE coluna IS NULL — ex: pedidos sem entregador atribuído ainda */
    public function whereNull(string $column, string $boolean = 'and'): static
    {
        $column = $this->sanitizeIdentifier($column);
        $this->wheres[] = ['type' => $boolean, 'sql' => "{$column} IS NULL"];
        return $this;
    }

    /** WHERE coluna IS NOT NULL */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        $column = $this->sanitizeIdentifier($column);
        $this->wheres[] = ['type' => $boolean, 'sql' => "{$column} IS NOT NULL"];
        return $this;
    }

    /** WHERE coluna BETWEEN min AND max — ex: produtos numa faixa de preço */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'and'): static
    {
        $column   = $this->sanitizeIdentifier($column);
        $paramMin = $this->nextParam('bt_min_' . $column);
        $paramMax = $this->nextParam('bt_max_' . $column);

        $this->bindings[$paramMin] = $min;
        $this->bindings[$paramMax] = $max;
        $this->wheres[] = ['type' => $boolean, 'sql' => "{$column} BETWEEN {$paramMin} AND {$paramMax}"];
        return $this;
    }

    /**
     * WHERE coluna LIKE %valor% — busca parcial de texto.
     * Uso: $produtos->whereLike('nome', 'pizza');
     */
    public function whereLike(string $column, string $value, string $boolean = 'and'): static
    {
        return $this->addWhere($column, 'LIKE', "%{$value}%", $boolean);
    }

    /**
     * Escape hatch para condições que o builder não cobre (subqueries,
     * funções SQL, comparação entre colunas, etc.). Você é responsável pela
     * SQL — nunca interpole valor de usuário direto na string; use os
     * placeholders nomeados e passe os valores em $bindings.
     *
     * Uso: $produtos->whereRaw('preco > estoque_minimo * :fator', ['fator' => 1.5]);
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        foreach ($bindings as $param => $value) {
            $paramName = str_starts_with((string) $param, ':') ? $param : ':' . $param;
            $this->bindings[$paramName] = $value;
        }
        $this->wheres[] = ['type' => $boolean, 'sql' => $sql];
        return $this;
    }

    /**
     * Agrupa condições entre parênteses — necessário quando você precisa
     * misturar AND/OR com a precedência certa.
     * Uso: WHERE cliente_id = 42 AND (status = 'pendente' OR status = 'preparando')
     *   (new Pedido)->where('cliente_id', 42)->whereGroup(function ($q) {
     *       $q->where('status', 'pendente')->orWhere('status', 'preparando');
     *   })->get();
     */
    public function whereGroup(callable $callback, string $boolean = 'and'): static
    {
        /** @var static $group */
        $group = new static();
        $callback($group);

        $sql = $group->buildWhereClause();
        if ($sql === '') {
            return $this;
        }

        foreach ($group->bindings as $param => $value) {
            $this->bindings[$param] = $value;
        }

        $this->wheres[] = ['type' => $boolean, 'sql' => "({$sql})"];
        return $this;
    }

    // ── Joins, agrupamento e ordenação ───────────────────────────────────────

    /**
     * JOIN manual. Tipo padrão INNER; use leftJoin() para LEFT JOIN.
     * Uso: $produtos->join('categorias', 'produtos.categoria_id', '=', 'categorias.id');
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $table    = $this->sanitizeIdentifier($table);
        $first    = $this->sanitizeIdentifier($first);
        $second   = $this->sanitizeIdentifier($second);
        $operator = $this->sanitizeOperator($operator);

        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new \InvalidArgumentException("Tipo de JOIN inválido: [{$type}].");
        }

        $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /** Uso: $pedidos->groupBy('status'); */
    public function groupBy(string ...$columns): static
    {
        $this->groupByCols = array_map([$this, 'sanitizeIdentifier'], $columns);
        return $this;
    }

    /**
     * HAVING — roda depois do GROUP BY. Não é sanitizado por poder conter
     * agregações (COUNT, SUM); não interpole valor de usuário aqui direto.
     * Uso: $pedidos->groupBy('cliente_id')->having('COUNT(*) > 5');
     */
    public function having(string $condition): static
    {
        $this->havingClause = " HAVING {$condition}";
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): static
    {
        $column = $this->sanitizeIdentifier($column);
        $dir    = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderByClause = " ORDER BY {$column} {$dir}";
        return $this;
    }

    public function limit(int $n): static
    {
        $this->limitVal  = $n;
        return $this;
    }
    public function offset(int $n): static
    {
        $this->offsetVal = $n;
        return $this;
    }
    public function select(string ...$cols): static
    {
        $this->selects = array_map([$this, 'sanitizeIdentifier'], $cols);
        return $this;
    }

    // ── Execução (leitura) ───────────────────────────────────────────────────

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

    /** Igual first(), mas lança exceção se não encontrar nada */
    public function firstOrFail(): object
    {
        $record = $this->first();
        if ($record === false) {
            throw new \RuntimeException(static::class . ': nenhum registro encontrado com as condições atuais.');
        }
        return $record;
    }

    /**
     * Retorna um array simples com os valores de UMA coluna, pra todos os
     * registros que batem com o filtro atual.
     * Uso: $categorias->where('ativo', 1)->pluck('nome'); // ['Pizzas', 'Bebidas', ...]
     */
    public function pluck(string $column): array
    {
        $column = $this->sanitizeIdentifier($column);
        $this->selects = [$column];
        $rows = $this->get();
        return array_map(fn($row) => $row->{$column}, $rows);
    }

    /**
     * Retorna o valor de UMA coluna do primeiro registro que bate com o
     * filtro atual, ou null se não achar nada.
     * Uso: $preco = (new Produto)->where('id', 5)->value('preco');
     */
    public function value(string $column): mixed
    {
        $column = $this->sanitizeIdentifier($column);
        $this->selects = [$column];
        $row = $this->first();
        return $row->{$column} ?? null;
    }

    /**
     * Busca o primeiro registro que bate com $attributes; se não existir,
     * cria com $attributes + $values e retorna o registro recém-criado.
     * Uso: $categorias->firstOrCreate(['nome' => 'Bebidas']);
     */
    public function firstOrCreate(array $attributes, array $values = []): object
    {
        foreach ($attributes as $column => $value) {
            $this->where($column, $value);
        }

        $existing = $this->first();
        if ($existing !== false) {
            return $existing;
        }

        $id = $this->create(array_merge($attributes, $values));
        return $this->find((int) $id);
    }

    /**
     * Busca o primeiro registro que bate com $attributes; se existir,
     * atualiza com $values; se não, cria com $attributes + $values.
     * Uso: $estoque->updateOrCreate(['produto_id' => 7], ['quantidade' => 50]);
     */
    public function updateOrCreate(array $attributes, array $values = []): object
    {
        foreach ($attributes as $column => $value) {
            $this->where($column, $value);
        }

        $existing = $this->first();
        if ($existing !== false) {
            $id = $existing->{$this->primaryKey};
            $this->update((int) $id, $values);
            return $this->find((int) $id);
        }

        $id = $this->create(array_merge($attributes, $values));
        return $this->find((int) $id);
    }

    /** Conta registros que batem com as condições atuais */
    public function count(): int
    {
        // ── BUG CORRIGIDO #6 (herdado) ──────────────────────────────────────
        // count() precisa rodar sem destruir o estado do builder, porque
        // paginate() chama count() e depois get() usando os mesmos
        // wheres/joins/bindings.
        $savedState = $this->captureBuilderState();

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if ($this->joins) $sql .= ' ' . implode(' ', $this->joins);
        $sql .= $this->buildWhereSql();

        $stmt = $this->db->query($sql);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $r = $stmt->fetch();

        $this->restoreBuilderState($savedState);

        return (int) ($r->total ?? 0);
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

    /**
     * Insere vários registros numa única query (bem mais rápido que N
     * chamadas de create() em loop). Todas as linhas devem ter as mesmas
     * colunas — as colunas usadas são as da primeira linha, após o filtro
     * fillable.
     * Uso: $produtos->insertMany([
     *     ['nome' => 'Pizza', 'preco' => 39.90, 'categoria_id' => 1],
     *     ['nome' => 'Refrigerante', 'preco' => 6.00, 'categoria_id' => 2],
     * ]);
     */
    public function insertMany(array $rows): int
    {
        if (empty($rows)) return 0;

        $rows = array_map([$this, 'filterFillable'], $rows);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            foreach ($rows as &$row) {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;
            }
            unset($row);
        }

        $columns = array_keys($rows[0]);
        $colList = implode(', ', $columns);

        $placeholderGroups = [];
        $bindings = [];
        foreach (array_values($rows) as $i => $row) {
            $group = [];
            foreach ($columns as $col) {
                $param = ":r{$i}_{$col}";
                $group[] = $param;
                $bindings[$param] = $row[$col] ?? null;
            }
            $placeholderGroups[] = '(' . implode(', ', $group) . ')';
        }

        $sql  = "INSERT INTO {$this->table} ({$colList}) VALUES " . implode(', ', $placeholderGroups);
        $stmt = $this->db->query($sql);
        foreach ($bindings as $param => $value) $stmt->bind($param, $value);
        $stmt->execute();

        return count($rows);
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

    /**
     * Atualiza TODOS os registros que batem com as condições acumuladas via
     * where()/whereIn()/etc — ao contrário de update(), não recebe um ID
     * específico. Exige pelo menos uma condição, por segurança (evita
     * atualizar a tabela inteira por engano).
     * Uso: $pedidos->where('status', 'pendente')->updateWhere(['status' => 'cancelado']);
     */
    public function updateWhere(array $data): bool
    {
        $conditions = $this->buildWhereClause();
        if ($conditions === '') {
            throw new \RuntimeException('updateWhere() chamado sem nenhuma condição where() — abortado por segurança.');
        }

        $data = $this->filterFillable($data);
        if (empty($data)) return false;

        if ($this->timestamps) $data['updated_at'] = date('Y-m-d H:i:s');

        $set = implode(', ', array_map(fn($k) => "{$k} = :set_{$k}", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set} WHERE {$conditions}";

        $stmt = $this->db->query($sql);
        foreach ($data as $k => $v) $stmt->bind(":set_{$k}", $v);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $result = $stmt->execute();

        $this->resetBuilder();
        return $result;
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

    /**
     * Remove (ou soft-deleta) TODOS os registros que batem com as condições
     * acumuladas via where()/whereIn()/etc — não recebe ID. Exige pelo menos
     * uma condição, por segurança.
     * Uso: $carrinho->where('sessao_id', $sessaoId)->deleteWhere();
     */
    public function deleteWhere(): bool
    {
        $conditions = $this->buildWhereClause();
        if ($conditions === '') {
            throw new \RuntimeException('deleteWhere() chamado sem nenhuma condição where() — abortado por segurança.');
        }

        $sql = $this->softDelete
            ? "UPDATE {$this->table} SET deleted_at = NOW() WHERE {$conditions}"
            : "DELETE FROM {$this->table} WHERE {$conditions}";

        $stmt = $this->db->query($sql);
        foreach ($this->bindings as $k => $v) $stmt->bind($k, $v);
        $result = $stmt->execute();

        $this->resetBuilder();
        return $result;
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
     * ── BUG CORRIGIDO #6 (continuação, herdado) ─────────────────────────────
     * count() preserva o estado do builder; portanto o get() subsequente
     * ainda enxerga os wheres/joins/bindings intactos.
     *
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage  = max(1, $perPage); // evita divisão por zero se perPage <= 0
        $total    = $this->count();                              // estado preservado
        $lastPage = (int) ceil($total / $perPage);
        $page     = max(1, min($page, max(1, $lastPage)));

        $this->limitVal  = $perPage;
        $this->offsetVal = ($page - 1) * $perPage;
        $data = $this->get();                                    // usa wheres intactos

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

    private function addWhere(string $column, string $op, mixed $value, string $boolean): static
    {
        $column = $this->sanitizeIdentifier($column);
        $op     = $this->sanitizeOperator($op);
        $param  = $this->nextParam('w_' . $column);

        $this->wheres[]          = ['type' => $boolean, 'sql' => "{$column} {$op} {$param}"];
        $this->bindings[$param]  = $value;
        return $this;
    }

    /** Gera nome de parâmetro sempre único (ver MELHORIA #2 acima) */
    private function nextParam(string $prefix): string
    {
        return ':' . $prefix . '_' . (self::$paramCounter++);
    }

    /** Monta a string de condições WHERE a partir do array estruturado de wheres */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) return '';

        $sql = '';
        foreach ($this->wheres as $i => $condition) {
            $sql .= $i === 0
                ? $condition['sql']
                : ' ' . strtoupper($condition['type']) . ' ' . $condition['sql'];
        }
        return $sql;
    }

    /**
     * Combina as condições acumuladas com o filtro de soft delete, sempre
     * envolvendo as condições do usuário em parênteses — isso evita que um
     * OR no meio das condições quebre a precedência quando combinado com
     * "AND deleted_at IS NULL" (ex: sem os parênteses, "a OR b AND deleted_at
     * IS NULL" seria interpretado como "a OR (b AND deleted_at IS NULL)",
     * o que vazaria registros deletados via soft delete).
     */
    private function buildWhereSql(): string
    {
        $conditions = $this->buildWhereClause();

        $parts = [];
        if ($this->softDelete) $parts[] = 'deleted_at IS NULL';
        if ($conditions !== '') $parts[] = "({$conditions})";

        return $parts ? ' WHERE ' . implode(' AND ', $parts) : '';
    }

    protected function buildSelectSql(): string
    {
        $cols = implode(', ', $this->selects);
        $sql  = "SELECT {$cols} FROM {$this->table}";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhereSql();

        if ($this->groupByCols) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupByCols);
        }

        $sql .= $this->havingClause;
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
        $this->wheres        = [];
        $this->bindings      = [];
        $this->joins          = [];
        $this->groupByCols    = [];
        $this->havingClause   = '';
        $this->orderByClause = '';
        $this->limitVal      = null;
        $this->offsetVal     = null;
        $this->selects       = ['*'];
    }

    // ── Utilitários de segurança ───────────────────────────────────────────────

    /**
     * Sanitiza um identificador SQL (nome de coluna/tabela).
     * Permite apenas letras, números, underscores e pontos (schema.coluna,
     * ou tabela.coluna em joins).
     * Lança exceção se o identificador for inválido.
     *
     * ── BUG CORRIGIDO #4 / #5 (herdado) ─────────────────────────────────────
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Identificador SQL inválido: [{$identifier}]. Use apenas letras, números e underscores."
            );
        }
        return $identifier;
    }

    /**
     * Valida o operador de comparação contra uma whitelist.
     */
    protected function sanitizeOperator(string $op): string
    {
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        $op      = strtoupper(trim($op));
        if (!in_array($op, $allowed, true)) {
            throw new \InvalidArgumentException("Operador SQL inválido: [{$op}].");
        }
        return $op;
    }

    /**
     * Captura o estado atual do query builder para restauração posterior.
     * Usado por count() para não destruir wheres/joins/bindings ao ser
     * chamado dentro de paginate().
     */
    private function captureBuilderState(): array
    {
        return [
            'wheres'        => $this->wheres,
            'bindings'      => $this->bindings,
            'joins'         => $this->joins,
            'groupByCols'   => $this->groupByCols,
            'havingClause'  => $this->havingClause,
            'orderByClause' => $this->orderByClause,
            'limitVal'      => $this->limitVal,
            'offsetVal'     => $this->offsetVal,
            'selects'       => $this->selects,
        ];
    }

    private function restoreBuilderState(array $state): void
    {
        $this->wheres        = $state['wheres'];
        $this->bindings      = $state['bindings'];
        $this->joins          = $state['joins'];
        $this->groupByCols    = $state['groupByCols'];
        $this->havingClause   = $state['havingClause'];
        $this->orderByClause = $state['orderByClause'];
        $this->limitVal      = $state['limitVal'];
        $this->offsetVal     = $state['offsetVal'];
        $this->selects       = $state['selects'];
    }
}
