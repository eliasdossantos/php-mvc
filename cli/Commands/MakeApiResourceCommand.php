<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:api-resource — Gera uma transformação de Model → JSON em app/Api/Resources
 *
 * Uso:
 *   php mvc make:api-resource ProdutoResource
 *
 * Cria: app/Api/Resources/ProdutoResource.php
 */
class MakeApiResourceCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do resource.');
            Output::line('  Uso: <comment>php mvc make:api-resource ProdutoResource</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        if (!str_ends_with($className, 'Resource')) {
            $className .= 'Resource';
        }

        $destPath = ROOT_PATH . '/app/Api/Resources/' . $className . '.php';

        Output::info("Gerando resource de API <comment>{$className}</comment>…");

        $this->generateFile('api-resource', $destPath, [
            '{{ ClassName }}' => $className,
        ]);

        Output::success("Resource criado: <info>app/Api/Resources/{$className}.php</info>");
        Output::newline();
        Output::line("Uso no Controller:");
        Output::dim("  {$className}::make(\$item);        // um registro");
        Output::dim("  {$className}::collection(\$items);  // vários");

        return true;
    }
}
