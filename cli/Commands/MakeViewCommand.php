<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:view — Gera as 4 views CRUD de um recurso
 *
 * Uso:
 *   php mvc make:view product
 *   php mvc make:view admin/product   ← sub-pasta
 *
 * Cria:
 *   app/Views/product/index.php
 *   app/Views/product/show.php
 *   app/Views/product/create.php
 *   app/Views/product/edit.php
 */
class MakeViewCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do recurso.');
            Output::line('  Uso: <comment>php mvc make:view product</comment>');
            return false;
        }

        // Normaliza para snake_case/lowercase preservando sub-pastas
        $normalized = str_replace('\\', '/', $input);
        $parts      = explode('/', $normalized);

        // Último segmento → nome do recurso
        $resourceName = strtolower($this->toSnakeCase(array_pop($parts)));
        $subDir       = $parts ? implode('/', $parts) . '/' : '';

        $viewPath    = $subDir . $resourceName;                    // 'admin/product'
        $modelName   = $this->toPascalCase($resourceName);        // 'Product'
        $routePrefix = str_replace('/', '/', $viewPath);           // 'admin/product'

        $views = ['index', 'show', 'create', 'edit'];
        $baseDir = ROOT_PATH . '/app/Views/' . $viewPath;

        Output::info("Gerando views para <comment>{$modelName}</comment>…");

        $ok = 0;
        foreach ($views as $view) {
            $destPath = $baseDir . '/' . $view . '.php';

            $created = $this->generateFile("view.{$view}", $destPath, [
                '{{ ClassName }}'    => $modelName,
                '{{ ModelName }}'    => $modelName,
                '{{ viewPath }}'     => $viewPath,
                '{{ routePrefix }}'  => $routePrefix,
            ]);

            if ($created) $ok++;
        }

        if ($ok > 0) {
            Output::success("{$ok} view(s) criadas em: <info>app/Views/{$viewPath}/</info>");
            Output::newline();
            Output::line("Renderize no controller:");
            Output::dim("  \$this->view('{$resourceName}.index', \$data);");
        }

        return true;
    }
}
