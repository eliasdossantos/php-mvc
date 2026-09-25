<?php

namespace App\Services\Integrations\Cep;

use Core\Service;
use App\Services\Integrations\Cep\Gateways\ViaCepGateway;

/**
 * CepService — Fachada de consulta de CEP usada pelo resto da aplicação
 *
 * Uso:
 *   $endereco = (new CepService())->buscar('01001-000');
 *   if ($endereco === null) { ... } // CEP inválido ou não encontrado
 */
class CepService extends Service
{
    protected CepGatewayInterface $gateway;

    public function __construct(?CepGatewayInterface $gateway = null)
    {
        $this->gateway = $gateway ?? $this->resolveGateway();
    }

    public function buscar(string $cep): ?array
    {
        return $this->gateway->consultar($cep);
    }

    protected function resolveGateway(): CepGatewayInterface
    {
        $config   = require CONFIG_PATH . '/api.php';
        $provider = $config['cep']['provider'] ?? 'viacep';

        return match ($provider) {
            default => new ViaCepGateway(),
        };
    }
}
