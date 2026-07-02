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

    'ipgeolocation' => [
        'api_key' => env('IPGEOLOCATION_API_KEY'),
        'base_url' => env('IPGEOLOCATION_BASE_URL', 'https://api.ipgeolocation.io/v2'),
    ],

    // TRON blockchain read source for internal USDT (TRC20) deposit processing.
    // TronGrid is only a read source: it never owns invoice status or balances.
    'trongrid' => [
        'base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
        'api_key' => env('TRONGRID_API_KEY'),
        'usdt_contract' => env('TRONGRID_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
        'tronscan_base_url' => env('TRONSCAN_BASE_URL', 'https://tronscan.org'),
    ],

    // Operational thresholds for internal wallet deposit invoices.
    'wallet_deposit' => [
        'qr_disk' => env('WALLET_DEPOSIT_QR_DISK', 'local'),
        'invoice_expires_in_minutes' => (int) env('WALLET_DEPOSIT_INVOICE_EXPIRES_IN_MINUTES', 30),
        'min_confirmations' => (int) env('WALLET_DEPOSIT_MIN_CONFIRMATIONS', 10),
        'amount_collision_percent' => (float) env('WALLET_DEPOSIT_AMOUNT_COLLISION_PERCENT', 5),
        'manual_review_page_size' => (int) env('WALLET_DEPOSIT_MANUAL_REVIEW_PAGE_SIZE', 50),
        // Keep polling a bit past expiry so a slightly late but already-detected transfer can still confirm.
        'poll_grace_minutes' => (int) env('WALLET_DEPOSIT_POLL_GRACE_MINUTES', 60),
    ],
];
