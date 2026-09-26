<?php

/**
 * UserSeeder — Cria usuários iniciais
 * ─────────────────────────────────────────────────────────────────────────────
 * Usuários:
 *   - Administrador
 *   - Usuário membro/demo
 *
 * Uso:
 *   php database/seeds/UserSeeder.php
 */

define('ROOT_PATH', dirname(__DIR__, 2));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// ─────────────────────────────────────────────────────────────────────────────
// Carrega variáveis do .env
// ─────────────────────────────────────────────────────────────────────────────

$envFile = ROOT_PATH . '/.env';

if (file_exists($envFile)) {
    foreach (
        file(
            $envFile,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        ) as $line
    ) {
        $line = trim($line);

        if (
            $line === '' ||
            str_starts_with($line, '#') ||
            !str_contains($line, '=')
        ) {
            continue;
        }

        [$key, $value] = array_map(
            'trim',
            explode('=', $line, 2)
        );

        $_ENV[$key] = $value;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Autoload
// ─────────────────────────────────────────────────────────────────────────────

require ROOT_PATH . '/vendor/autoload.php';

// ─────────────────────────────────────────────────────────────────────────────
// Conexão com o banco
// ─────────────────────────────────────────────────────────────────────────────

try {
    $pdo = \Framework\Database::getInstance()->getPdo();
} catch (\Throwable $e) {
    die("❌ Conexão falhou: " . $e->getMessage() . PHP_EOL);
}

// ─────────────────────────────────────────────────────────────────────────────
// Usuários iniciais
// ─────────────────────────────────────────────────────────────────────────────

$users = [
    [
        'name' => 'Administrador',
        'email' => 'admin@example.com',
        'password' => 'admin123',
        'role' => 'admin',
        'active' => 1,
        'email_verified_at' => date('Y-m-d H:i:s'),
    ],
    [
        'name' => 'Usuário Demo',
        'email' => 'user@example.com',
        'password' => 'user123',
        'role' => 'member',
        'active' => 1,
        'email_verified_at' => date('Y-m-d H:i:s'),
    ],
];

// ─────────────────────────────────────────────────────────────────────────────
// Seed
// ─────────────────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo "🌱  Seeding usuários..." . PHP_EOL;
echo PHP_EOL;

foreach ($users as $user) {

    // Verifica se o usuário já existe
    $stmt = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute([
        ':email' => $user['email'],
    ]);

    if ($stmt->fetch()) {
        echo "  ! {$user['email']} já existe — ignorado." . PHP_EOL;
        continue;
    }

    // Gera hash seguro da senha
    $senhaHash = password_hash(
        $user['password'],
        PASSWORD_BCRYPT,
        ['cost' => 12]
    );

    // Insere usuário
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, active, email_verified_at) VALUES (:name, :email, :password, :role, :active, :email_verified_at)"
    );

    $stmt->execute([
        ':name' => $user['name'],
        ':email' => $user['email'],
        ':password' => $senhaHash,
        ':role' => $user['role'],
        ':active' => $user['active'],
        ':email_verified_at' => $user['email_verified_at'],
    ]);

    echo "  ✓ {$user['email']}" . PHP_EOL;
}

echo PHP_EOL;
echo "✅  Seed concluído." . PHP_EOL;
echo PHP_EOL;
