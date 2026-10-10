<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:repository — Gera um Repository com buscas customizadas prontas
 *
 * Uso:
 *   php mvc make:repository ProductRepository
 *   php mvc make:repository Product                       ← sufixo Repository adicionado automaticamente
 *   php mvc make:repository Catalogo/ProductRepository    ← dentro de subpasta
 *
 * Cria:
 *   app/Repositories/ProductRepository.php
 *   app/Repositories/Catalogo/ProductRepository.php
 */
class MakeRepositoryCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do repository.');
            Output::line('  Uso: <comment>php mvc make:repository ProductRepository</comment>');
            Output::line('       <comment>php mvc make:repository Catalogo/ProductRepository</comment>');
            return false;
        }

        // Aceita "/" e "\" como separador de pasta
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($input))),
            fn($s) => $s !== ''
        ));

        if (empty($segments)) {
            Output::error('Informe o nome do repository.');
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

        // Garante sufixo Repository
        if (!str_ends_with($className, 'Repository')) {
            $className .= 'Repository';
        }

        $modelName    = preg_replace('/Repository$/', '', $className);
        $tableName    = $this->toTableName($modelName);
        $subPath      = $folders ? implode('/', $folders) . '/' : '';
        $namespace    = 'App\\Repositories' . ($folders ? '\\' . implode('\\', $folders) : '');
        $fullClass    = $namespace . '\\' . $className;
        $relativePath = 'app/Repositories/' . $subPath . $className . '.php';
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

        Output::info("Gerando repository <comment>{$className}</comment>…");

        $this->generateFile('repository', $destPath, [
            '{{ ClassName }}' => $className,
            '{{ ModelName }}' => $modelName,
            '{{ tableName }}' => $tableName,
            '{{ Namespace }}' => $namespace,
        ]);

        Output::success("Repository criado: <info>{$relativePath}</info>");
        Output::newline();
        Output::line("Uso no controller:");
        Output::dim("  use {$fullClass};");
        Output::newline();
        Output::line("Certifique-se de que o model existe:");
        Output::dim("  php mvc make:model {$modelName}");

        return true;
    }
}