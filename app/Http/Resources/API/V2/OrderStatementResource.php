<?php

declare(strict_types=1);

namespace App\Http\Resources\API\V2;

use App\Models\CascadeDeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeDeal $this
         */
        return [
            'payin_id' => $this->uuid,
            'external_id' => $this->external_id,
            'payin' => [
                'initial_amount' => $this->initial_amount?->toPrecision(),
                'amount' => $this->amount?->toPrecision(),
                'currency' => $this->currency?->getCode(),
            ],
            'profit' => [
                'amount' => $this->credit?->toPrecision(),
                'currency' => $this->credit?->getCurrency()->getCode() ?? 'USDT',
            ],
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
