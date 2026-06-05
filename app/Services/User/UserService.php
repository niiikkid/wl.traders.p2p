<?php

namespace App\Services\User;

use App\Contracts\UserServiceContract;
use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\Enums\TeamLeaderInsuranceMode;
use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserService implements UserServiceContract
{
    public function __construct(
        private readonly TeamLeaderInsuranceService $teamLeaderInsuranceService,
    ) {}

    private const DEFAULT_TEAM_LEADER_COMMISSION_PERCENTAGE = 0.20;

    private const DEFAULT_AGENT_COMMISSION_PERCENTAGE = 0.20;

    public function create(UserCreateDTO $data): User
    {
        return $this->transaction(function () use ($data) {
            $roleName = Role::find($data->role_id)?->name;
            $teamLeaderId = $this->resolveTeamLeaderIdForTrader($data->team_leader_id, $roleName);
            $agentId = $this->resolveAgentIdForMerchant($data->agent_id, $roleName);

            $referralCommissionPercentage = $roleName === 'Team Leader'
                ? self::DEFAULT_TEAM_LEADER_COMMISSION_PERCENTAGE
                : 0.00;
            $agentCommissionPercentage = $roleName === 'Agent'
                ? $data->agent_commission_percentage
                : self::DEFAULT_AGENT_COMMISSION_PERCENTAGE;

            $userAttributes = [
                'name' => '',
                'email' => strtolower($data->login),
                'telegram_username' => $data->telegram_username,
                'password' => Hash::make($data->password),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => $data->login,
                'avatar_style' => 'adventurer',
                'traffic_enabled_at' => now(),
                'reserve_balance_limit' => services()->settings()->getDefaultReserveBalanceLimit(),
                'team_leader_id' => $teamLeaderId,
                'agent_id' => $agentId,
                'agent_commission_percentage' => $agentCommissionPercentage,
                'team_leader_extended_access_enabled' => false,
                'referral_commission_percentage' => $referralCommissionPercentage,
                // Настройки выплат по умолчанию для всех новых пользователей
                'payouts_enabled' => true,
                'payout_hold_enabled' => true,
                'payout_hold_minutes' => 60,
                'payout_active_payouts_limit' => 1,
                'trader_economy_enabled' => $roleName === 'Trader' ? $data->trader_economy_enabled : false,
            ];

            if ($roleName === 'Team Leader') {
                $userAttributes = [
                    ...$userAttributes,
                    ...$this->teamLeaderInsuranceConfigurationFromDto($roleName, [
                        'team_leader_insurance_mode' => $data->team_leader_insurance_mode ?? TeamLeaderInsuranceMode::TraderReserve->value,
                        'team_leader_trader_limit' => $data->team_leader_trader_limit,
                        'team_leader_reserve_balance_limit' => $data->team_leader_reserve_balance_limit,
                        'team_leader_reserve_stop_threshold' => $data->team_leader_reserve_stop_threshold,
                    ]),
                ];
            }

            $user = User::create($userAttributes);

            $user->assignRole($data->role_id);

            services()->wallet()->create($user);

            services()->merchantTrafficCategory()->initializeDefaultsForTrader($user);

            return $user;
        });
    }

    public function update(UserUpdateDTO $data, User $user): User
    {
        return $this->transaction(function () use ($data, $user) {
            $wasTrafficStopped = $user->stop_traffic;

            $teamLeaderId = null;
            $roleName = Role::find($data->role_id)?->name;
            if (! $user->team_leader_id) {
                $teamLeaderId = $this->resolveTeamLeaderIdForTrader($data->team_leader_id, $roleName);
            }
            $agentId = $this->resolveAgentIdForMerchant($data->agent_id, $roleName);

            $extendedAccessEnabled = in_array($roleName, ['Team Leader', 'Super Admin'], true)
                ? $data->team_leader_extended_access_enabled
                : false;
            $flexibleTraderCommissionEnabled = in_array($roleName, ['Team Leader', 'Super Admin'], true)
                ? ($extendedAccessEnabled && $data->team_leader_flexible_trader_commission_enabled)
                : false;
            $supportFeatureAllowed = in_array($roleName, ['Support', 'Super Admin'], true);
            $manualControlAcqAllowed = in_array($roleName, ['Support', 'Super Admin'], true);
            $agentCommissionPercentage = $roleName === 'Agent'
                ? $data->agent_commission_percentage
                : self::DEFAULT_AGENT_COMMISSION_PERCENTAGE;

            $wasBanned = $user->banned_at !== null;
            $isBanned = (bool) $data->banned;

            $updateData = [
                'email' => strtolower($data->login),
                'telegram_username' => $data->telegram_username,
                ...$this->resolveBanAttributes($user, $wasBanned, $isBanned, $data->ban_reason),
                'stop_traffic' => $data->stop_traffic,
                'can_work_without_device' => $data->can_work_without_device,
                'is_vip' => $data->is_vip,
                'payouts_enabled' => $data->payouts_enabled,
                'trader_economy_enabled' => $roleName === 'Trader' ? $data->trader_economy_enabled : false,
                'priority_payout_access_enabled' => in_array($roleName, ['Trader', 'Super Admin'], true)
                    ? $data->priority_payout_access_enabled
                    : false,
                'payout_hold_enabled' => $data->payout_hold_enabled,
                'payout_hold_minutes' => $data->payout_hold_minutes ?? $user->payout_hold_minutes,
                'payout_active_payouts_limit' => $data->payout_active_payouts_limit ?? $user->payout_active_payouts_limit,
                'referral_commission_percentage' => $data->referral_commission_percentage,
                'team_leader_split_from_service_percent' => $data->team_leader_split_from_service_percent ?? $user->team_leader_split_from_service_percent,
                'payout_referral_commission_percentage' => $data->payout_referral_commission_percentage ?? $user->payout_referral_commission_percentage,
                'payout_team_leader_split_from_service_percent' => $data->payout_team_leader_split_from_service_percent
                    ?? $user->payout_team_leader_split_from_service_percent,
                'reserve_balance_limit' => $this->teamLeaderInsuranceService->shouldIgnoreTraderReserveLimit($roleName, $user)
                    ? $user->reserve_balance_limit
                    : $data->reserve_balance_limit,
                'agent_id' => $agentId,
                'agent_commission_percentage' => $agentCommissionPercentage,
                'traffic_enabled_at' => $wasTrafficStopped && ! $data->stop_traffic ? now() : $user->traffic_enabled_at,
                'team_leader_extended_access_enabled' => $extendedAccessEnabled,
                'team_leader_flexible_trader_commission_enabled' => $flexibleTraderCommissionEnabled,
                'team_leader_flexible_trader_commission_min' => $flexibleTraderCommissionEnabled
                    ? $data->team_leader_flexible_trader_commission_min
                    : null,
                'team_leader_flexible_trader_commission_max' => $flexibleTraderCommissionEnabled
                    ? $data->team_leader_flexible_trader_commission_max
                    : null,
                'support_can_view_deposits' => $supportFeatureAllowed
                    ? $data->support_can_view_deposits
                    : false,
                'support_can_edit_order_amount' => $supportFeatureAllowed
                    ? $data->support_can_edit_order_amount
                    : false,
                'support_can_use_manual_control_acq' => $manualControlAcqAllowed
                    ? $data->support_can_use_manual_control_acq
                    : false,
            ];

            if ($roleName === 'Team Leader') {
                $updateData = [
                    ...$updateData,
                    ...$this->teamLeaderInsuranceConfigurationFromDto($roleName, [
                        'team_leader_insurance_mode' => $data->team_leader_insurance_mode ?? $user->team_leader_insurance_mode->value,
                        'team_leader_trader_limit' => $data->team_leader_trader_limit,
                        'team_leader_reserve_balance_limit' => $data->team_leader_reserve_balance_limit,
                        'team_leader_reserve_stop_threshold' => $data->team_leader_reserve_stop_threshold,
                    ]),
                ];
            }

            if ($teamLeaderId) {
                $updateData['team_leader_id'] = $teamLeaderId;
            }

            $user->update($updateData);

            if ($user->id !== 1) {
                $user->syncRoles($data->role_id);
            }

            if ($user->banned_at) {
                $user->paymentDetails()->update([
                    'is_active' => false,
                ]);
            }

            return $user;
        });
    }

    protected function transaction(callable $callback): mixed
    {
        return Transaction::run(function () use ($callback) {
            return $callback();
        });
    }

    private function resolveTeamLeaderIdForTrader(?int $teamLeaderId, ?string $roleName): ?int
    {
        if (! $teamLeaderId) {
            return null;
        }

        if ($roleName !== 'Trader') {
            return null;
        }

        $teamLeader = User::query()
            ->where('id', $teamLeaderId)
            ->role('Team Leader')
            ->first();

        return $teamLeader?->id;
    }

    private function resolveAgentIdForMerchant(?int $agentId, ?string $roleName): ?int
    {
        if (! $agentId) {
            return null;
        }

        if ($roleName !== 'Merchant') {
            return null;
        }

        $agent = User::query()
            ->where('id', $agentId)
            ->role('Agent')
            ->first();

        return $agent?->id;
    }

    /**
     * @return array{banned_at: ?Carbon, ban_reason: ?string, banned_by_user_id: ?int}
     */
    private function resolveBanAttributes(User $user, bool $wasBanned, bool $isBanned, ?string $banReason): array
    {
        if (! $isBanned) {
            return [
                'banned_at' => null,
                'ban_reason' => null,
                'banned_by_user_id' => null,
            ];
        }

        if (! $wasBanned) {
            return [
                'banned_at' => now(),
                'ban_reason' => $banReason,
                'banned_by_user_id' => auth()->id(),
            ];
        }

        return [
            'banned_at' => $user->banned_at,
            'ban_reason' => $banReason,
            'banned_by_user_id' => $user->banned_by_user_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, int|string|null>
     */
    private function teamLeaderInsuranceConfigurationFromDto(?string $roleName, array $configuration): array
    {
        return $this->teamLeaderInsuranceService->resolveTeamLeaderConfigurationForPersist($roleName, [
            'team_leader_insurance_mode' => $configuration['team_leader_insurance_mode'],
            'team_leader_trader_limit' => $configuration['team_leader_trader_limit'],
            'team_leader_reserve_balance_limit' => $configuration['team_leader_reserve_balance_limit'],
            'team_leader_reserve_stop_threshold' => $configuration['team_leader_reserve_stop_threshold'],
        ]);
    }
}
