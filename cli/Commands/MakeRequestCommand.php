<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:request — Gera um FormRequest com validações prontas
 *
 * Uso:
 *   php mvc make:request StoreProductRequest
 *   php mvc make:request Auth/LoginRequest        ← sub-pasta
 *
 * Cria:
 *   app/Requests/StoreProductRequest.php
 *   app/Requests/Auth/LoginRequest.php
 */
class MakeRequestCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do request.');
            Output::line('  Uso: <comment>php mvc make:request StoreProductRequest</comment>');
            return false;
        }

        [$className, $subNs] = $this->parseNameAndNamespace($input);

        // Garante sufixo Request
        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $nsExtra  = $subNs ? '\\' . $subNs : '';
        $subDir   = $subNs ? str_replace('\\', DIRECTORY_SEPARATOR, $subNs) . DIRECTORY_SEPARATOR : '';
        $destPath = ROOT_PATH . '/app/Requests/' . $subDir . $className . '.php';

        Output::info("Gerando request <comment>{$className}</comment>…");

        $this->generateFile('request', $destPath, [
            '{{ ClassName }}'    => $className,
            '{{ SubNamespace }}' => $nsExtra,
        ]);

        Output::success("Request criado: <info>app/Requests/{$subDir}{$className}.php</info>");
        Output::newline();
        Output::line("Uso no controller:");
        Output::dim("  \$request = new {$className}();");
        Output::dim("  if (\$request->fails()) { /* tratar erros */ }");
        Output::dim("  \$data = \$request->validated();");

        return true;
    }
}
