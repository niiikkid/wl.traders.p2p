<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class TelegramChatTraderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $telegramUsername = $this->pivot?->telegram_username;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'telegram_username' => $telegramUsername,
            'telegram_tag' => $telegramUsername ? '@'.$telegramUsername : null,
            'created_at' => $this->pivot?->created_at?->toDateTimeString(),
            'updated_at' => $this->pivot?->updated_at?->toDateTimeString(),
        ];
    }
}
