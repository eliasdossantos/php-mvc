<?php

/**
 * PHP MVC — Migration Runner — CLI
 *
 * Executa todos os arquivos .sql de database/migrations/
 * 
 * Uso:
 * php database/migrate.php
 * php database/migrate.php --fresh (recria tudo do zero — CUIDADO em produção)
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

require CONFIG_PATH . '/app.php';

$isFresh = in_array('--fresh', $argv ?? []);

echo "\n🗄  PHP MVC — Migration Runner\n";
echo str_repeat('─', 45) . "\n";

function quoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function tableExists(PDO $pdo, string $database, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = :database
        AND table_name = :table
    ");

    $stmt->execute([
        ':database' => $database,
        ':table' => $table
    ]);

    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $database, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = :database
        AND table_name = :table
        AND column_name = :column
    ");

    $stmt->execute([
        ':database' => $database,
        ':table' => $table,
        ':column' => $column
    ]);

    return (bool) $stmt->fetchColumn();
}

function foreignKeyExists(PDO $pdo, string $database, string $fkName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.table_constraints
        WHERE constraint_schema = :database
        AND constraint_name = :fk
        AND constraint_type = 'FOREIGN KEY'
    ");

    $stmt->execute([
        ':database' => $database,
        ':fk' => $fkName
    ]);

    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $database, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = :database
        AND table_name = :table
        AND index_name = :index
    ");

    $stmt->execute([
        ':database' => $database,
        ':table' => $table,
        ':index' => $index
    ]);

    return (bool) $stmt->fetchColumn();
}

function splitSqlStatements(string $sql): array
{
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/#.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    return array_filter(array_map('trim', explode(';', $sql)));
}

function shouldSkipStatement(PDO $pdo, string $database, string $statement): ?string
{
    // CREATE TABLE users (...)
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $statement, $match)) {
        $table = $match[1];

        if (tableExists($pdo, $database, $table)) {
            return "tabela '{$table}' já existe";
        }
    }

    // ALTER TABLE users ADD COLUMN active ...
    if (preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+ADD\s+(?:COLUMN\s+)?`?([a-zA-Z0-9_]+)`?/i', $statement, $match)) {
        $table = $match[1];
        $column = $match[2];

        if (columnExists($pdo, $database, $table, $column)) {
            return "coluna '{$table}.{$column}' já existe";
        }
    }

    // ALTER TABLE users ADD CONSTRAINT fk_name FOREIGN KEY (...)
    if (preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+ADD\s+CONSTRAINT\s+`?([a-zA-Z0-9_]+)`?\s+FOREIGN\s+KEY/i', $statement, $match)) {
        $fkName = $match[2];

        if (foreignKeyExists($pdo, $database, $fkName)) {
            return "chave estrangeira '{$fkName}' já existe";
        }
    }

    // CREATE INDEX index_name ON table (...)
    if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+`?([a-zA-Z0-9_]+)`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $match)) {
        $index = $match[1];
        $table = $match[2];

        if (indexExists($pdo, $database, $table, $index)) {
            return "índice '{$index}' já existe";
        }
    }

    return null;
}

try {
    $config = require CONFIG_PATH . '/database.php';
    $conn = $config['connections'][$config['default']];

    $dbName = $conn['database'];
    $quotedDb = quoteIdentifier($dbName);

    $dsn = sprintf(
        '%s:host=%s;port=%s;charset=%s',
        $conn['driver'],
        $conn['host'],
        $conn['port'],
        $conn['charset']
    );

    $pdo = new PDO(
        $dsn,
        $conn['username'],
        $conn['password'],
        $conn['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if ($isFresh) {
        echo "⚠  Modo --fresh: recriando banco '{$dbName}'...\n";

        $pdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");
        $pdo->exec("CREATE DATABASE {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE {$quotedDb}");

        echo "✓  Banco recriado.\n\n";
    } else {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE {$quotedDb}");
    }

    $migrationsTable = 'migrations';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$migrationsTable}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $migrationsPath = __DIR__ . '/migrations';
    $files = glob($migrationsPath . '/*.sql');
    sort($files);

    if (!$files) {
        echo "ℹ  Nenhuma migration encontrada.\n";
        exit(0);
    }

    $ran = 0;
    $skipped = 0;

    foreach ($files as $file) {
        $name = basename($file);

        if (!$isFresh) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM `{$migrationsTable}` WHERE migration = :migration");
            $check->execute([':migration' => $name]);

            if ($check->fetchColumn()) {
                echo "  [SKIP] {$name} já executada\n";
                $skipped++;
                continue;
            }
        }

        echo "  [RUN]  {$name}\n";

        $sql = file_get_contents($file);
        $statements = splitSqlStatements($sql);

        $pdo->beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (
                    $statement === '' ||
                    preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement)
                ) {
                    continue;
                }

                $reason = shouldSkipStatement($pdo, $dbName, $statement);

                if ($reason !== null) {
                    echo "        ↳ ignorado: {$reason}\n";
                    continue;
                }

                $pdo->exec($statement);
            }

            $insert = $pdo->prepare("INSERT IGNORE INTO `{$migrationsTable}` (migration) VALUES (:migration)");
            $insert->execute([':migration' => $name]);

            $pdo->commit();

            echo "        ✓ executada com sucesso\n";
            $ran++;
        } catch (Throwable $e) {
            $pdo->rollBack();

            echo "        ✕ erro: {$e->getMessage()}\n";
            exit(1);
        }
    }

    echo str_repeat('─', 45) . "\n";
    echo "✓ {$ran} migration(s) executada(s), {$skipped} ignorada(s).\n\n";
} catch (Throwable $e) {
    echo "\n✕ ERRO: {$e->getMessage()}\n\n";
    exit(1);
}