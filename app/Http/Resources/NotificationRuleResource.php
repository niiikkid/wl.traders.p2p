<?php

namespace App\Http\Resources;

use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $minAmount = null;

        if ($this->min_amount_minor && $this->currency) {
            $minAmount = Money::fromUnits($this->min_amount_minor, $this->currency->getCode())->toBeauty();
        }

        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'channels' => $this->channels,
            'statuses' => $this->statuses,
            'currency' => $this->currency?->getCode(),
            'min_amount' => $minAmount,
            'enabled' => $this->enabled,
        ];
    }
}
