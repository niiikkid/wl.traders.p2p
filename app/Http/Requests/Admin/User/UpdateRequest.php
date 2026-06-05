<?php

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $roleName = Role::query()->whereKey($this->integer('role_id'))->value('name');

        return [
            // Используем поле login, но проверяем уникальность по колонке email
            'login' => ['required', 'string', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'telegram_username' => ['nullable', 'string', 'max:32', 'regex:/^@?[A-Za-z0-9_]{5,32}$/'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('name', '!=', 'Provider Liquidity')),
            ],
            'banned' => ['required', 'boolean'],
            'ban_reason' => ['nullable', 'string', 'max:500'],
            'stop_traffic' => ['required', 'boolean'],
            'can_work_without_device' => ['required', 'boolean'],
            'is_vip' => ['required', 'boolean'],
            'payouts_enabled' => ['required', 'boolean'],
            'priority_payout_access_enabled' => ['required', 'boolean'],
            'payout_hold_enabled' => ['required', 'boolean'],
            'payout_hold_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'payout_active_payouts_limit' => ['nullable', 'integer', 'min:1'],
            'referral_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'team_leader_split_from_service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payout_referral_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_team_leader_split_from_service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reserve_balance_limit' => ['nullable', 'integer', 'min:0'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'agent_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'team_leader_extended_access_enabled' => ['required', 'boolean'],
            'team_leader_flexible_trader_commission_enabled' => ['required', 'boolean'],
            'team_leader_flexible_trader_commission_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'team_leader_flexible_trader_commission_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'support_can_view_deposits' => ['required', 'boolean'],
            'support_can_edit_order_amount' => ['required', 'boolean'],
            'support_can_use_manual_control_acq' => ['required', 'boolean'],
            ...app(TeamLeaderInsuranceService::class)->teamLeaderConfigurationRules($roleName === 'Team Leader'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roleId = (int) $this->input('role_id');
            $roleName = Role::query()->whereKey($roleId)->value('name');
            /** @var User $user */
            $user = $this->route('user');
            $insuranceService = app(TeamLeaderInsuranceService::class);

            if ($roleName === 'Team Leader') {
                $insuranceService->validateTeamLeaderConfiguration($validator, $user);
            }

            if ($roleName === 'Trader') {
                $teamLeaderId = $this->integer('team_leader_id') ?: null;
                if (! $user->team_leader_id && $teamLeaderId) {
                    $user->loadMissing('wallet');
                    $insuranceService->validateTraderTeamLeaderAssignment($validator, $teamLeaderId, $user);
                }

                $insuranceService->validateTraderReserveLimitChange($validator, $user);
            }

            $agentId = $this->integer('agent_id') ?: null;
            if ($agentId) {
                $agentExists = User::query()
                    ->whereKey($agentId)
                    ->role('Agent')
                    ->exists();

                if ($roleName !== 'Merchant' || ! $agentExists) {
                    $validator->errors()->add('agent_id', __('Выберите пользователя с ролью Agent.'));
                }
            }

            $isTeamLeaderLikeRole = in_array($roleName, ['Super Admin', 'Team Leader'], true);
            $extendedAccessEnabled = (bool) $this->input('team_leader_extended_access_enabled', false);
            $flexibleEnabled = (bool) $this->input('team_leader_flexible_trader_commission_enabled', false);
            $min = $this->input('team_leader_flexible_trader_commission_min');
            $max = $this->input('team_leader_flexible_trader_commission_max');

            if ($flexibleEnabled && (! $isTeamLeaderLikeRole || ! $extendedAccessEnabled)) {
                $validator->errors()->add(
                    'team_leader_flexible_trader_commission_enabled',
                    __('Гибкая комиссия доступна только при включенном расширенном функционале Team Leader.')
                );
            }

            if (! $flexibleEnabled) {
                return;
            }

            if ($min === null || $min === '') {
                $validator->errors()->add(
                    'team_leader_flexible_trader_commission_min',
                    __('Укажите минимальную комиссию для трейдера.')
                );
            }

            if ($max === null || $max === '') {
                $validator->errors()->add(
                    'team_leader_flexible_trader_commission_max',
                    __('Укажите максимальную комиссию для трейдера.')
                );
            }

            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $validator->errors()->add(
                    'team_leader_flexible_trader_commission_min',
                    __('Минимальная комиссия не может быть больше максимальной.')
                );
            }
        });
    }

    public function attributes()
    {
        return [
            'role_id' => __('роль'),
            'stop_traffic' => __('остановка трафика'),
            'can_work_without_device' => __('работа без устройства'),
            'is_vip' => __('VIP статус'),
            'payouts_enabled' => __('выплаты включены'),
            'priority_payout_access_enabled' => __('приоритетный доступ к выплатам'),
            'payout_hold_enabled' => __('холд включён'),
            'payout_hold_minutes' => __('длительность hold (минуты)'),
            'payout_active_payouts_limit' => __('лимит активных выплат'),
            'referral_commission_percentage' => __('процент комиссии от рефералов'),
            'team_leader_split_from_service_percent' => __('сплит комиссии тимлида от сервиса'),
            'payout_referral_commission_percentage' => __('процент комиссии тимлида от выплат'),
            'payout_team_leader_split_from_service_percent' => __('сплит комиссии тимлида от выплат'),
            'reserve_balance_limit' => __('страховой депозит'),
            'team_leader_id' => __('тим лидер'),
            'agent_id' => __('агент'),
            'agent_commission_percentage' => __('комиссия агента'),
            'telegram_username' => __('telegram'),
            'team_leader_extended_access_enabled' => __('расширенный доступ тимлида'),
            'team_leader_flexible_trader_commission_enabled' => __('гибкая комиссия тимлида по трейдерам'),
            'team_leader_flexible_trader_commission_min' => __('минимальная комиссия тимлида'),
            'team_leader_flexible_trader_commission_max' => __('максимальная комиссия тимлида'),
            'support_can_view_deposits' => __('доступ саппорта к депозитам'),
            'support_can_edit_order_amount' => __('доступ саппорта к изменению суммы сделки'),
            'support_can_use_manual_control_acq' => __('доступ саппорта к Manual Control Acquiring'),
            ...app(TeamLeaderInsuranceService::class)->teamLeaderConfigurationAttributes(),
        ];
    }
}
