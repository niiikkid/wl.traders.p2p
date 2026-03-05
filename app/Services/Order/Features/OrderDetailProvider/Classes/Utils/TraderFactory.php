<?php

declare(strict_types=1);

namespace App\Services\Order\Features\OrderDetailProvider\Classes\Utils;

use App\Models\User;
use App\Services\Order\Features\OrderDetailProvider\Values\Trader;

class TraderFactory
{
    public static function make(User $user): Trader
    {
        return new Trader(
            id: $user->id,
            trustBalance: $user->wallet->trust_balance,
            teamLeaderID: $user->team_leader_id,
            teamLeaderCommissionRate: (float)$user->teamLeader?->referral_commission_percentage ?? 0,
            teamLeaderSplitFromServicePercent: (float)$user->teamLeader?->team_leader_split_from_service_percent ?? 0,
        );
    }
}
