<?php

namespace App\Http\Controllers;

use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookController extends Controller
{
    public function __invoke()
    {
        Telegram::commandsHandler(true);

        return response()->noContent();
    }
}
