<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:service — Gera um Service com estrutura base
 *
 * Uso:
 *   php mvc make:service ProductService
 *
 * Cria:
 *   app/Services/ProductService.php
 */
class MakeServiceCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do service.');
            Output::line('  Uso: <comment>php mvc make:service ProductService</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        // Garante sufixo Service
        if (!str_ends_with($className, 'Service')) {
            $className .= 'Service';
        }

        $modelName = preg_replace('/Service$/', '', $className);
        $destPath  = ROOT_PATH . '/app/Services/' . $className . '.php';

        Output::info("Gerando service <comment>{$className}</comment>…");

        $this->generateFile('service', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
        ]);

        Output::success("Service criado: <info>app/Services/{$className}.php</info>");
        Output::newline();
        Output::line("Injeção no controller:");
        Output::dim("  private {$className} \$service;");
        Output::dim("  public function __construct() {");
        Output::dim("      \$this->service = new {$className}();");
        Output::dim("  }");

        return true;
    }
}
