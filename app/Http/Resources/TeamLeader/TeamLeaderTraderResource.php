<?php

namespace App\Http\Resources\TeamLeader;

use App\Models\User;
use App\Support\TeamLeaderTraderCommissionResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamLeaderTraderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var User $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_uuid' => $this->avatar_uuid,
            'avatar_style' => $this->avatar_style,
            'is_online' => (bool) $this->is_online,
            'stop_traffic' => (bool) $this->stop_traffic,
            'online_at' => $this->normalizeCachedDate(cache()->get("user-online-at-$this->id")),
            'payment_details_count' => (int) ($this->payment_details_count ?? 0),
            'team_leader_individual_commission_percentage' => $this->team_leader_individual_commission_percentage !== null
                ? (float) $this->team_leader_individual_commission_percentage
                : null,
            'team_leader_effective_commission_percentage' => $this->resolveEffectiveCommission($request),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function resolveEffectiveCommission(Request $request): float
    {
        $teamLeader = $request->user();
        if (! $teamLeader instanceof User) {
            return 0.0;
        }

        return TeamLeaderTraderCommissionResolver::resolveEffectiveRate($teamLeader, $this->resource);
    }

    private function normalizeCachedDate(mixed $date): ?string
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }
}

