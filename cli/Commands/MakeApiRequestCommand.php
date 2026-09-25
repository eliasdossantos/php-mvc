<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:api-request — Gera um FormRequest específico da API em app/Api/Requests
 *
 * Uso:
 *   php mvc make:api-request StoreProdutoRequest
 *
 * Cria: app/Api/Requests/StoreProdutoRequest.php
 * Lembrete: se um FormRequest equivalente já existe em app/Requests/ para o
 * formulário web, prefira reaproveitá-lo em vez de duplicar.
 */
class MakeApiRequestCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do request.');
            Output::line('  Uso: <comment>php mvc make:api-request StoreProdutoRequest</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $destPath = ROOT_PATH . '/app/Api/Requests/' . $className . '.php';

        Output::info("Gerando request de API <comment>{$className}</comment>…");

        $this->generateFile('api-request', $destPath, [
            '{{ ClassName }}' => $className,
        ]);

        Output::success("Request criado: <info>app/Api/Requests/{$className}.php</info>");

        return true;
    }
}
