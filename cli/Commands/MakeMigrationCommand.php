<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:migration — Gera um arquivo de migration PHP (Core\Migration)
 *
 * Uso:
 *   php mvc make:migration CreatePostsTable
 *   php mvc make:migration AddEmailToUsers
 *   php mvc make:migration create_posts_table    ← snake_case é convertido
 *
 * Cria:
 *   database/migrations/2026_09_20_143000_create_posts_table.php
 *   database/migrations/2026_09_20_143512_add_email_to_users.php
 *
 * O prefixo é um timestamp (YYYY_MM_DD_HHMMSS), não mais um número
 * sequencial — é ele quem garante a ordem de execução no Migrator, e evita
 * conflito de numeração quando duas pessoas criam migrations em paralelo.
 */
class MakeMigrationCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome da migration.');
            Output::line('  Uso: <comment>php mvc make:migration CreatePostsTable</comment>');
            return false;
        }

        // Converte a entrada em snake_case
        [$className] = $this->parseNameAndNamespace($input);
        $migrationName = $this->toSnakeCase($className);

        // Gera o nome do arquivo: 2026_09_20_143000_create_posts_table.php
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$migrationName}.php";
        $destPath = ROOT_PATH . '/database/migrations/' . $fileName;

        // Descrição mais legível para o comentário (ex: "Create Posts Table")
        $description = $this->formatDescription($className);

        // Nome da tabela (simplificado: remove Create/Drop/Add prefixos),
        // convertido pra snake_case pra funcionar com nomes compostos
        // (ex: OrderItems → order_items, não "orderitems").
        $tableName = $this->extractTableName($className);
        $tableNameLower = $this->toSnakeCase($tableName);

        Output::info("Gerando migration <comment>{$fileName}</comment>…");

        $this->generateFile('migration', $destPath, [
            '{{ Description }}'    => $description,
            '{{ tableNameLower }}' => $tableNameLower,
        ]);

        Output::success("Migration criada: <info>database/migrations/{$fileName}</info>");
        Output::newline();
        Output::line("Execute para aplicar:");
        Output::dim("  php mvc migrate");

        return true;
    }

    /**
     * Formata o nome da migration para a descrição.
     * Ex: CreatePostsTable → Create Posts Table
     */
    private function formatDescription(string $className): string
    {
        // Insere espaço antes de maiúsculas
        $formatted = preg_replace('/([A-Z])/', ' $1', $className);
        // Remove espaço do início
        return trim($formatted);
    }

    /**
     * Extrai o nome da tabela do nome da migration.
     * Ex: CreatePostsTable → Posts
     *     AddEmailToUsers → Users
     *     DropPostsTable → Posts
     */
    private function extractTableName(string $className): string
    {
        // Remove prefixos comuns
        $tableName = preg_replace('/^(Create|Add|Drop|Alter|Remove|Rename)/', '', $className);

        // Remove sufixos comuns
        $tableName = preg_replace('/(Table|Column|Index|Constraint)$/', '', $tableName);

        // Se termina com "To" seguido de palavra (ex: AddEmailToUsers), pega a última palavra
        if (preg_match('/To([A-Z]\w+)$/', $tableName, $matches)) {
            $tableName = $matches[1];
        }

        return $tableName ?: 'Table';
    }
}
