<?php

namespace App\Services\User;

use App\Contracts\UserServiceContract;
use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserService implements UserServiceContract
{
    private const DEFAULT_TEAM_LEADER_COMMISSION_PERCENTAGE = 0.20;

    public function create(UserCreateDTO $data): User
    {
        return $this->transaction(function () use ($data) {
            $roleName = Role::find($data->role_id)?->name;
            $teamLeaderId = $this->resolveTeamLeaderIdForTrader($data->team_leader_id, $roleName);

            $referralCommissionPercentage = $roleName === 'Team Leader'
                ? self::DEFAULT_TEAM_LEADER_COMMISSION_PERCENTAGE
                : 0.00;

            $user = User::create([
                'name' => '',
                'email' => strtolower($data->login),
                'password' => Hash::make($data->password),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => $data->login,
                'avatar_style' => 'adventurer',
                'traffic_enabled_at' => now(),
                'reserve_balance_limit' => services()->settings()->getDefaultReserveBalanceLimit(),
                'team_leader_id' => $teamLeaderId,
                'referral_commission_percentage' => $referralCommissionPercentage,
                // Настройки выплат по умолчанию для всех новых пользователей
                'payouts_enabled' => true,
                'payout_hold_enabled' => true,
                'payout_hold_minutes' => 60,
                'payout_active_payouts_limit' => 1,
            ]);

            $user->assignRole($data->role_id);

            services()->wallet()->create($user);

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

            $updateData = [
                'email' => strtolower($data->login),
                'banned_at' => $data->banned ? now() : null,
                'stop_traffic' => $data->stop_traffic,
                'can_work_without_device' => $data->can_work_without_device,
                'is_vip' => $data->is_vip,
                'payouts_enabled' => $data->payouts_enabled,
                'payout_hold_enabled' => $data->payout_hold_enabled,
                'payout_hold_minutes' => $data->payout_hold_minutes ?? $user->payout_hold_minutes,
                'payout_active_payouts_limit' => $data->payout_active_payouts_limit ?? $user->payout_active_payouts_limit,
                'referral_commission_percentage' => $data->referral_commission_percentage,
                'team_leader_split_from_service_percent' => $data->team_leader_split_from_service_percent ?? $user->team_leader_split_from_service_percent,
                'payout_referral_commission_percentage' => $data->payout_referral_commission_percentage ?? $user->payout_referral_commission_percentage,
                'payout_team_leader_split_from_service_percent' => $data->payout_team_leader_split_from_service_percent
                    ?? $user->payout_team_leader_split_from_service_percent,
                'reserve_balance_limit' => $data->reserve_balance_limit,
                'traffic_enabled_at' => $wasTrafficStopped && ! $data->stop_traffic ? now() : $user->traffic_enabled_at,
            ];

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
}


