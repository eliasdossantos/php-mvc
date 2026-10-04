<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Migrator;
use Cli\Output;

/**
 * migrate:rollback — desfaz o batch mais recente ou o batch indicado por --step.
 *
 * Uso:
 *   php mvc migrate:rollback          desfaz o maior batch existente
 *   php mvc migrate:rollback --step=3 desfaz exatamente batch = 3
 */
class MigrateRollbackCommand extends Command
{
    public function handle(): bool
    {
        $batch = null;
        if ($this->hasOption('step')) {
            $raw = $this->option('step');
            $validated = is_string($raw) || is_int($raw)
                ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;

            if ($validated === false) {
                Output::error('A opção --step deve receber o número positivo de um batch, por exemplo --step=2.');
                return false;
            }
            $batch = $validated;
            Output::info("Desfazendo exatamente o batch {$batch}…");
        } else {
            Output::info('Desfazendo o último batch existente…');
        }
        Output::newline();

        $migrator = new Migrator();
        $lines = [];
        $result = $migrator->rollback($batch, static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        foreach ($lines as $line) {
            if ($batch !== null && !$result['found'] && str_contains($line, 'Nenhum batch de migrations encontrado')) {
                Output::warn($line);
            } else {
                Output::line($line);
            }
        }

        if ($batch !== null && !$result['found']) {
            return false;
        }

        Output::newline();
        Output::success("{$result['rolled_back']} migration(s) desfeita(s).");

        return true;
    }
}
