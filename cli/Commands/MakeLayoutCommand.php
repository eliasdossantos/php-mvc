<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:layout — Gera um layout de página
 *
 * Uso:
 *   php mvc make:layout blank
 *   php mvc make:layout admin/print   ← sub-pasta
 *
 * Cria:
 *   resources/views/layouts/blank.php
 *
 * O stub já vem pronto com View::title()/View::content() e as sections
 * 'styles'/'scripts', então o layout gerado funciona tanto com views que
 * usam View::start('content')/View::end() quanto com views de HTML solto
 * — sem precisar lembrar qual dos dois estilos este layout espera.
 */
class MakeLayoutCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do layout.');
            Output::line('  Uso: <comment>php mvc make:layout blank</comment>');
            return false;
        }

        // Normaliza para snake_case/lowercase preservando sub-pastas
        $normalized = str_replace('\\', '/', $input);
        $parts      = explode('/', $normalized);

        $layoutName = strtolower($this->toSnakeCase(array_pop($parts)));
        $subDir     = $parts ? implode('/', $parts) . '/' : '';

        $layoutPath = $subDir . $layoutName;                         // 'admin/print'
        $destPath   = ROOT_PATH . '/resources/views/layouts/' . $layoutPath . '.php';

        Output::info("Gerando layout <comment>{$layoutName}</comment>…");

        $created = $this->generateFile('layout', $destPath, [
            '{{ LayoutName }}' => $layoutName,
        ]);

        if ($created) {
            Output::success("Layout criado: <info>resources/views/layouts/{$layoutPath}.php</info>");
            Output::newline();
            Output::line("Use no controller:");
            Output::dim("  \$this->view('pasta.view', \$data, '{$layoutPath}');");
        }

        return true;
    }
}
