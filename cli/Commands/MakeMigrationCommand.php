<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:migration — Gera um arquivo de migration SQL
 *
 * Uso:
 *   php mvc make:migration CreatePostsTable
 *   php mvc make:migration AddEmailToUsers
 *   php mvc make:migration create_posts_table    ← snake_case é convertido
 *
 * Cria:
 *   database/migrations/002_create_posts_table.sql
 *   database/migrations/003_add_email_to_users.sql
 *
 * O número é gerado automaticamente baseado nas migrations existentes.
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

        // Encontra o próximo número de migration
        $nextNumber = $this->getNextMigrationNumber();
        $paddedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Gera o nome do arquivo: 002_create_posts_table.sql
        $fileName = "{$paddedNumber}_{$migrationName}.sql";
        $destPath = ROOT_PATH . '/database/migrations/' . $fileName;

        // Descrição mais legível para o comentário (ex: "Create Posts Table")
        $description = $this->formatDescription($className);
        
        // Nome da tabela (simplificado: remove Create/Drop/Add prefixos)
        $tableName = $this->extractTableName($className);

        Output::info("Gerando migration <comment>{$fileName}</comment>…");

        $this->generateFile('migration', $destPath, [
            '{{ Number }}'        => $paddedNumber,
            '{{ Description }}'   => $description,
            '{{ TableName }}'     => $tableName,
            '{{ tableNameLower }}' => strtolower($tableName),
        ]);

        Output::success("Migration criada: <info>database/migrations/{$fileName}</info>");
        Output::newline();
        Output::line("Execute para aplicar:");
        Output::dim("  php mvc migrate");

        return true;
    }

    /**
     * Encontra o próximo número de migration disponível.
     * Ex: se existe 001_*, 002_*, retorna 3
     */
    private function getNextMigrationNumber(): int
    {
        $migrationsDir = ROOT_PATH . '/database/migrations';

        if (!is_dir($migrationsDir)) {
            return 1;
        }

        $files = scandir($migrationsDir);
        $maxNumber = 0;

        foreach ($files as $file) {
            if (!str_ends_with($file, '.sql')) {
                continue;
            }

            // Extrai o número do início do arquivo: 001_xxx.sql → 1
            if (preg_match('/^(\d+)_/', $file, $matches)) {
                $number = (int) $matches[1];
                $maxNumber = max($maxNumber, $number);
            }
        }

        return $maxNumber + 1;
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
