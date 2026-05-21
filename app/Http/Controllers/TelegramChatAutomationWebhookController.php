<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TelegramChatWebhookIngestionServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramChatAutomationWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        app(TelegramChatWebhookIngestionServiceContract::class)->handle($request->all());

        return response()->noContent();
    }
}
