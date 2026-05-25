<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet\TraderTransfer\Concerns;

use App\Models\User;

trait AuthorizesTraderBalanceTransfer
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $user->hasRole('Trader')) {
            return false;
        }

        if ($user->team_leader_id === null) {
            return false;
        }

        if ($user->archived_at !== null || $user->banned_at !== null) {
            return false;
        }

        return true;
    }
}
