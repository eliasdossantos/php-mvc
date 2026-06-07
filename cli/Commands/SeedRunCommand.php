<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * seed:run — Executa seeders PHP em database/seeds
 *
 * Uso:
 *   php mvc seed:run           ← executa todos os seeders
 *   php mvc seed:run UserSeeder ← executa apenas o UserSeeder
 */
class SeedRunCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);
        $seedDir = ROOT_PATH . '/database/seeds';

        if (!is_dir($seedDir)) {
            Output::error('Diretório database/seeds não encontrado.');
            return false;
        }

        if ($input) {
            [$className] = $this->parseNameAndNamespace($input);
            if (!str_ends_with($className, 'Seeder')) {
                $className .= 'Seeder';
            }

            $file = $seedDir . '/' . $className . '.php';
            if (!file_exists($file)) {
                Output::error("Seeder não encontrado: {$className}");
                return false;
            }

            Output::info("Executando seeder <comment>{$className}</comment>…");
            require $file;
            return true;
        }

        $files = glob($seedDir . '/*Seeder.php');
        if (!$files) {
            Output::error('Nenhum seeder encontrado em database/seeds.');
            return false;
        }

        natsort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            Output::info("Executando seeder <comment>{$name}</comment>…");
            require $file;
        }

        return true;
    }
}
