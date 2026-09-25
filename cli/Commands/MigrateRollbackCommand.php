<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Migrator;
use Cli\Output;

/**
 * migrate:rollback — Desfaz o(s) último(s) batch(es) de migrations aplicados
 *
 * Uso:
 *   php mvc migrate:rollback             ← desfaz o último batch
 *   php mvc migrate:rollback --step=3    ← desfaz os últimos 3 batches
 */
class MigrateRollbackCommand extends Command
{
    public function handle(): bool
    {
        $steps = (int) $this->option('step', 1);
        $steps = max(1, $steps);

        $migrator = new Migrator();

        Output::info($steps === 1 ? 'Desfazendo o último batch…' : "Desfazendo os últimos {$steps} batches…");
        Output::newline();

        $result = $migrator->rollback($steps, function (string $line) {
            Output::line($line);
        });

        Output::newline();
        Output::success("{$result['rolled_back']} migration(s) desfeita(s).");

        return true;
    }
}
