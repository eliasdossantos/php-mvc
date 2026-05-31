<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:seed — Gera um Seeder com estrutura funcional
 *
 * Uso:
 *   php mvc make:seed ProductSeeder
 *   php mvc make:seed Product        ← sufixo Seeder adicionado automaticamente
 *
 * Cria:
 *   database/seeds/ProductSeeder.php
 */
class MakeSeedCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do seeder.');
            Output::line('  Uso: <comment>php mvc make:seed ProductSeeder</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        // Garante sufixo Seeder
        if (!str_ends_with($className, 'Seeder')) {
            $className .= 'Seeder';
        }

        $modelName = preg_replace('/Seeder$/', '', $className);
        $tableName = $this->toTableName($modelName);
        $destPath  = ROOT_PATH . '/database/seeds/' . $className . '.php';

        Output::info("Gerando seeder <comment>{$className}</comment>…");

        $this->generateFile('seed', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
            '{{ tableName }}' => $tableName,
        ]);

        Output::success("Seeder criado: <info>database/seeds/{$className}.php</info>");
        Output::newline();
        Output::line("Para executar:");
        Output::dim("  php database/seeds/{$className}.php");

        return true;
    }
}
