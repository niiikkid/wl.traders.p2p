<?php

namespace App\Http\Resources;

use App\Models\RateSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var RateSource $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'base_currency' => strtolower($this->base_currency),
            'quote_currency' => strtolower($this->quote_currency),
            'pair' => $this->pair(),
            'rate' => $this->rate?->toBeauty(),
            'is_active' => (bool) $this->is_active,
            'is_automatic' => $this->isAutomatic(),
            'settings' => $this->settings ?? [],
            'last_refreshed_at' => $this->last_refreshed_at?->toIso8601String(),
            'last_parse_attempt' => $this->last_parse_attempt,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
