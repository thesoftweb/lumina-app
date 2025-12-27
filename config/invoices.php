<?php

// config/invoices.php
// Configurações padrão para geração de invoices

return [
    /*
     * Dias padrão para vencimento de diferentes tipos de fatura
     */
    'enrollment_due_days' => 2,
    'tuition_due_days' => 10,
    'service_due_days' => 5,
    'material_due_days' => 3,
    'other_due_days' => 7,

    /*
     * Prefixos para números de referência
     */
    'prefixes' => [
        'enrollment' => 'MAT',
        'tuition' => 'TUI',
        'service' => 'SRV',
        'material' => 'MATERIAL',
        'other' => 'OTH',
    ],

    /*
     * Configurações de numeração de faturas
     */
    'number_format' => 'sequential', // 'sequential', 'yearly', 'monthly'
    'number_length' => 6, // Quantidade de dígitos para número da fatura

    /*
     * Empresa padrão para invoices
     */
    'default_company_id' => env('INVOICE_DEFAULT_COMPANY_ID', 1),
];
