<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class TeamLeaderTraderCommissionResolver
{
    public static function isFlexibleEnabled(User $teamLeader): bool
    {
        return (bool) $teamLeader->team_leader_extended_access_enabled
            && (bool) $teamLeader->team_leader_flexible_trader_commission_enabled;
    }

    public static function resolveEffectiveRate(User $teamLeader, User $trader): float
    {
        $defaultRate = (float) $teamLeader->referral_commission_percentage;

        if (! self::isFlexibleEnabled($teamLeader)) {
            return $defaultRate;
        }

        if ($trader->team_leader_id !== $teamLeader->id) {
            return $defaultRate;
        }

        if ($trader->team_leader_individual_commission_percentage === null) {
            return $defaultRate;
        }

        $rate = (float) $trader->team_leader_individual_commission_percentage;
        $min = $teamLeader->team_leader_flexible_trader_commission_min;
        $max = $teamLeader->team_leader_flexible_trader_commission_max;

        if ($min === null || $max === null) {
            return $defaultRate;
        }

        if ($rate < (float) $min || $rate > (float) $max) {
            return $defaultRate;
        }

        return $rate;
    }
}
