<?php

/**
 * Migration Runner — CLI
 * ─────────────────────────────────────────────────────────────────────────────
 * Executa todos os arquivos .sql de database/migrations/ em ordem alfabética.
 *
 * Uso:
 *   php database/migrate.php
 *   php database/migrate.php --fresh   (recria tudo do zero — CUIDADO em produção)
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Carrega .env manualmente (sem Composer ainda)
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = $v;
        putenv("{$k}={$v}");
    }
}

$config = require CONFIG_PATH . '/database.php';
$conn   = $config['connections'][$config['default']];

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $conn['host'], $conn['port'], $conn['database'], $conn['charset']);

try {
    $pdo = new PDO($dsn, $conn['username'], $conn['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("❌  Erro de conexão: " . $e->getMessage() . "\n");
}

$fresh = in_array('--fresh', $argv ?? []);

if ($fresh) {
    echo "⚠️  --fresh: dropando todas as tabelas…\n";
    $pdo->exec("SET foreign_key_checks = 0");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) { $pdo->exec("DROP TABLE IF EXISTS `{$t}`"); echo "   dropped: {$t}\n"; }
    $pdo->exec("SET foreign_key_checks = 1");
}

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

echo "\n🚀  Executando migrations…\n\n";
$ran = 0;

foreach ($files as $file) {
    $name = basename($file);
    echo "  → {$name} ";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        echo "✓\n";
        $ran++;
    } catch (PDOException $e) {
        // Table already exists é warning, não erro fatal
        if (str_contains($e->getMessage(), 'already exists')) {
            echo "(já existe, ignorado)\n";
        } else {
            echo "❌  ERRO: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n✅  {$ran} migration(s) executada(s).\n\n";
