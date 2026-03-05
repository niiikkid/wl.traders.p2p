<?php

namespace App\Http\Resources;

use App\Contracts\TelegramServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $telegramService = app(TelegramServiceContract::class);

        return [
            'is_active' => (bool) $this->is_active,
            'linked_at' => $this->linked_at?->toDateTimeString(),
            'bot_username' => $telegramService->botUsername(),
            'start_link' => $telegramService->buildDeepLink($this->resource),
        ];
    }
}
