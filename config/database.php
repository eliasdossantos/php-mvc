<?php

/**
 * Configuração do Banco de Dados
 * Todos os valores vêm do .env — nunca credenciais hardcoded aqui.
 */
return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST']      ?? 'localhost',
            'port'      => $_ENV['DB_PORT']      ?? '3306',
            'database'  => $_ENV['DB_DATABASE']  ?? 'app_db',
            'username'  => $_ENV['DB_USERNAME']  ?? 'root',
            'password'  => $_ENV['DB_PASSWORD']  ?? '',
            'charset'   => $_ENV['DB_CHARSET']   ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION']  ?? 'utf8mb4_unicode_ci',
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],

        // SQLite (para testes locais rápidos ou projetos pequenos)
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => STORAGE_PATH . '/database.sqlite',
        ],
    ],
];
