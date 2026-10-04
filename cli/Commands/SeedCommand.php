<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;
use Cli\SeedRunner;

/** Executa a sequência ordenada definida em database/seeds/DatabaseSeeder.php. */
class SeedCommand extends Command
{
    public function handle(): bool
    {
        Output::info('Executando o Seeder principal…');
        Output::newline();

        $result = (new SeedRunner())->runMain(static function (string $line): void {
            Output::line($line);
        });

        Output::newline();
        Output::success(sprintf(
            '%d Seeder(s) executado(s) com sucesso.',
            $result['seeders']
        ));

        return true;
    }
}
