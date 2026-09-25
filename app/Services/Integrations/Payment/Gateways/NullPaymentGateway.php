<?php

namespace App\Services\Integrations\Payment\Gateways;

use App\Services\Integrations\Payment\PaymentGatewayInterface;

/**
 * NullPaymentGateway — Gateway "nulo" usado quando PAYMENT_PROVIDER=null
 * ─────────────────────────────────────────────────────────────────────────────
 * Não faz nenhuma chamada externa — apenas simula respostas previsíveis.
 * Útil como padrão seguro (nenhuma cobrança real acontece por engano em
 * ambiente sem gateway configurado) e como base para testes automatizados,
 * sem precisar mockar HTTP.
 *
 * Gateways reais (Mercado Pago, Asaas, Stripe, PagSeguro...) devem ser
 * adicionados aqui mesmo, implementando PaymentGatewayInterface e usando
 * Core\Api\ApiClient para a comunicação HTTP — nenhum deles está implementado
 * ainda; esta arquitetura só prepara o terreno.
 */
class NullPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(array $data): array
    {
        return [
            'id'     => 'null_' . bin2hex(random_bytes(8)),
            'status' => 'simulado',
            'amount' => $data['amount'] ?? 0,
        ];
    }

    public function getPayment(string $id): array
    {
        return ['id' => $id, 'status' => 'simulado'];
    }

    public function cancelPayment(string $id): bool
    {
        return true;
    }
}
