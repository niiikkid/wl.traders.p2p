<?php

namespace App\Enums;

use App\Traits\Enumable;

/**
 * Amount-based tiers used to build a separate "fairness" queue per UAH amount range.
 *
 * Boundaries are inclusive on the lower bound and exclusive on the upper bound,
 * evaluated against the human-readable amount (whole hryvnia).
 */
enum UahAmountTier: string
{
    use Enumable;

    case RANGE_0_500 = '0_500';
    case RANGE_500_800 = '500_800';
    case RANGE_800_1000 = '800_1000';
    case RANGE_1000_2000 = '1000_2000';
    case RANGE_2000_PLUS = '2000_plus';

    /**
     * Resolve the tier for a given whole-hryvnia amount.
     */
    public static function fromAmount(int $amount): self
    {
        return match (true) {
            $amount < 500 => self::RANGE_0_500,
            $amount < 800 => self::RANGE_500_800,
            $amount < 1000 => self::RANGE_800_1000,
            $amount < 2000 => self::RANGE_1000_2000,
            default => self::RANGE_2000_PLUS,
        };
    }
}
