<?php

namespace Cli;

/**
 * Migrator — Executa as migrations SQL do projeto
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsável pela execução das migrations do framework.
 *
 * Não faz echo/exit diretamente: progresso é reportado por callback opcional,
 * e falhas são lançadas como exceção (capturadas pelo Cli\Kernel::run()).
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

        $pdo = \Core\Database::getInstance()->getPdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $migrationsPath = ROOT_PATH . '/database/migrations';
        $files = glob($migrationsPath . '/*.sql');

        if (!$files) {
            $report('ℹ  Nenhuma migration encontrada.');
            return ['ran' => 0, 'skipped' => 0];
        }

        natsort($files);
        $files = array_values($files);

        $ran = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $name = basename($file);

            if (!$fresh) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM `{$this->migrationsTable}` WHERE migration = :migration");
                $check->execute([':migration' => $name]);

                if ($check->fetchColumn()) {
                    $report("  [SKIP] {$name} já executada");
                    $skipped++;
                    continue;
                }
            }

            $report("  [RUN]  {$name}");

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Não foi possível ler a migration {$name}");
            }

            foreach ($this->splitSqlStatements($sql) as $statement) {
                if (trim($statement) === '') {
                    continue;
                }
                $pdo->exec($statement);
            }

            $insert = $pdo->prepare("
                INSERT IGNORE INTO `{$this->migrationsTable}` (migration)
                VALUES (:migration)
            ");
            $insert->execute([':migration' => $name]);

            $report("        ✓ executada com sucesso");
            $ran++;
        }

        return ['ran' => $ran, 'skipped' => $skipped];
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

    private function splitSqlStatements(string $sql): array
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/#.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        return array_filter(array_map('trim', explode(';', $sql)));
    }
}
