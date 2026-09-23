<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Finite SMTP timeout for Symfony Mailer SocketStream. Must stay
            // below PHP max_execution_time so invitation mail failures reach
            // the one-time fallback handler instead of a public 500.
            // Production: MAIL_SMTP_TIMEOUT=8
            'timeout' => \App\Support\MailTransport::smtpTimeoutSeconds(env('MAIL_SMTP_TIMEOUT', 8)),
        ],
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'namaste@vyomikaatelier.com'),
        'name' => env('MAIL_FROM_NAME', 'Vyomika Atelier'),
    ],
];
