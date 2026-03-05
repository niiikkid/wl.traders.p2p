<?php

use App\Telegram\Commands\StartCommand;

return [
    'default' => env('TELEGRAM_BOT_NAME', 'default'),

    'bots' => [
        env('TELEGRAM_BOT_NAME', 'default') => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'username' => env('TELEGRAM_BOT_NAME'),
            'commands' => [
                StartCommand::class,
            ],
        ],
    ],

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
];
