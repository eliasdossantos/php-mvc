<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Migrator;
use Cli\Output;

/**
 * migrate — Executa as migrations SQL do projeto
 *
 * Uso:
 *   php mvc migrate             ← executa migrations pendentes
 *   php mvc migrate --fresh     ← DROP ALL + recria tudo (⚠ cuidado em produção)
 */
class MigrateCommand extends Command
{
    public function handle(): bool
    {
        $fresh = $this->hasOption('fresh');

        if ($fresh) {
            Output::warn('--fresh: todas as tabelas serão removidas e recriadas!');
        }

        Output::info('Executando migrations…');
        Output::newline();

        $migrator = new Migrator();
        $result   = $migrator->run($fresh, function (string $line) {
            Output::line($line);
        });

        Output::newline();
        Output::success("{$result['ran']} migration(s) executada(s), {$result['skipped']} ignorada(s).");

        return true;
    }
}
