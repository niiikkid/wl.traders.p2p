<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\BalanceType;
use App\Enums\TeamLeaderInsuranceMode;
use App\Models\User;
use App\Services\Money\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class TeamLeaderInsuranceService
{
    public const ORDER_ISSUE_BLOCK_REASON_RESERVE_THRESHOLD = 'team_leader_reserve_stop_threshold';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function teamLeaderConfigurationRules(bool $isTeamLeaderRole): array
    {
        if (! $isTeamLeaderRole) {
            return [
                'team_leader_insurance_mode' => ['nullable'],
                'team_leader_trader_limit' => ['nullable'],
                'team_leader_reserve_balance_limit' => ['nullable'],
                'team_leader_reserve_stop_threshold' => ['nullable'],
            ];
        }

        return [
            'team_leader_insurance_mode' => [
                'required',
                new Enum(TeamLeaderInsuranceMode::class),
            ],
            'team_leader_trader_limit' => [
                Rule::requiredIf(fn () => $this->isSharedReserveModeInput()),
                'nullable',
                'integer',
                'min:1',
            ],
            'team_leader_reserve_balance_limit' => [
                Rule::requiredIf(fn () => $this->isSharedReserveModeInput()),
                'nullable',
                'integer',
                'min:0',
            ],
            'team_leader_reserve_stop_threshold' => [
                Rule::requiredIf(fn () => $this->isSharedReserveModeInput()),
                'nullable',
                'integer',
                'min:0',
                'lte:team_leader_reserve_balance_limit',
            ],
        ];
    }

    public function validateTeamLeaderConfiguration(Validator $validator, ?User $teamLeader): void
    {
        if ($teamLeader === null) {
            return;
        }

        $targetMode = TeamLeaderInsuranceMode::tryFrom((string) request()->input('team_leader_insurance_mode'));
        if ($targetMode === null) {
            return;
        }

        if ($targetMode === $teamLeader->team_leader_insurance_mode) {
            return;
        }

        if ($teamLeader->connectedTraderCount() > 0) {
            $validator->errors()->add(
                'team_leader_insurance_mode',
                __('Нельзя изменить режим страхового депозита, пока к Team Leader подключены трейдеры.')
            );

            return;
        }

        if ($targetMode === TeamLeaderInsuranceMode::TeamLeaderReserve) {
            return;
        }

        if ($this->teamLeaderHasNonZeroReserve($teamLeader)) {
            $validator->errors()->add(
                'team_leader_insurance_mode',
                __('Переключение на вариант 1 возможно только при нулевом резервном балансе Team Leader.')
            );
        }
    }

    public function validateTraderTeamLeaderAssignment(Validator $validator, ?int $teamLeaderId, ?User $trader = null): void
    {
        if (! $teamLeaderId) {
            return;
        }

        $teamLeader = User::query()
            ->whereKey($teamLeaderId)
            ->role('Team Leader')
            ->first();

        if ($teamLeader === null) {
            $validator->errors()->add('team_leader_id', __('Выберите пользователя с ролью Team Leader.'));

            return;
        }

        if (! $teamLeader->team_leader_insurance_mode->usesSharedReserve()) {
            return;
        }

        if ($teamLeader->remainingTeamLeaderTraderSlots() === 0) {
            $validator->errors()->add(
                'team_leader_id',
                __('Лимит трейдеров для этого Team Leader исчерпан.')
            );
        }

        if ($trader === null) {
            return;
        }

        if ($this->traderHasNonZeroWalletBalances($trader)) {
            $validator->errors()->add(
                'team_leader_id',
                __('Перед подключением к Team Leader с общим резервом все балансы трейдера должны быть равны нулю.')
            );
        }
    }

    public function validateTraderReserveLimitChange(Validator $validator, User $trader): void
    {
        if (! $trader->usesTeamLeaderSharedReserve()) {
            return;
        }

        if (! request()->has('reserve_balance_limit')) {
            return;
        }

        $submittedLimit = request()->input('reserve_balance_limit');
        $currentLimit = $trader->reserve_balance_limit;

        if ($submittedLimit === null && $currentLimit === null) {
            return;
        }

        if ((int) $submittedLimit === (int) $currentLimit) {
            return;
        }

        $validator->errors()->add(
            'reserve_balance_limit',
            __('Страховой депозит трейдера недоступен при работе через общий резерв Team Leader.')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function teamLeaderConfigurationAttributes(): array
    {
        return [
            'team_leader_insurance_mode' => __('режим страхового депозита'),
            'team_leader_trader_limit' => __('лимит трейдеров'),
            'team_leader_reserve_balance_limit' => __('требуемая сумма резерва'),
            'team_leader_reserve_stop_threshold' => __('порог остановки выдачи'),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function resolveTeamLeaderConfigurationForPersist(?string $roleName, array $validated): array
    {
        if ($roleName !== 'Team Leader') {
            return [
                'team_leader_insurance_mode' => TeamLeaderInsuranceMode::TraderReserve->value,
                'team_leader_trader_limit' => null,
                'team_leader_reserve_balance_limit' => null,
                'team_leader_reserve_stop_threshold' => null,
            ];
        }

        $mode = TeamLeaderInsuranceMode::from($validated['team_leader_insurance_mode']);

        if (! $mode->usesSharedReserve()) {
            return [
                'team_leader_insurance_mode' => $mode->value,
                'team_leader_trader_limit' => null,
                'team_leader_reserve_balance_limit' => null,
                'team_leader_reserve_stop_threshold' => null,
            ];
        }

        return [
            'team_leader_insurance_mode' => $mode->value,
            'team_leader_trader_limit' => (int) $validated['team_leader_trader_limit'],
            'team_leader_reserve_balance_limit' => (int) $validated['team_leader_reserve_balance_limit'],
            'team_leader_reserve_stop_threshold' => (int) $validated['team_leader_reserve_stop_threshold'],
        ];
    }

    public function shouldIgnoreTraderReserveLimit(?string $roleName, ?User $trader): bool
    {
        if ($roleName !== 'Trader' || $trader === null) {
            return false;
        }

        return $trader->usesTeamLeaderSharedReserve();
    }

    public function validateAdminWalletDeposit(Validator $validator, User $user, ?BalanceType $balanceType): void
    {
        if ($balanceType === null) {
            return;
        }

        if ($balanceType === BalanceType::RESERVE) {
            if (! $user->hasRole('Team Leader')) {
                $validator->errors()->add(
                    'balance_type',
                    __('Пополнение резервного баланса доступно только для Team Leader.')
                );

                return;
            }

            if (! $user->team_leader_insurance_mode->usesSharedReserve()) {
                $validator->errors()->add(
                    'balance_type',
                    __('Резервный баланс Team Leader доступен только во втором варианте страхового депозита.')
                );
            }
        }
    }

    public function validateAdminWalletWithdraw(Validator $validator, User $user, ?BalanceType $balanceType): void
    {
        if ($balanceType === null) {
            return;
        }

        if ($balanceType !== BalanceType::RESERVE) {
            return;
        }

        if (! $user->hasRole('Team Leader')) {
            $validator->errors()->add(
                'balance_type',
                __('Вывод резервного баланса доступен только для Team Leader.')
            );

            return;
        }

        if (! $user->team_leader_insurance_mode->usesSharedReserve()) {
            $validator->errors()->add(
                'balance_type',
                __('Резервный баланс Team Leader доступен только во втором варианте страхового депозита.')
            );
        }
    }

    public function teamLeaderUsesSharedReserve(?User $teamLeader): bool
    {
        if ($teamLeader === null || ! $teamLeader->hasRole('Team Leader')) {
            return false;
        }

        return $teamLeader->team_leader_insurance_mode->usesSharedReserve();
    }

    public function canIssueOrdersForTrader(User $trader): bool
    {
        if (! $trader->usesTeamLeaderSharedReserve()) {
            return true;
        }

        $teamLeader = $trader->relationLoaded('teamLeader')
            ? $trader->teamLeader
            : $trader->teamLeader()->first();

        if ($teamLeader === null) {
            return true;
        }

        return ! $this->isTeamLeaderReserveAtOrBelowStopThreshold($teamLeader);
    }

    public function constrainEligibleTradersForOrderIssuing(Builder $userQuery): void
    {
        $sharedMode = TeamLeaderInsuranceMode::TeamLeaderReserve->value;

        $userQuery->where(function (Builder $query) use ($sharedMode) {
            $query->whereNull('team_leader_id')
                ->orWhereHas('teamLeader', function (Builder $teamLeaderQuery) use ($sharedMode) {
                    $teamLeaderQuery->where(function (Builder $eligibleTeamLeaderQuery) use ($sharedMode) {
                        $eligibleTeamLeaderQuery
                            ->where('team_leader_insurance_mode', '!=', $sharedMode)
                            ->orWhereNull('team_leader_reserve_stop_threshold')
                            ->orWhereHas('wallet', function (Builder $walletQuery) {
                                $walletQuery->whereRaw(
                                    'CAST(wallets.reserve_balance AS DECIMAL(65, 0)) > CAST(users.team_leader_reserve_stop_threshold AS DECIMAL(65, 0))'
                                );
                            });
                    });
                });
        });
    }

    public function isTeamLeaderReserveAtOrBelowStopThreshold(User $teamLeader): bool
    {
        if (! $this->teamLeaderUsesSharedReserve($teamLeader)) {
            return false;
        }

        $stopThreshold = $teamLeader->team_leader_reserve_stop_threshold;
        if ($stopThreshold === null) {
            return false;
        }

        $wallet = $teamLeader->relationLoaded('wallet')
            ? $teamLeader->wallet
            : $teamLeader->wallet()->first();

        if ($wallet === null) {
            return true;
        }

        $thresholdAmount = Money::fromUnits(
            (string) $stopThreshold,
            $wallet->reserve_balance->getCurrency()->getCode()
        );

        return $wallet->reserve_balance->lessOrEquals($thresholdAmount);
    }

    /**
     * @return array<string, array<string, array{key: string, name: string}>>
     */
    public function sharedReserveHistoryBalanceFilterVariants(): array
    {
        return [
            'all' => [
                'key' => 'all',
                'name' => 'Все операции',
            ],
            BalanceType::TEAMLEADER->value => [
                'key' => BalanceType::TEAMLEADER->value,
                'name' => 'Доход тимлидера',
            ],
            BalanceType::RESERVE->value => [
                'key' => BalanceType::RESERVE->value,
                'name' => 'Страховой резерв',
            ],
        ];
    }

    public function resolveSharedReserveHistoryBalanceType(?string $filterKey): ?BalanceType
    {
        if ($filterKey === null || $filterKey === '' || $filterKey === 'all') {
            return null;
        }

        return BalanceType::tryFrom($filterKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function teamLeaderInsurancePropsForUser(User $user): ?array
    {
        if ($user->hasRole('Team Leader')) {
            $usesSharedReserve = $user->team_leader_insurance_mode->usesSharedReserve();

            return [
                'role' => 'team_leader',
                'uses_shared_reserve' => $usesSharedReserve,
                'mode' => $user->team_leader_insurance_mode->value,
                'mode_label' => $user->team_leader_insurance_mode->label(),
                'reserve_balance_limit' => $user->team_leader_reserve_balance_limit,
                'reserve_stop_threshold' => $user->team_leader_reserve_stop_threshold,
                'trader_limit' => $user->team_leader_trader_limit,
                'connected_trader_count' => $user->connectedTraderCount(),
                'remaining_trader_slots' => $user->remainingTeamLeaderTraderSlots(),
                'reserve_at_stop_threshold' => $usesSharedReserve
                    ? $this->isTeamLeaderReserveAtOrBelowStopThreshold($user)
                    : false,
            ];
        }

        if ($user->hasRole('Trader') && $user->usesTeamLeaderSharedReserve()) {
            $teamLeader = $user->relationLoaded('teamLeader')
                ? $user->teamLeader
                : $user->teamLeader()->first();

            return [
                'role' => 'trader',
                'uses_shared_reserve' => true,
                'team_leader_email' => $teamLeader?->email,
            ];
        }

        return null;
    }

    private function isSharedReserveModeInput(): bool
    {
        return TeamLeaderInsuranceMode::tryFrom((string) request()->input('team_leader_insurance_mode'))
            ?->usesSharedReserve() ?? false;
    }

    private function teamLeaderHasNonZeroReserve(User $teamLeader): bool
    {
        $wallet = $teamLeader->relationLoaded('wallet')
            ? $teamLeader->wallet
            : $teamLeader->wallet()->first();

        if ($wallet === null) {
            return false;
        }

        return $wallet->reserve_balance->greaterThanZero();
    }

    private function traderHasNonZeroWalletBalances(User $trader): bool
    {
        $wallet = $trader->relationLoaded('wallet')
            ? $trader->wallet
            : $trader->wallet()->first();

        if ($wallet === null) {
            return true;
        }

        return $wallet->trust_balance->greaterThanZero()
            || $wallet->reserve_balance->greaterThanZero()
            || $wallet->merchant_balance->greaterThanZero()
            || $wallet->provider_balance->greaterThanZero()
            || $wallet->commission_balance->greaterThanZero()
            || $wallet->teamleader_balance->greaterThanZero()
            || $wallet->agent_balance->greaterThanZero();
    }
}
