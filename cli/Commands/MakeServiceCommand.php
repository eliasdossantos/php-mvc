<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:service — Gera um Service com estrutura base
 *
 * Uso:
 *   php mvc make:service ProductService
 *   php mvc make:service Documentos/CpfService
 *
 * Cria:
 *   app/Services/ProductService.php
 *   app/Services/Documentos/CpfService.php
 */
class MakeServiceCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do service.');
            Output::line('  Uso: <comment>php mvc make:service ProductService</comment>');
            Output::line('       <comment>php mvc make:service Documentos/CpfService</comment>');
            return false;
        }

        // Aceita "/" e "\" como separador de pasta
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($input))),
            fn($s) => $s !== ''
        ));

        // Cada segmento precisa ser um identificador PHP válido (namespace/classe)
        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment)) {
                Output::error("Nome inválido: <comment>{$segment}</comment>");
                Output::line('  Use apenas letras, números e _, separando pastas com /');
                return false;
            }
        }

        $className = ucfirst(array_pop($segments));
        $folders   = array_map('ucfirst', $segments);

        // Garante sufixo Service
        if (!str_ends_with($className, 'Service')) {
            $className .= 'Service';
        }

        $modelName    = preg_replace('/Service$/', '', $className);
        $subPath      = $folders ? implode('/', $folders) . '/' : '';
        $namespace    = 'App\\Services' . ($folders ? '\\' . implode('\\', $folders) : '');
        $fullClass    = $namespace . '\\' . $className;
        $relativePath = 'app/Services/' . $subPath . $className . '.php';
        $destPath     = ROOT_PATH . '/' . $relativePath;

        if (file_exists($destPath)) {
            Output::error("O arquivo já existe: <comment>{$relativePath}</comment>");
            return false;
        }

        // Cria a(s) pasta(s) se ainda não existirem
        $destDir = dirname($destPath);
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            Output::error("Não foi possível criar a pasta: <comment>" . dirname($relativePath) . "</comment>");
            return false;
        }

        Output::info("Gerando service <comment>{$className}</comment>…");

        $this->generateFile('service', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
            '{{ Namespace }}' => $namespace,
        ]);

        Output::success("Service criado: <info>{$relativePath}</info>");
        Output::newline();
        Output::line("Injeção no controller:");
        Output::dim("  use {$fullClass};");
        Output::newline();
        Output::dim("  private {$className} \$service;");
        Output::dim("  public function __construct() {");
        Output::dim("      \$this->service = new {$className}();");
        Output::dim("  }");

        return true;
    }
}
