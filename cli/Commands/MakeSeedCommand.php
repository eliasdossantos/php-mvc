<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:seed — Gera um Seeder de dados em database/seeds.
 *
 * Uso:
 *   php mvc make:seed ProductSeeder
 *   php mvc make:seed Product        o sufixo Seeder é adicionado automaticamente
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

        if (!str_ends_with($className, 'Seeder')) {
            $className .= 'Seeder';
        }

        $modelName = preg_replace('/Seeder$/', '', $className);
        $tableName = $this->toTableName($modelName);
        $destPath  = ROOT_PATH . '/database/seeds/' . $className . '.php';

        Output::info("Gerando seeder <comment>{$className}</comment>…");

        $created = $this->generateFile('seed', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
            '{{ tableName }}' => $tableName,
        ]);

        if (!$created) {
            return false;
        }

        Output::success("Seeder criado: <info>database/seeds/{$className}.php</info>");
        Output::newline();
        Output::line('Adicione a classe ao array retornado por database/seeds/DatabaseSeeder.php para controlar a ordem.');
        Output::line("  php mvc seed:run {$className}   (somente este Seeder)");
        Output::line('  php mvc seed                    (executa o Seeder principal)');

        return true;
    }
}
