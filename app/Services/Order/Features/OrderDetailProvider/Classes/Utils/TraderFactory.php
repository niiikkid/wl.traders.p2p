<?php

declare(strict_types=1);

namespace App\Services\Order\Features\OrderDetailProvider\Classes\Utils;

use App\Models\User;
use App\Services\Order\Features\OrderDetailProvider\Values\Trader;
use App\Support\TeamLeaderTraderCommissionResolver;

class TraderFactory
{
    public static function make(User $user): Trader
    {
        $teamLeader = $user->teamLeader;
        $teamLeaderCommissionRate = $teamLeader
            ? TeamLeaderTraderCommissionResolver::resolveEffectiveRate($teamLeader, $user)
            : 0.0;

        return new Trader(
            id: $user->id,
            trustBalance: $user->wallet->trust_balance,
            teamLeaderID: $user->team_leader_id,
            teamLeaderCommissionRate: $teamLeaderCommissionRate,
            teamLeaderSplitFromServicePercent: (float) ($teamLeader?->team_leader_split_from_service_percent ?? 0),
        );
    }
}
