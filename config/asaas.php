<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Asaas API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Asaas payment gateway integration.
    | Supports both sandbox and production environments.
    |
    */

    'api_key' => env('ASAAS_API_KEY', ''),

    'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'),

    'endpoints' => [
        'sandbox' => 'https://sandbox.asaas.com/api/v3',
        'production' => 'https://api.asaas.com/v3',
    ],

    'timeout' => env('ASAAS_TIMEOUT', 30),

    'connect_timeout' => env('ASAAS_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        'secret' => env('ASAAS_WEBHOOK_SECRET', ''),
        'path' => '/webhooks/asaas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        // Gerar PIX dinâmico automaticamente para cada cobrança
        'generate_pix' => env('ASAAS_GENERATE_PIX', true),

        // Tipo de cobrança: PIX, BOLETO, ou CREDIT_CARD
        'billing_type' => env('ASAAS_BILLING_TYPE', 'PIX'),

        // Enviar email de notificação automaticamente
        'notify_customer' => env('ASAAS_NOTIFY_CUSTOMER', true),

        // Reemitir boletos vencidos automaticamente
        'auto_reissue_overdue' => env('ASAAS_AUTO_REISSUE_OVERDUE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'enabled' => env('ASAAS_RETRY_ENABLED', true),
        'max_attempts' => env('ASAAS_RETRY_MAX_ATTEMPTS', 3),
        'delay_seconds' => env('ASAAS_RETRY_DELAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'sync' => [
        // Sincronizar pagamentos automaticamente
        'payments_enabled' => env('ASAAS_SYNC_PAYMENTS_ENABLED', true),

        // Frequência de sincronização em minutos
        'frequency_minutes' => env('ASAAS_SYNC_FREQUENCY', 360), // 6 hours
    ],
];
