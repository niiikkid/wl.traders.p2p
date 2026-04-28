<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Enums\CascadeDealEventType;
use App\Models\CascadeDeal;
use App\Models\CascadeDealEvent;
use App\Models\CascadeProvider;
use App\Models\CascadeTransaction;
use App\Models\User;

class CascadeDealEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        CascadeDeal $deal,
        CascadeDealEventType $type,
        ?array $payload = null,
        ?CascadeTransaction $transaction = null,
        ?CascadeProvider $provider = null,
        ?User $user = null,
        ?string $fromStatus = null,
        ?string $fromSubStatus = null,
        ?string $toStatus = null,
        ?string $toSubStatus = null,
    ): CascadeDealEvent {
        return CascadeDealEvent::query()->create([
            'cascade_deal_id' => $deal->id,
            'cascade_transaction_id' => $transaction?->id,
            'provider_id' => $provider?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'from_status' => $fromStatus,
            'from_sub_status' => $fromSubStatus,
            'to_status' => $toStatus,
            'to_sub_status' => $toSubStatus,
            'payload' => $payload,
        ]);
    }
}
