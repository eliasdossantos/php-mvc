<?php

/**
 * UsuarioSeeder — Cria usuários iniciais
 * ─────────────────────────────────────────────────────────────────────────────
 * Usuários:
 *   - Administrador
 *   - Usuário membro/demo
 *
 * Uso:
 *   php database/seeds/UsuarioSeeder.php
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
    $pdo = \Core\Database::getInstance()->getPdo();
} catch (\Throwable $e) {
    die("❌ Conexão falhou: " . $e->getMessage() . PHP_EOL);
}

// ─────────────────────────────────────────────────────────────────────────────
// Usuários iniciais
// ─────────────────────────────────────────────────────────────────────────────

$usuarios = [
    [
        'nome' => 'Administrador',
        'email' => 'admin@example.com',
        'senha' => 'admin123',
        'perfil' => 'admin',
        'ativo' => 1,
        'email_verificado_em' => date('Y-m-d H:i:s'),
    ],
    [
        'nome' => 'Usuário Demo',
        'email' => 'user@example.com',
        'senha' => 'user123',
        'perfil' => 'member',
        'ativo' => 1,
        'email_verificado_em' => date('Y-m-d H:i:s'),
    ],
];

// ─────────────────────────────────────────────────────────────────────────────
// Seed
// ─────────────────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo "🌱  Seeding usuários..." . PHP_EOL;
echo PHP_EOL;

foreach ($usuarios as $usuario) {

    // Verifica se o usuário já existe
    $stmt = $pdo->prepare(
        "SELECT id
         FROM usuarios
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute([
        ':email' => $usuario['email'],
    ]);

    if ($stmt->fetch()) {
        echo "  ! {$usuario['email']} já existe — ignorado." . PHP_EOL;
        continue;
    }

    // Gera hash seguro da senha
    $senhaHash = password_hash(
        $usuario['senha'],
        PASSWORD_BCRYPT,
        ['cost' => 12]
    );

    // Insere usuário
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha, perfil, ativo, email_verificado_em) VALUES (:nome, :email, :senha, :perfil, :ativo, :email_verificado_em)"
    );

    $stmt->execute([
        ':nome' => $usuario['nome'],
        ':email' => $usuario['email'],
        ':senha' => $senhaHash,
        ':perfil' => $usuario['perfil'],
        ':ativo' => $usuario['ativo'],
        ':email_verificado_em' => $usuario['email_verificado_em'],
    ]);

    echo "  ✓ {$usuario['email']} (senha: {$usuario['senha']})" . PHP_EOL;
}

echo PHP_EOL;
echo "✅  Seed concluído." . PHP_EOL;
echo PHP_EOL;
