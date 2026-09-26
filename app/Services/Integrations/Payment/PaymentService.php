<?php

namespace App\Services\Integrations\Payment;

use Framework\Service;
use Framework\Logger;
use App\Services\Integrations\Payment\Gateways\NullPaymentGateway;

/**
 * PaymentService — Fachada de pagamentos usada pelo resto da aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Resolve qual PaymentGatewayInterface usar a partir de config/api.php
 * ('payment.provider', vindo de PAYMENT_PROVIDER no .env) e delega.
 *
 * Uso num Controller/Service da aplicação:
 *   $result = (new PaymentService())->charge(['amount' => 100.00, 'description' => 'Pedido #42']);
 */
class PaymentService extends Service
{
    protected PaymentGatewayInterface $gateway;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        $this->gateway = $gateway ?? $this->resolveGateway();
    }

    public function charge(array $data): array
    {
        try {
            return $this->gateway->createPayment($data);
        } catch (\Throwable $e) {
            Logger::error('PaymentService: falha ao criar cobrança', ['message' => $e->getMessage()]);
            $this->fail('Não foi possível processar o pagamento no momento.');
        }
    }

    public function status(string $id): array
    {
        return $this->gateway->getPayment($id);
    }

    public function cancel(string $id): bool
    {
        return $this->gateway->cancelPayment($id);
    }

    /**
     * Escolhe o gateway configurado.
     *
     * Para adicionar Mercado Pago/Asaas/Stripe/PagSeguro: implemente a
     * interface em Gateways/, adicione o case aqui e as credenciais em
     * config/api.php + .env.
     */
    protected function resolveGateway(): PaymentGatewayInterface
    {
        $config   = require CONFIG_PATH . '/api.php';
        $provider = $config['payment']['provider'] ?? 'null';

        return match ($provider) {
            // 'mercadopago' => new Gateways\MercadoPagoGateway($config['payment']['mercadopago']),
            // 'asaas'       => new Gateways\AsaasGateway($config['payment']['asaas']),
            default => new NullPaymentGateway(),
        };
    }
}
