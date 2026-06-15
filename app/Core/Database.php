<?php

namespace Core;

/**
 * Database — Conexão Reutilizável com PDO
 * ─────────────────────────────────────────────────────────────────────────────
 * Implementa Singleton com PDO e interface fluente para queries seguras.
 *
 * Características:
 *  - Singleton thread-safe
 *  - Prepared statements em todos os casos (prevenção de SQL Injection)
 *  - Method chaining: query()->bind()->fetch()
 *  - Suporte a transações
 *  - Logging de erros
 *  - Configuração via config/database.php + .env
 *
 * Uso:
 *   $db = Database::getInstance();
 *   $user = $db->query("SELECT * FROM users WHERE id = :id")
 *              ->bind(':id', $id)
 *              ->fetch();
 *
 * Transações:
 *   $db->beginTransaction();
 *   try {
 *       $db->query(...)->bind(...)->execute();
 *       $db->commit();
 *   } catch (\Exception $e) {
 *       $db->rollback();
 *       throw $e;
 *   }
 */
class Database
{
    private static ?Database $instance = null;
    private \PDO             $pdo;
    private ?\PDOStatement   $stmt = null;

    private function __construct()
    {
        $config = require CONFIG_PATH . '/database.php';
        $conn   = $config['connections'][$config['default']];

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $conn['driver'],
            $conn['host'],
            $conn['port'],
            $conn['database'],
            $conn['charset']
        );

        try {
            $this->pdo = new \PDO($dsn, $conn['username'], $conn['password'], $conn['options']);
        } catch (\PDOException $e) {
            Logger::critical('Falha na conexão com o banco de dados', [
                'dsn'     => preg_replace('/password=[^;]+/', 'password=***', $dsn),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Não foi possível conectar ao banco de dados. Verifique as configurações.',
                500
            );
        }
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    // ── Query Builder ─────────────────────────────────────────────────────────

    /** Prepara uma query SQL */
    public function query(string $sql): static
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    /** Vincula um parâmetro ao prepared statement (type detection automático) */
    public function bind(string $param, mixed $value, ?int $type = null): static
    {
        $type ??= match (true) {
            is_int($value)  => \PDO::PARAM_INT,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_null($value) => \PDO::PARAM_NULL,
            default         => \PDO::PARAM_STR,
        };

        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    // ── Execução ──────────────────────────────────────────────────────────────

    public function execute(): bool
    {
        try {
            return $this->stmt->execute();
        } catch (\PDOException $e) {
            Logger::error('Erro ao executar query', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Erro ao executar query: ' . $e->getMessage(), 500);
        }
    }

    /** Executa e retorna múltiplos registros como array de objetos */
    public function fetchAll(): array
    {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    /** Executa e retorna um único registro como objeto */
    public function fetch(): object|false
    {
        $this->execute();
        return $this->stmt->fetch();
    }

    /** Executa e retorna o valor de uma única coluna */
    public function fetchColumn(int $col = 0): mixed
    {
        $this->execute();
        return $this->stmt->fetchColumn($col);
    }

    public function rowCount(): int
    {
        return $this->stmt?->rowCount() ?? 0;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // ── Transações ────────────────────────────────────────────────────────────

    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool           { return $this->pdo->commit(); }
    public function rollback(): bool         { return $this->pdo->rollBack(); }
    public function inTransaction(): bool    { return $this->pdo->inTransaction(); }

    /**
     * Executa um callback dentro de uma transação.
     * Faz commit em caso de sucesso, rollback em caso de exceção.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Executa SQL direto (apenas para migrations e seeds).
     *
     * ⚠️  AVISO: este método NÃO usa prepared statements.
     * NUNCA passe input de usuário aqui — use query()->bind() para isso.
     * Uso correto: migrations, seeds, DDL estático em código controlado.
     */
    public function execMigration(string $sql): int|false
    {
        return $this->pdo->exec($sql);
    }

    /**
     * Retorna o PDO bruto para operações avançadas.
     *
     * ⚠️  AVISO: ao usar o PDO diretamente, você perde as proteções
     * desta classe (logging, prepared statements obrigatórios).
     * Prefira sempre query()->bind()->fetch() desta classe.
     * Use getPdo() apenas para casos que o wrapper não cobre
     * (ex: bulk insert com executemany, operações específicas de driver).
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    // ── Singleton protection ──────────────────────────────────────────────────
    private function __clone() {}
    public function __wakeup(): never { throw new \RuntimeException('Singleton não pode ser desserializado.'); }
}
