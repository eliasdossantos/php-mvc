<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:model — Gera um Model com estrutura base
 *
 * Uso:
 *   php mvc make:model Product
 *   php mvc make:model Catalogo/Product     ← dentro de subpasta
 *
 * Cria:
 *   app/Models/Product.php
 *   app/Models/Catalogo/Product.php
 */
class MakeModelCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do model.');
            Output::line('  Uso: <comment>php mvc make:model Product</comment>');
            Output::line('       <comment>php mvc make:model Catalogo/Product</comment>');
            return false;
        }

        // Aceita "/" e "\" como separador de pasta
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($input))),
            fn($s) => $s !== ''
        ));

        if (empty($segments)) {
            Output::error('Informe o nome do model.');
            return false;
        }

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

        $tableName    = $this->toTableName($className);
        $subPath      = $folders ? implode('/', $folders) . '/' : '';
        $namespace    = 'App\\Models' . ($folders ? '\\' . implode('\\', $folders) : '');
        $fullClass    = $namespace . '\\' . $className;
        $relativePath = 'app/Models/' . $subPath . $className . '.php';
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

        Output::info("Gerando model <comment>{$className}</comment>…");

        $this->generateFile('model', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ tableName }}' => $tableName,
            '{{ Namespace }}' => $namespace,
        ]);

        Output::success("Model criado: <info>{$relativePath}</info>");
        Output::newline();
        Output::line("Uso:");
        Output::dim("  use {$fullClass};");
        Output::newline();
        Output::line("Próximos passos:");
        Output::dim("  1. Adicione os campos em \$fillable");
        Output::dim("  2. Crie a migration: database/migrations/xxx_create_{$tableName}_table.sql");
        Output::dim("  3. Execute: php mvc migrate");

        return true;
    }
}