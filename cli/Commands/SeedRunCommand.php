<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;
use Cli\SeedRunner;

/** Executa todos os Seeders de database/seeds ou somente o nome informado. */
class SeedRunCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);
        $runner = new SeedRunner();

        if ($input === null) {
            Output::info('Executando Seeders de database/seeds…');
            Output::newline();
            $result = $runner->runAll(static function (string $line): void {
                Output::line($line);
            });
            if ($result['seeders'] === 0) {
                Output::warn('Nenhum Seeder encontrado (DatabaseSeeder.php é ignorado neste comando).');
                return false;
            }
        } else {
            Output::info("Executando Seeder <comment>{$input}</comment>…");
            Output::newline();
            $result = $runner->runOne($input, static function (string $line): void {
                Output::line($line);
            });
        }

        Output::newline();
        Output::success(sprintf(
            '%d Seeder(s) executado(s) com sucesso.',
            $result['seeders']
        ));

        return true;
    }
}
