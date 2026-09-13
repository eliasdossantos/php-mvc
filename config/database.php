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

        // Nota: o SQLite ainda não é suportado pelo Core\Database, pois o DSN é
        // montado assumindo host, porta, nome do banco e charset, formato que não
        // se aplica ao SQLite. Caso esse suporte seja implementado futuramente,
        // adicionarei a conexão novamente aqui, juntamente com o suporte adequado
        // no Database.php.
    ],
];
