<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class TelegramChatAutomationWebhookController extends Controller
{
    /**
     * Placeholder for Phase 3 webhook ingestion. Telegram requires a reachable HTTPS endpoint when setting the webhook.
     */
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
