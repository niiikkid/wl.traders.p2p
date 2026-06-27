<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var User $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'login' => $this->email, // логин совпадает с колонкой email
            'telegram_username' => $this->telegram_username,
            'telegram_tag' => $this->telegram_username ? '@'.$this->telegram_username : null,
            'telegram_url' => $this->telegram_username ? 'https://t.me/'.$this->telegram_username : null,
            'apk_latest_ping_at' => $this->normalizeCachedDate(cache()->get("user-apk-latest-ping-at-$this->id")),
            'online_at' => $this->normalizeCachedDate(cache()->get("user-online-at-$this->id")),
            'banned_at' => $this->banned_at?->toISOString(),
            'ban_reason' => $this->when(
                $request->user()?->hasRole('Super Admin'),
                $this->ban_reason
            ),
            'banned_by' => $this->when(
                $request->user()?->hasRole('Super Admin') && $this->relationLoaded('bannedBy'),
                fn () => $this->bannedBy ? [
                    'id' => $this->bannedBy->id,
                    'email' => $this->bannedBy->email,
                ] : null
            ),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'team_leader_id' => $this->team_leader_id,
            'team_leader_extended_access_enabled' => (bool) $this->team_leader_extended_access_enabled,
            'team_leader_flexible_trader_commission_enabled' => (bool) $this->team_leader_flexible_trader_commission_enabled,
            'team_leader_flexible_trader_commission_min' => $this->team_leader_flexible_trader_commission_min !== null
                ? (float) $this->team_leader_flexible_trader_commission_min
                : null,
            'team_leader_flexible_trader_commission_max' => $this->team_leader_flexible_trader_commission_max !== null
                ? (float) $this->team_leader_flexible_trader_commission_max
                : null,
            'support_can_view_deposits' => (bool) $this->support_can_view_deposits,
            'support_can_edit_order_amount' => (bool) $this->support_can_edit_order_amount,
            'support_can_use_manual_control_acq' => (bool) $this->support_can_use_manual_control_acq,
            'team_leader_individual_commission_percentage' => $this->team_leader_individual_commission_percentage !== null
                ? (float) $this->team_leader_individual_commission_percentage
                : null,
            'team_leader' => $this->whenLoaded('teamLeader', function () {
                return [
                    'id' => $this->teamLeader->id,
                    'email' => $this->teamLeader->email,
                    'team_leader_insurance_mode' => $this->teamLeader->team_leader_insurance_mode->value,
                    'team_leader_insurance_mode_label' => $this->teamLeader->team_leader_insurance_mode->label(),
                    'uses_team_leader_shared_reserve' => $this->teamLeader->team_leader_insurance_mode->usesSharedReserve(),
                ];
            }),
            'team_leader_insurance_mode' => $this->team_leader_insurance_mode->value,
            'team_leader_insurance_mode_label' => $this->team_leader_insurance_mode->label(),
            'team_leader_trader_limit' => $this->team_leader_trader_limit,
            'team_leader_reserve_balance_limit' => $this->team_leader_reserve_balance_limit,
            'team_leader_reserve_stop_threshold' => $this->team_leader_reserve_stop_threshold,
            'connected_trader_count' => $this->when(
                $this->hasRole('Team Leader'),
                fn () => $this->connectedTraderCount()
            ),
            'remaining_team_leader_trader_slots' => $this->when(
                $this->hasRole('Team Leader'),
                fn () => $this->remainingTeamLeaderTraderSlots()
            ),
            'uses_team_leader_shared_reserve' => $this->usesTeamLeaderSharedReserve(),
            $this->mergeWhen($this->resource->relationLoaded('roles'), function () {
                return [
                    'role' => RoleResource::make($this->roles[0])->resolve(),
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('wallet'), function () {
                $amount = Money::fromPrecision(0, Currency::USDT());
                /**
                 * @var Wallet $wallet
                 */
                $wallet = $this->wallet;
                if ($this->hasRole('Merchant')) {
                    $amount = $wallet->merchant_balance;
                } elseif ($this->hasRole('Trader')) {
                    $amount = $wallet->trust_balance;
                } elseif ($this->hasRole('Team Leader')) {
                    $amount = $wallet->teamleader_balance;
                }

                return [
                    'balance' => $amount->toBeauty(),
                ];
            }),
            'stop_traffic' => $this->stop_traffic,
            'can_work_without_device' => (bool) $this->can_work_without_device,
            'sms_auto_close_orders_enabled' => (bool) $this->sms_auto_close_orders_enabled,
            'traffic_enabled_at' => $this->traffic_enabled_at?->toISOString(),
            'is_online' => $this->is_online,
            'can_set_order_amount_limits' => $this->can_set_order_amount_limits,
            'referral_commission_percentage' => $this->referral_commission_percentage,
            'team_leader_split_from_service_percent' => $this->team_leader_split_from_service_percent,
            'payout_referral_commission_percentage' => $this->payout_referral_commission_percentage,
            'payout_team_leader_split_from_service_percent' => $this->payout_team_leader_split_from_service_percent,
            'reserve_balance_limit' => $this->reserve_balance_limit,
            'max_min_order_amount' => $this->max_min_order_amount,
            'fiat_currency' => $this->fiat_currency,
            'payouts_enabled' => (bool) $this->payouts_enabled,
            'payout_hold_enabled' => (bool) $this->payout_hold_enabled,
            'payout_hold_minutes' => (int) ($this->payout_hold_minutes ?? 0),
            'payout_active_payouts_limit' => (int) ($this->payout_active_payouts_limit ?? 1),
            'can_be_impersonated' => $this->id !== $request->user()?->id && $this->banned_at === null,
            'has_2fa' => (bool) $this->google2fa_secret,
        ];
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
