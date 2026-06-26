<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Sms\OrderSmsLogLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnlinkedOrderForSmsLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $this */
        $smsLog = $request->attributes->get('sms_log_for_unlinked_orders');
        $linkService = app(OrderSmsLogLinkService::class);
        $amountMatchesSms = false;

        if ($smsLog instanceof SmsLog) {
            $parsingResult = $smsLog->parsing_result ?? [];
            $smsAmount = $parsingResult['amount'] ?? null;
            $amountMatchesSms = $linkService->roundedSmsAmount(is_string($smsAmount) ? $smsAmount : null)
                === $linkService->roundedOrderAmount($this->resource);
        }

        return [
            ...(new TableOrderResource($this->resource))->toArray($request),
            'amount_matches_sms' => $amountMatchesSms,
        ];
    }
}
