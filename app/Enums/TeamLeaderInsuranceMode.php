<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum TeamLeaderInsuranceMode: string
{
    use Enumable;

    case TraderReserve = 'trader_reserve';
    case TeamLeaderReserve = 'team_leader_reserve';

    public function label(): string
    {
        return match ($this) {
            self::TraderReserve => 'Вариант 1: страховой депозит у каждого трейдера',
            self::TeamLeaderReserve => 'Вариант 2: общий страховой депозит Team Leader',
        };
    }

    public function usesSharedReserve(): bool
    {
        return $this === self::TeamLeaderReserve;
    }
}
