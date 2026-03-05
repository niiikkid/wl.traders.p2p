<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
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
            'avatar_uuid' => $this->avatar_uuid,
            'avatar_style' => $this->avatar_style,
            'apk_latest_ping_at' => cache()->get("user-apk-latest-ping-at-$this->id"),
            'online_at' => cache()->get("user-online-at-$this->id"),
            'banned_at' => $this->banned_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'team_leader_id' => $this->team_leader_id,
            'team_leader' => $this->whenLoaded('teamLeader', function () {
                return [
                    'id' => $this->teamLeader->id,
                    'email' => $this->teamLeader->email,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('roles'), function () {
                return [
                    'role' => RoleResource::make($this->roles[0])->resolve()
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
                } else if ($this->hasRole('Trader')) {
                    $amount = $wallet->trust_balance;
                } else if ($this->hasRole('Team Leader')) {
                    $amount = $wallet->teamleader_balance;
                }

                return [
                    'balance' => $amount->toBeauty(),
                ];
            }),
            'stop_traffic' => $this->stop_traffic,
            'can_work_without_device' => (bool) $this->can_work_without_device,
            'traffic_enabled_at' => $this->traffic_enabled_at?->toISOString(),
            'is_online' => $this->is_online,
            'is_vip' => $this->is_vip,
            'temp_vip_active_until' => $this->temp_vip_active_until?->toIso8601String(),
            'temp_vip_can_activate' => (bool) $this->temp_vip_can_activate,
            'temp_vip_progress_start_at' => $this->temp_vip_progress_start_at?->toIso8601String(),
            'is_temp_vip_active' => services()->settings()->isTempVipEnabled() && $this->temp_vip_active_until
                ? now()->lt($this->temp_vip_active_until)
                : false,
            'temp_vip_progress' => $this->getTempVipProgressData(),
            'referral_commission_percentage' => $this->referral_commission_percentage,
            'team_leader_split_from_service_percent' => $this->team_leader_split_from_service_percent,
            'payout_referral_commission_percentage' => $this->payout_referral_commission_percentage,
            'payout_team_leader_split_from_service_percent' => $this->payout_team_leader_split_from_service_percent,
            'reserve_balance_limit' => $this->reserve_balance_limit,
            'fiat_currency' => $this->fiat_currency,
            'payouts_enabled' => (bool) $this->payouts_enabled,
            'payout_hold_enabled' => (bool) $this->payout_hold_enabled,
            'payout_hold_minutes' => (int) ($this->payout_hold_minutes ?? 0),
            'payout_active_payouts_limit' => (int) ($this->payout_active_payouts_limit ?? 1),
            'can_be_impersonated' => $this->id !== auth()->user()?->id && $this->banned_at === null,
            'has_2fa' => (bool)$this->google2fa_secret,
        ];
    }
}
