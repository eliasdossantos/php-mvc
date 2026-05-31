<?php

/**
 * Configuração de E-mail
 * driver = 'dev'  → não envia, retorna URL no payload JSON (testes)
 * driver = 'log'  → grava em storage/logs/mail.log
 * driver = 'smtp' → envia via SMTP (PHPMailer ou implementação nativa)
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
