<?php

namespace App\Services\Integrations\Payment;

/**
 * PaymentGatewayInterface — Contrato comum a qualquer gateway de pagamento
 * ─────────────────────────────────────────────────────────────────────────────
 * PaymentService nunca conhece Mercado Pago, Asaas, Stripe etc. diretamente —
 * só esta interface. Trocar de gateway (ou usar mais de um) é questão de
 * implementar uma nova classe e apontar PAYMENT_PROVIDER no .env.
 *
 * Cada gateway concreto vive em Gateways/ (ex: Gateways/MercadoPagoGateway.php)
 * e usa Core\Api\ApiClient internamente para as chamadas HTTP — nunca curl
 * direto.
 */
interface PaymentGatewayInterface
{
    /**
     * Cria uma cobrança/pagamento.
     * @param array $data Dados específicos do gateway (valor, descrição, cliente, etc.)
     * @return array Resposta normalizada: ['id' => string, 'status' => string, ...]
     */
    public function createPayment(array $data): array;

    /** Consulta o status atual de um pagamento pelo ID no gateway */
    public function getPayment(string $id): array;

    /** Cancela/estorna um pagamento */
    public function cancelPayment(string $id): bool;
}
