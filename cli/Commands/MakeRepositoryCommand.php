<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:repository — Gera um Repository com buscas customizadas prontas
 *
 * Uso:
 *   php mvc make:repository ProductRepository
 *   php mvc make:repository Product            ← sufixo Repository adicionado automaticamente
 *
 * Cria:
 *   app/Repositories/ProductRepository.php
 */
class MakeRepositoryCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do repository.');
            Output::line('  Uso: <comment>php mvc make:repository ProductRepository</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        // Garante sufixo Repository
        if (!str_ends_with($className, 'Repository')) {
            $className .= 'Repository';
        }

        $modelName = preg_replace('/Repository$/', '', $className);
        $tableName = $this->toTableName($modelName);
        $destPath  = ROOT_PATH . '/app/Repositories/' . $className . '.php';

        Output::info("Gerando repository <comment>{$className}</comment>…");

        $this->generateFile('repository', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
            '{{ tableName }}' => $tableName,
        ]);

        Output::success("Repository criado: <info>app/Repositories/{$className}.php</info>");
        Output::newline();
        Output::line("Certifique-se de que o model existe:");
        Output::dim("  php mvc make:model {$modelName}");

        return true;
    }
}
