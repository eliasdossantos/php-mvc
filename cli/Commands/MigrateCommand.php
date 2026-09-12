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
        $fresh    = $this->hasOption('fresh');
        $migrator = new Migrator();

        if ($fresh) {
            // Precisa do bootstrap ANTES de decidir se pede confirmação,
            // porque APP_ENV e a config do banco só existem depois dele rodar.
            $migrator->bootstrapEnvironment();

            if (APP_ENV === 'production' && !$this->hasOption('force')) {
                Output::error('--fresh em produção requer a flag --force explícita.');
                Output::line('  Isso existe para evitar apagar dados de produção por engano.');
                return false;
            }

            if (!$this->hasOption('yes')) {
                $dbName = $migrator->currentDatabaseName();

                Output::warn("Isso vai APAGAR TODAS AS TABELAS do banco \"{$dbName}\"! Esta ação não pode ser desfeita.");
                $confirm = $this->ask('Digite o nome do banco para confirmar: ');

                if ($confirm !== $dbName) {
                    Output::error('Confirmação não corresponde ao nome do banco. Operação cancelada.');
                    return false;
                }
            }
        }

        Output::info('Executando migrations…');
        Output::newline();

        $result = $migrator->run($fresh, function (string $line) {
            Output::line($line);
        });

        Output::newline();
        Output::success("{$result['ran']} migration(s) executada(s), {$result['skipped']} ignorada(s).");

        return true;
    }
}
