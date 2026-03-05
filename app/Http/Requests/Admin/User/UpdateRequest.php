<?php

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            // Используем поле login, но проверяем уникальность по колонке email
            'login' => ['required', 'string', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'banned' => ['required', 'boolean'],
            'stop_traffic' => ['required', 'boolean'],
            'can_work_without_device' => ['required', 'boolean'],
            'is_vip' => ['required', 'boolean'],
            'payouts_enabled' => ['required', 'boolean'],
            'payout_hold_enabled' => ['required', 'boolean'],
            'payout_hold_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'payout_active_payouts_limit' => ['nullable', 'integer', 'min:1'],
            'referral_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'team_leader_split_from_service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payout_referral_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_team_leader_split_from_service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reserve_balance_limit' => ['nullable', 'integer', 'min:0'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function attributes()
    {
        return [
            'role_id' => __('роль'),
            'stop_traffic' => __('остановка трафика'),
            'can_work_without_device' => __('работа без устройства'),
            'is_vip' => __('VIP статус'),
            'payouts_enabled' => __('выплаты включены'),
            'payout_hold_enabled' => __('холд включён'),
            'payout_hold_minutes' => __('длительность hold (минуты)'),
            'payout_active_payouts_limit' => __('лимит активных выплат'),
            'referral_commission_percentage' => __('процент комиссии от рефералов'),
            'team_leader_split_from_service_percent' => __('сплит комиссии тимлида от сервиса'),
            'payout_referral_commission_percentage' => __('процент комиссии тимлида от выплат'),
            'payout_team_leader_split_from_service_percent' => __('сплит комиссии тимлида от выплат'),
            'reserve_balance_limit' => __('страховой депозит'),
            'team_leader_id' => __('тим лидер'),
        ];
    }
}
