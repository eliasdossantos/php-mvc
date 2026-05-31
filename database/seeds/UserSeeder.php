<?php

/**
 * UserSeeder — Cria um usuário administrador inicial
 * ─────────────────────────────────────────────────────────────────────────────
 * Uso:
 *   php database/seeds/UserSeeder.php
 */

define('ROOT_PATH', dirname(__DIR__, 2));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = $v;
    }
}

$config = require CONFIG_PATH . '/database.php';
$conn   = $config['connections'][$config['default']];
$dsn    = "mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['database']};charset={$conn['charset']}";

try {
    $pdo = new PDO($dsn, $conn['username'], $conn['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("❌  Conexão falhou: " . $e->getMessage() . "\n");
}

$users = [
    ['name' => 'Administrador', 'email' => 'admin@example.com', 'password' => 'admin123', 'role' => 'admin'],
    ['name' => 'Usuário Demo',  'email' => 'user@example.com',  'password' => 'user123',  'role' => 'member'],
];

echo "\n🌱  Seeding usuários…\n\n";

foreach ($users as $u) {
    $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?")->execute([$u['email']]);
    $row    = $pdo->query("SELECT id FROM users WHERE email = '{$u['email']}'")->fetch();

    if ($row) {
        echo "  ! {$u['email']} já existe — ignorado.\n";
        continue;
    }

    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("INSERT INTO users (name, email, password, role, active) VALUES (?, ?, ?, ?, 1)")
        ->execute([$u['name'], $u['email'], $hash, $u['role']]);

    echo "  ✓ {$u['email']} (senha: {$u['password']})\n";
}

echo "\n✅  Seed concluído.\n\n";
