<?php

namespace App\Services\Integrations\Cep;

/**
 * CepGatewayInterface — Contrato para consulta de CEP brasileiro
 */
interface CepGatewayInterface
{
    /**
     * Consulta um CEP e retorna o endereço normalizado, ou null se não encontrado.
     * @return array{cep:string, logradouro:string, bairro:string, cidade:string, uf:string}|null
     */
    public function consultar(string $cep): ?array;
}
