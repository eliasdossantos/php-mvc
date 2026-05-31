<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:controller — Gera um Controller com métodos CRUD prontos
 *
 * Uso:
 *   php mvc make:controller UserController
 *   php mvc make:controller Api/UserController     ← sub-namespace
 *
 * Cria:
 *   app/Controllers/UserController.php
 *   app/Controllers/Api/UserController.php
 */
class MakeControllerCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do controller.');
            Output::line('  Uso: <comment>php mvc make:controller UserController</comment>');
            return false;
        }

        [$className, $subNs] = $this->parseNameAndNamespace($input);

        // Garante sufixo Controller
        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        // Deriva o nome do model (UserController → User)
        $modelName  = preg_replace('/Controller$/', '', $className);
        $routePrefix = $this->toRoutePrefix($modelName);
        $viewPath    = strtolower($this->toSnakeCase($modelName));

        // Namespace e caminho de destino
        $nsExtra   = $subNs ? '\\' . $subNs : '';
        $subDir    = $subNs ? str_replace('\\', DIRECTORY_SEPARATOR, $subNs) . DIRECTORY_SEPARATOR : '';
        $destPath  = ROOT_PATH . '/app/Controllers/' . $subDir . $className . '.php';

        Output::info("Gerando controller <comment>{$className}</comment>…");

        $this->generateFile('controller', $destPath, [
            '{{ ClassName }}'    => $className,
            '{{ SubNamespace }}' => $nsExtra,
            '{{ ModelName }}'    => $modelName,
            '{{ routePrefix }}'  => $routePrefix,
            '{{ viewPath }}'     => $viewPath,
            '{{ UseRepository }}' => '',
            '{{ UseRequest }}'    => '',
        ]);

        Output::success("Controller criado: <info>app/Controllers/{$subDir}{$className}.php</info>");
        Output::newline();
        Output::line("Registre a rota em <comment>routes/web.php</comment>:");
        Output::dim("  \$router->resource('/{$routePrefix}', {$className}::class);");

        return true;
    }
}
