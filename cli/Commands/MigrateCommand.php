<?php

namespace Cli\Commands;

use Cli\Command;
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
        $fresh      = $this->hasOption('fresh');
        $migrateFile = ROOT_PATH . '/database/migrate.php';

        if (!file_exists($migrateFile)) {
            Output::error('Arquivo database/migrate.php não encontrado.');
            return false;
        }

        if ($fresh) {
            Output::warn('--fresh: todas as tabelas serão removidas e recriadas!');
        }

        Output::info('Executando migrations…');
        Output::newline();

        // Passa o flag --fresh para o script de migration
        $args = $fresh ? ['mvc', '--fresh'] : ['mvc'];

        // Inclui o runner de migrations no contexto atual
        // (mantém variáveis de ambiente já carregadas)
        $argv = $args;
        require $migrateFile;

        return true;
    }
}
