<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\TelegramChatBotServiceContract;
use App\Models\TelegramBotSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TelegramBotSetting */
class TelegramBotSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->webhook_metadata) ? $this->webhook_metadata : [];

        return [
            'has_bot_token' => $this->hasBotToken(),
            'has_webhook_secret' => $this->hasWebhookSecret(),
            'is_local' => is_local(),
            'local_webhook_base_url' => is_local() ? $this->local_webhook_base_url : null,
            'webhook_set_at' => $this->webhook_set_at?->toDateTimeString(),
            'webhook_last_error' => $this->webhook_last_error,
            'webhook_url' => app(TelegramChatBotServiceContract::class)->webhookUrl(),
            'webhook_metadata' => [
                'url' => $metadata['url'] ?? null,
                'has_custom_certificate' => $metadata['has_custom_certificate'] ?? null,
                'pending_update_count' => $metadata['pending_update_count'] ?? null,
                'last_error_date' => isset($metadata['last_error_date'])
                    ? date('Y-m-d H:i:s', (int) $metadata['last_error_date'])
                    : null,
                'last_error_message' => $metadata['last_error_message'] ?? null,
                'max_connections' => $metadata['max_connections'] ?? null,
                'allowed_updates' => $metadata['allowed_updates'] ?? null,
            ],
        ];
    }
}
