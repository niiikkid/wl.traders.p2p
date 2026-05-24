<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PaymentDetail\PaymentDetailScheduleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = app(PaymentDetailScheduleAvailabilityService::class)->resolveStatus($this->resource);

        return array_merge($status, [
            'payment_details_count' => $this->whenCounted('paymentDetails'),
            'intervals' => PaymentDetailScheduleIntervalResource::collection(
                $this->whenLoaded('intervals'),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
