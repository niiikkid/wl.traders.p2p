<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\TelegramChatBotException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TelegramBotSetting\UpdateRequest;
use App\Http\Resources\TelegramBotSettingResource;
use Illuminate\Http\JsonResponse;

class TelegramBotSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = services()->telegramChatBot()->getSettings();

        if ($setting->hasBotToken()) {
            try {
                $setting = services()->telegramChatBot()->refreshWebhookMetadata();
            } catch (TelegramChatBotException) {
                // Keep stored metadata when Telegram API is temporarily unavailable.
            }
        }

        return response()->json([
            'setting' => TelegramBotSettingResource::make($setting)->resolve(),
        ]);
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $setting = services()->telegramChatBot()->updateSettings(
                botToken: $validated['bot_token'] ?? null,
                regenerateWebhookSecret: (bool) ($validated['regenerate_webhook_secret'] ?? false),
            );
        } catch (TelegramChatBotException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Настройки Telegram-бота сохранены.',
            'setting' => TelegramBotSettingResource::make($setting)->resolve(),
        ]);
    }

    public function setupWebhook(): JsonResponse
    {
        try {
            $setting = services()->telegramChatBot()->setupWebhook();
        } catch (TelegramChatBotException $exception) {
            $setting = services()->telegramChatBot()->getSettings();

            return response()->json([
                'message' => $exception->getMessage(),
                'setting' => TelegramBotSettingResource::make($setting)->resolve(),
            ], 422);
        }

        return response()->json([
            'message' => 'Webhook Telegram успешно установлен.',
            'setting' => TelegramBotSettingResource::make($setting)->resolve(),
        ]);
    }
}
