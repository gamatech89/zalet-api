<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Founder Emails
    |--------------------------------------------------------------------------
    |
    | Emails of users from the legacy ZALET platform who are eligible for
    | automatic Founder status verification. These users supported the
    | platform early and receive special recognition + 100 ZaletCoin bonus.
    |
    | To add emails, either:
    | 1. Add them directly to this array
    | 2. Set LEGACY_FOUNDER_EMAILS in .env as comma-separated list
    |    Example: LEGACY_FOUNDER_EMAILS="founder1@example.com,founder2@example.com"
    |
    */
    'legacy_founder_emails' => array_filter(array_merge(
        // Emails from environment variable
        env('LEGACY_FOUNDER_EMAILS') 
            ? explode(',', env('LEGACY_FOUNDER_EMAILS')) 
            : [],
        // Hardcoded emails (add real founder emails here)
        [
            // 'founder1@example.com',
            // 'founder2@example.com',
        ]
    )),

    /*
    |--------------------------------------------------------------------------
    | Storage Limits
    |--------------------------------------------------------------------------
    |
    | Default storage limits for different user tiers in megabytes.
    |
    */
    'storage_limits' => [
        'free' => 512,      // 512 MB
        'pro' => 5120,      // 5 GB
        'founder' => 10240, // 10 GB
    ],

    /*
    |--------------------------------------------------------------------------
    | Moments Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for native video uploads (Moments).
    |
    */
    'moments' => [
        'max_duration_seconds' => 60,
        'max_file_size_mb' => 100,
        'allowed_formats' => ['mp4', 'mov', 'webm'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Economy Configuration
    |--------------------------------------------------------------------------
    |
    | ZaletCoin economy settings.
    |
    */
    'economy' => [
        'min_deposit' => 5.00,
        'min_withdrawal' => 50.00,
        'withdrawal_fee_percent' => 5,
        'founder_bonus' => 100.00, // One-time bonus for legacy founders
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate
    |--------------------------------------------------------------------------
    |
    | Currency exchange rates for ZaletCoin.
    |
    */
    'exchange_rate' => [
        'rsd_to_coin' => env('EXCHANGE_RATE_RSD_TO_COIN', 1.0), // 1 RSD = 1 ZaletCoin
    ],

    /*
    |--------------------------------------------------------------------------
    | Raiffeisen Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for Raiffeisen Serbia UPC payment gateway integration.
    | See docs/raiffeisen/ for full documentation.
    |
    | WARNING: These are TEST credentials. Production credentials must be
    | stored securely in environment variables.
    |
    */
    'raiffeisen' => [
        'merchant_id' => env('RAIFFEISEN_MERCHANT_ID', '1731553'),
        'terminal_id' => env('RAIFFEISEN_TERMINAL_ID', 'E1731563'),
        'gateway_url' => env('RAIFFEISEN_GATEWAY_URL', 'https://ecg.test.upc.ua/rbrs/pay'),
        'portal_url' => env('RAIFFEISEN_PORTAL_URL', 'https://ecg.test.upc.ua/rbrs/merchant/'),
        'success_url' => env('RAIFFEISEN_SUCCESS_URL', ''),
        'failure_url' => env('RAIFFEISEN_FAILURE_URL', ''),
        'pem_path' => env('RAIFFEISEN_PEM_PATH', base_path('docs/raiffeisen/test-merchant.pem')),
        'certificate_path' => env('RAIFFEISEN_CERT_PATH', base_path('docs/raiffeisen/test-server.cert')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coin Packages
    |--------------------------------------------------------------------------
    |
    | Pre-defined ZaletCoin purchase packages. Each package has a coin amount,
    | RSD price, and optional label for UI display.
    |
    */
    'coin_packages' => [
        ['id' => 'pkg_500', 'coins' => 500, 'price_rsd' => 500, 'bonus' => 0, 'label' => null],
        ['id' => 'pkg_1000', 'coins' => 1000, 'price_rsd' => 950, 'bonus' => 50, 'label' => 'popular'],
        ['id' => 'pkg_2500', 'coins' => 2500, 'price_rsd' => 2300, 'bonus' => 200, 'label' => null],
        ['id' => 'pkg_5000', 'coins' => 5000, 'price_rsd' => 4500, 'bonus' => 500, 'label' => 'best_value'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Withdrawal Configuration
    |--------------------------------------------------------------------------
    */
    'withdrawal' => [
        'fee_percent' => env('WITHDRAWAL_FEE_PERCENT', 2),
        'min_amount' => env('WITHDRAWAL_MIN_AMOUNT', 500),
        'exchange_rate_coin_to_rsd' => env('EXCHANGE_RATE_COIN_TO_RSD', 1.2), // 1 ZC = 1.2 RSD
        'processing_days' => '1-2',
    ],
];

