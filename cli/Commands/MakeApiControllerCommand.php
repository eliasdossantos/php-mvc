<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * make:api-controller — Gera um Controller REST em app/Api/Controllers
 *
 * Uso:
 *   php mvc make:api-controller ProdutoApiController
 *
 * Cria: app/Api/Controllers/ProdutoApiController.php
 * Espera que exista (ou você crie em seguida): App\Repositories\ProdutoRepository
 * e App\Api\Resources\ProdutoResource (veja make:api-resource).
 */
class MakeApiControllerCommand extends Command
{
    public function handle(): bool
    {
        $input = $this->arg(0);

        if (!$input) {
            Output::error('Informe o nome do controller.');
            Output::line('  Uso: <comment>php mvc make:api-controller ProdutoApiController</comment>');
            return false;
        }

        [$className] = $this->parseNameAndNamespace($input);

        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $modelName   = preg_replace('/ApiController$|Controller$/', '', $className);
        $routePrefix = $this->toRoutePrefix($modelName);
        $destPath    = ROOT_PATH . '/app/Api/Controllers/' . $className . '.php';

        Output::info("Gerando controller de API <comment>{$className}</comment>…");

        $this->generateFile('api-controller', $destPath, [
            '{{ ClassName }}'   => $className,
            '{{ ModelName }}'   => $modelName,
            '{{ routePrefix }}' => $routePrefix,
        ]);

        Output::success("Controller criado: <info>app/Api/Controllers/{$className}.php</info>");
        Output::newline();
        Output::line("Registre as rotas em <comment>routes/api.php</comment>:");
        Output::dim("  \$r->get('/{$routePrefix}',      [{$className}::class, 'index'],   ['ApiAuthMiddleware']);");
        Output::dim("  \$r->get('/{$routePrefix}/{id}',  [{$className}::class, 'show'],    ['ApiAuthMiddleware']);");
        Output::dim("  \$r->post('/{$routePrefix}',      [{$className}::class, 'store'],   ['ApiAuthMiddleware']);");
        Output::dim("  \$r->put('/{$routePrefix}/{id}',  [{$className}::class, 'update'],  ['ApiAuthMiddleware']);");
        Output::dim("  \$r->delete('/{$routePrefix}/{id}', [{$className}::class, 'destroy'], ['ApiAuthMiddleware']);");

        return true;
    }
}
