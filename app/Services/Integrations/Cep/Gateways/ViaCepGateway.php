<?php

namespace App\Services\Integrations\Cep\Gateways;

use Core\Api\ApiClient;
use Core\Api\ApiException;
use Core\Logger;
use App\Services\Integrations\Cep\CepGatewayInterface;

/**
 * ViaCepGateway — Implementação concreta usando a API pública ViaCEP
 * ─────────────────────────────────────────────────────────────────────────────
 * Serve como exemplo de referência real do fluxo:
 *   Service → Integration (Gateway) → Core\Api\ApiClient → API externa
 * para as próximas integrações (Mapas, WhatsApp, IA, etc.) — copie este
 * padrão.
 */
class ViaCepGateway implements CepGatewayInterface
{
    protected ApiClient $client;

    public function __construct()
    {
        $this->client = new ApiClient('https://viacep.com.br/ws', timeout: 10);
    }

    public function consultar(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($cep) !== 8) {
            return null;
        }

        try {
            $res = $this->client->get("/{$cep}/json", ['throw_on_error' => false]);
        } catch (ApiException $e) {
            Logger::error('ViaCepGateway: falha ao consultar CEP', ['cep' => $cep, 'message' => $e->getMessage()]);
            return null;
        }

        $json = $res['json'];
        if (!$res['ok'] || !$json || !empty($json['erro'])) {
            return null;
        }

        return [
            'cep'        => $json['cep']         ?? $cep,
            'logradouro' => $json['logradouro']  ?? '',
            'bairro'     => $json['bairro']      ?? '',
            'cidade'     => $json['localidade']  ?? '',
            'uf'         => $json['uf']          ?? '',
        ];
    }
}
