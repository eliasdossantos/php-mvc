<?php

use Core\Database;
use Core\Migration;

/**
 * Migra o schema de usuários para as convenções em inglês.
 *
 * A migration usa SQL explícito porque o Schema Builder próprio do projeto
 * ainda não oferece renameTable/renameColumn nem alteração de foreign keys.
 */
return new class extends Migration {
    private const TABLE_OLD = 'usuarios';
    private const TABLE_NEW = 'users';

    /** @var array<string, string> */
    private const COLUMNS = [
        'nome'                  => 'name',
        'perfil'                => 'role',
        'ativo'                 => 'active',
        'email_verificado_em'   => 'email_verified_at',
        'ultimo_login_em'       => 'last_login_at',
        'lembrar_token'         => 'remember_token',
        'lembrar_token_expira_em' => 'remember_token_expires_at',
        'telefone'              => 'phone',
        'preferencias'          => 'preferences',
    ];

    /** @var array<string, string> */
    private const INDEXES = [
        'idx_usuarios_ativo'  => 'idx_users_active',
        'idx_usuarios_perfil' => 'idx_users_role',
        'uniq_usuarios_email' => 'uniq_users_email',
    ];

    public function up(): void
    {
        $db = Database::getInstance();

        // A FK precisa ser removida antes de renomear a tabela/coluna pai.
        $db->execMigration(
            'ALTER TABLE api_tokens DROP FOREIGN KEY fk_api_tokens_usuario_id'
        );

        $db->execMigration('RENAME TABLE usuarios TO users');

        foreach (self::COLUMNS as $old => $new) {
            $db->execMigration(
                "ALTER TABLE users RENAME COLUMN {$old} TO {$new}"
            );
        }

        foreach (self::INDEXES as $old => $new) {
            $db->execMigration(
                "ALTER TABLE users RENAME INDEX {$old} TO {$new}"
            );
        }

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME COLUMN usuario_id TO user_id'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME COLUMN nome TO name'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME INDEX idx_api_tokens_usuario_id TO idx_api_tokens_user_id'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens ADD CONSTRAINT fk_api_tokens_user_id '
            . 'FOREIGN KEY (user_id) REFERENCES users(id) '
            . 'ON DELETE CASCADE ON UPDATE RESTRICT'
        );
    }

    public function down(): void
    {
        $db = Database::getInstance();

        $db->execMigration(
            'ALTER TABLE api_tokens DROP FOREIGN KEY fk_api_tokens_user_id'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME INDEX idx_api_tokens_user_id TO idx_api_tokens_usuario_id'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME COLUMN user_id TO usuario_id'
        );

        $db->execMigration(
            'ALTER TABLE api_tokens RENAME COLUMN name TO nome'
        );

        foreach (array_reverse(self::INDEXES, true) as $old => $new) {
            $db->execMigration(
                "ALTER TABLE users RENAME INDEX {$new} TO {$old}"
            );
        }

        foreach (array_reverse(self::COLUMNS, true) as $old => $new) {
            $db->execMigration(
                "ALTER TABLE users RENAME COLUMN {$new} TO {$old}"
            );
        }

        $db->execMigration('RENAME TABLE users TO usuarios');

        $db->execMigration(
            'ALTER TABLE api_tokens ADD CONSTRAINT fk_api_tokens_usuario_id '
            . 'FOREIGN KEY (usuario_id) REFERENCES usuarios(id) '
            . 'ON DELETE CASCADE ON UPDATE RESTRICT'
        );
    }
};
