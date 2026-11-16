<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default ZATCA environment
    |--------------------------------------------------------------------------
    |
    | sandbox    - Developer portal (no Fatoora account needed, OTP 123345)
    | simulation - Fatoora simulation portal (real OTP from fatoora.zatca.gov.sa)
    | production - Live core environment
    |
    */

    'env' => env('ZATCA_ENV', 'sandbox'),

    'sandbox_otp' => env('ZATCA_SANDBOX_OTP', '123345'),

    'environments' => [
        // seller_vat / buyer_vat: fixed test VAT numbers. Where set, the seller (software) VAT on the
        // ZATCA page and the buyer (customer) VAT on every invoice are filled in and locked to them.
        // Where null (simulation, production), both are entered by hand.
        'sandbox' => [
            'label' => 'Sandbox (Developer Portal)',
            'url' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
            'certificate_template' => 'TSTZATCA-Code-Signing',
            'seller_vat' => env('ZATCA_SANDBOX_SELLER_VAT', '399999999900003'),
            'buyer_vat' => env('ZATCA_SANDBOX_BUYER_VAT', '311111111111113'),
        ],
        'simulation' => [
            'label' => 'Simulation',
            'url' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
            'certificate_template' => 'PREZATCA-Code-Signing',
            'seller_vat' => null,
            'buyer_vat' => null,
        ],
        'production' => [
            'label' => 'Production',
            'url' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
            'certificate_template' => 'ZATCA-Code-Signing',
            'seller_vat' => null,
            'buyer_vat' => null,
        ],
    ],

    'timeout' => env('ZATCA_TIMEOUT', 60),

];
