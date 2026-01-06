<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RaiAccept Payment Gateway
    |--------------------------------------------------------------------------
    */
    'raiaccept' => [
        'mode' => env('RAIACCEPT_MODE', 'stub'), // stub, sandbox, production
        'client_id' => env('RAIACCEPT_CLIENT_ID'),
        'client_secret' => env('RAIACCEPT_CLIENT_SECRET'),
        'cognito_url' => env('RAIACCEPT_COGNITO_URL'),
        'api_url' => env('RAIACCEPT_API_URL', 'https://api.raiaccept.com'),
        'stub_behavior' => env('RAIACCEPT_STUB_BEHAVIOR', 'success'), // success, failure, random
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Packages
    |--------------------------------------------------------------------------
    */
    'credit_packages' => [
        [
            'id' => 'starter',
            'name' => 'Starter Pack',
            'credits' => 100,
            'price_cents' => 500,
            'currency' => 'EUR',
        ],
        [
            'id' => 'popular',
            'name' => 'Popular Pack',
            'credits' => 500,
            'price_cents' => 2000,
            'currency' => 'EUR',
            'popular' => true,
        ],
        [
            'id' => 'premium',
            'name' => 'Premium Pack',
            'credits' => 1200,
            'price_cents' => 4000,
            'currency' => 'EUR',
        ],
    ],

];
