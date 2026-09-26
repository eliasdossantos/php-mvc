<?php

/**
 * Configuração da API (/api/v1)
 * Todos os valores sensíveis/variáveis vêm do .env — nunca hardcoded aqui.
 */
return [
    // Timeout padrão (segundos) para chamadas feitas via Framework\Api\ApiClient
    'timeout' => (int) ($_ENV['API_TIMEOUT'] ?? 30),

    'cors' => [
        // '*' apenas em desenvolvimento. Em produção, liste as origens
        // explicitamente: CORS_ALLOWED_ORIGINS=https://app.site.com,https://admin.site.com
        'allowed_origins' => array_filter(array_map(
            'trim',
            explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')
        )),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token', 'Accept'],
    ],

    // Gateway de pagamento ativo (ver app/Services/Integrations/Payment/)
    'payment' => [
        'provider' => $_ENV['PAYMENT_PROVIDER'] ?? 'null',
        'mercadopago' => [
            'access_token' => $_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? '',
            'public_key'   => $_ENV['MERCADOPAGO_PUBLIC_KEY']   ?? '',
        ],
        'asaas' => [
            'api_key'     => $_ENV['ASAAS_API_KEY']     ?? '',
            'environment' => $_ENV['ASAAS_ENVIRONMENT']  ?? 'sandbox',
        ],
    ],

    // Integração de CEP ativa (ver app/Services/Integrations/Cep/)
    'cep' => [
        'provider' => $_ENV['CEP_PROVIDER'] ?? 'viacep',
    ],
];
