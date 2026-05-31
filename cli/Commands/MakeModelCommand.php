<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:model — Gera um Model com estrutura base
 *
 * Uso:
 *   php mvc make:model Product
 *
 * Cria:
 *   app/Models/Product.php
 */
class MakeModelCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do model.');
            Output::line('  Uso: <comment>php mvc make:model Product</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);
        $tableName   = $this->toTableName($className);
        $destPath    = ROOT_PATH . '/app/Models/' . $className . '.php';

        Output::info("Gerando model <comment>{$className}</comment>…");

        $this->generateFile('model', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ tableName }}' => $tableName,
        ]);

        Output::success("Model criado: <info>app/Models/{$className}.php</info>");
        Output::newline();
        Output::line("Próximos passos:");
        Output::dim("  1. Adicione os campos em \$fillable");
        Output::dim("  2. Crie a migration: database/migrations/xxx_create_{$tableName}_table.sql");
        Output::dim("  3. Execute: php mvc migrate");

        return true;
    }
}
