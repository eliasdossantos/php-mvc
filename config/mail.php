<?php

/**
 * Configuração de E-mail
 *
 * Define o driver e as configurações utilizadas pelo sistema de envio
 * de e-mails.
 *
 * Drivers disponíveis:
 *
 *   dev
 *     Não envia o e-mail. Gera uma URL para visualização/teste
 *     da mensagem.
 *
 *   log
 *     Não envia o e-mail. Registra a mensagem em
 *     storage/logs/mail.log.
 *
 *   smtp
 *     Envia o e-mail através de um servidor SMTP.
 */
return [
    'driver'     => strtolower((string)(env('MAIL_DRIVER', 'dev'))),
    'from_name'  => (string)(env('MAIL_FROM_NAME', env('APP_NAME', 'PHP MVC App'))),
    'from_email' => (string)(env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', 'noreply@example.com'))),
    'app_url'    => (string)(env('APP_URL', '')),
    'smtp'       => [
        'host'       => (string)(env('MAIL_HOST', 'smtp.mailtrap.io')),
        'port'       => (int)(env('MAIL_PORT', 587)),
        'username'   => (string)(env('MAIL_USERNAME', '')),
        'password'   => (string)(env('MAIL_PASSWORD', '')),
        'encryption' => strtolower((string)(env('MAIL_ENCRYPTION', 'tls'))),
    ],
];