<?php

namespace Cli;

/**
 * Migrator — Executa as migrations do projeto
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsável pela execução das migrations do framework.
 *
 * Não faz echo/exit diretamente: progresso é reportado por callback opcional,
 * e falhas são lançadas como exceção (capturadas pelo Cli\Kernel::run()).
 *
 * MUDANÇA: migrations deixaram de ser arquivos .sql soltos e passaram a ser
 * classes PHP (Core\Migration) com up()/down(), no estilo Laravel. Cada
 * execução é registrada com um número de "batch", permitindo desfazer
 * (rollback()) o último lote aplicado — o que não era possível no formato
 * anterior baseado só em nome de arquivo já rodado ou não.
 *
 * Aviso: no MySQL, comandos DDL (CREATE/ALTER/DROP TABLE) fazem commit
 * implícito. Isso significa que, se uma migration com várias tabelas falhar
 * na metade, as tabelas já criadas antes do erro NÃO são desfeitas
 * automaticamente — é preciso corrigir manualmente ou rodar down() daquela
 * migration antes de tentar de novo. Isso é uma limitação do MySQL, não
 * deste Migrator (o Laravel tem a mesma limitação).
 */
class Migrator
{
    private string $migrationsTable = 'migrations';

    /**
     * @param bool          $fresh  Se true, dropa e recria o banco antes de migrar.
     * @param callable|null $onLine function(string $message): void
     * @return array{ran:int,skipped:int}
     */
    public function run(bool $fresh = false, ?callable $onLine = null): array
    {
        $report = function (string $msg) use ($onLine) {
            if ($onLine !== null) {
                $onLine($msg);
            }
        };

        $this->bootstrapEnvironment();

        $config = require CONFIG_PATH . '/database.php';
        $conn   = $config['connections'][$config['default']];
        $dbName = $conn['database'];

        $this->ensureDatabaseExists($conn, $dbName, $fresh, $report);

        $db  = \Core\Database::getInstance();
        $pdo = $db->getPdo();

        $this->ensureMigrationsTable($db, $pdo);

        $migrationsPath = ROOT_PATH . '/database/migrations';
        $files = $this->allMigrationFiles($migrationsPath);

        if (!$files) {
            $report('ℹ  Nenhuma migration encontrada.');
            return ['ran' => 0, 'skipped' => 0];
        }

        $ranNames = $this->ranMigrations($pdo);
        $batch = $this->nextBatch($pdo);

        $ran = 0;
        $skipped = 0;

        foreach ($files as $name => $path) {
            if (in_array($name, $ranNames, true)) {
                $report("  [SKIP] {$name} já executada");
                $skipped++;
                continue;
            }

            $report("  [RUN]  {$name}");

            $migration = $this->loadMigration($path);
            $migration->up();
            $this->recordMigration($pdo, $name, $batch);

            $report("        ✓ executada com sucesso");
            $ran++;
        }

        return ['ran' => $ran, 'skipped' => $skipped];
    }

    /**
     * Desfaz o(s) último(s) batch(es) aplicados, chamando down() na ordem inversa.
     *
     * @param int           $steps  Quantos batches desfazer (1 = só o último).
     * @param callable|null $onLine function(string $message): void
     * @return array{rolled_back:int}
     */
    public function rollback(int $steps = 1, ?callable $onLine = null): array
    {
        $report = function (string $msg) use ($onLine) {
            if ($onLine !== null) {
                $onLine($msg);
            }
        };

        $this->bootstrapEnvironment();

        $db  = \Core\Database::getInstance();
        $pdo = $db->getPdo();

        $this->ensureMigrationsTable($db, $pdo);

        $migrationsPath = ROOT_PATH . '/database/migrations';
        $files = $this->allMigrationFiles($migrationsPath);
        $rows  = $this->lastBatches($pdo, $steps);

        if (!$rows) {
            $report('ℹ  Nada para desfazer.');
            return ['rolled_back' => 0];
        }

        $rolledBack = 0;

        foreach ($rows as $row) {
            $name = $row['migration'];

            if (!isset($files[$name])) {
                $report("  [WARN] {$name} não encontrada em disco — removendo apenas o registro");
                $this->removeMigrationRecord($pdo, $name);
                continue;
            }

            $report("  [DOWN] {$name}");

            $migration = $this->loadMigration($files[$name]);
            $migration->down();
            $this->removeMigrationRecord($pdo, $name);

            $report("        ✓ desfeita com sucesso");
            $rolledBack++;
        }

        return ['rolled_back' => $rolledBack];
    }

    // ── Bootstrap mínimo de ambiente ─────────────────────────────────────────
    // Necessário porque o entry point `mvc` só define ROOT_PATH e o autoload —
    // não carrega dotenv nem config/app.php (mesmo motivo pelo qual os
    // seeders também fazem sua própria preparação de ambiente).
    //
    // Público (não mais private) porque MigrateCommand precisa chamá-lo
    // ANTES de run(), para ter acesso a APP_ENV/config do banco na hora de
    // decidir se pede confirmação para --fresh. Idempotente: pode ser chamado
    // mais de uma vez na mesma requisição sem efeito colateral (require_once
    // evita redefinir as constantes de config/app.php duas vezes).
    public function bootstrapEnvironment(): void
    {
        if (!defined('CONFIG_PATH')) {
            define('CONFIG_PATH', ROOT_PATH . '/config');
        }
        if (!defined('APP_PATH')) {
            define('APP_PATH', ROOT_PATH . '/app');
        }
        if (!defined('STORAGE_PATH')) {
            define('STORAGE_PATH', ROOT_PATH . '/storage');
        }

        $dotenv = \Dotenv\Dotenv::createImmutable(ROOT_PATH);
        $dotenv->safeLoad();

        require_once CONFIG_PATH . '/app.php';
    }

    /**
     * Nome do banco de dados configurado atualmente (conexão padrão).
     * Usado por MigrateCommand para a confirmação do --fresh.
     * Chame bootstrapEnvironment() antes, se ainda não tiver sido chamado
     * nesta requisição (precisa de CONFIG_PATH definido).
     */
    public function currentDatabaseName(): string
    {
        $config = require CONFIG_PATH . '/database.php';
        $conn   = $config['connections'][$config['default']];
        return $conn['database'];
    }

    // ── Provisionamento do banco ─────────────────────────────────────────────
    // Conexão própria, sem dbname: Core\Database sempre inclui dbname no DSN
    // e por isso não pode ser usado antes de o banco existir.
    private function ensureDatabaseExists(array $conn, string $dbName, bool $fresh, callable $report): void
    {
        $quotedDb = $this->quoteIdentifier($dbName);

        $dsn = sprintf(
            '%s:host=%s;port=%s;charset=%s',
            $conn['driver'],
            $conn['host'],
            $conn['port'],
            $conn['charset']
        );

        $pdo = new \PDO(
            $dsn,
            $conn['username'],
            $conn['password'],
            $conn['options'] ?? [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        if ($fresh) {
            $report("⚠  Modo --fresh: recriando banco '{$dbName}'...");
            $pdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");
            $pdo->exec("CREATE DATABASE {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $report("✓  Banco recriado.");
        } else {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    // ── Tabela de controle de migrations ─────────────────────────────────────

    private function ensureMigrationsTable(\Core\Database $db, \PDO $pdo): void
    {
        $db->execMigration("
            CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Compatibilidade com o formato anterior (tabela já existia sem a
        // coluna batch): adiciona agora, com default 1 pras linhas existentes.
        $hasBatch = $pdo->query("SHOW COLUMNS FROM `{$this->migrationsTable}` LIKE 'batch'")->fetch();
        if (!$hasBatch) {
            $pdo->exec("ALTER TABLE `{$this->migrationsTable}` ADD COLUMN `batch` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `migration`");
        }
    }

    // ── Descoberta de arquivos de migration ──────────────────────────────────

    /** @return array<string,string> nome (sem .php) => caminho completo, em ordem cronológica */
    private function allMigrationFiles(string $path): array
    {
        $files = glob($path . '/*.php') ?: [];
        sort($files); // o prefixo YYYY_MM_DD_HHMMSS garante ordem cronológica

        $map = [];
        foreach ($files as $file) {
            $map[basename($file, '.php')] = $file;
        }
        return $map;
    }

    private function loadMigration(string $path): \Core\Migration
    {
        $migration = require $path; // arquivo deve fazer: return new class extends Migration {...};
        if (!$migration instanceof \Core\Migration) {
            throw new \RuntimeException("Migration inválida: {$path} não retorna uma instância de Core\\Migration.");
        }
        return $migration;
    }

    // ── Bookkeeping (histórico de execução) ──────────────────────────────────

    private function ranMigrations(\PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT migration FROM `{$this->migrationsTable}`");
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'migration');
    }

    private function nextBatch(\PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT MAX(batch) as max_batch FROM `{$this->migrationsTable}`");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['max_batch'] ?? 0) + 1;
    }

    private function lastBatches(\PDO $pdo, int $steps): array
    {
        $stmt = $pdo->prepare("SELECT DISTINCT batch FROM `{$this->migrationsTable}` ORDER BY batch DESC LIMIT :steps");
        $stmt->bindValue(':steps', $steps, \PDO::PARAM_INT);
        $stmt->execute();
        $batches = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'batch');

        if (!$batches) {
            return [];
        }

        // valores já vieram do banco como inteiros; forçar de novo por segurança antes de concatenar
        $placeholders = implode(',', array_map('intval', $batches));

        $stmt = $pdo->query(
            "SELECT migration, batch FROM `{$this->migrationsTable}` WHERE batch IN ({$placeholders}) ORDER BY id DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function recordMigration(\PDO $pdo, string $name, int $batch): void
    {
        $stmt = $pdo->prepare("INSERT INTO `{$this->migrationsTable}` (migration, batch) VALUES (:migration, :batch)");
        $stmt->execute([':migration' => $name, ':batch' => $batch]);
    }

    private function removeMigrationRecord(\PDO $pdo, string $name): void
    {
        $stmt = $pdo->prepare("DELETE FROM `{$this->migrationsTable}` WHERE migration = :migration");
        $stmt->execute([':migration' => $name]);
    }
}
