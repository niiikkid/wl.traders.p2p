<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Sms\OrderSmsLogLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnlinkedSmsLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SmsLog $this */
        $parsingResult = $this->parsing_result ?? [];
        $amount = $parsingResult['amount'] ?? null;
        $order = $request->attributes->get('order_for_unlinked_sms_logs');
        $linkService = app(OrderSmsLogLinkService::class);
        $amountMatchesOrder = $order instanceof Order
            && $linkService->roundedSmsAmount(is_string($amount) ? $amount : null) === $linkService->roundedOrderAmount($order);

        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'message' => $this->message,
            'amount' => is_string($amount) ? $amount : null,
            'bank' => is_string($parsingResult['bank'] ?? null) ? $parsingResult['bank'] : null,
            'card' => is_string($parsingResult['card'] ?? null) ? $parsingResult['card'] : null,
            'device_name' => $this->whenLoaded('device', fn () => $this->device?->name),
            'created_at' => $this->created_at->toISOString(),
            'amount_matches_order' => $amountMatchesOrder,
        ];
    }
}
