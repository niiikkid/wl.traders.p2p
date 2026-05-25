<?php

declare(strict_types=1);

namespace App\Services\Order\ValueObjects;

use App\Services\Money\Money;

readonly class OrderDebitAllocation
{
    public function __construct(
        public Money $traderTrust,
        public Money $teamLeaderReserve,
    ) {}

    public function total(): Money
    {
        return $this->traderTrust->add($this->teamLeaderReserve);
    }

    /**
     * @return array{trader_trust_paid_for_order: Money, team_leader_reserve_paid_for_order: Money}
     */
    public function toOrderAttributes(): array
    {
        return [
            'trader_trust_paid_for_order' => $this->traderTrust,
            'team_leader_reserve_paid_for_order' => $this->teamLeaderReserve,
        ];
    }
}
