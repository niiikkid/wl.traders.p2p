<?php

namespace App\Http\Requests\Admin\User;

use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class StoreRequest extends FormRequest
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
        $roleName = Role::query()->whereKey($this->integer('role_id'))->value('name');

        return [
            // Используем поле login, но сохраняем в колонку email
            'login' => 'required|string|max:255|unique:users,email',
            'telegram_username' => ['nullable', 'string', 'max:32', 'regex:/^@?[A-Za-z0-9_]{5,32}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('name', '!=', 'Provider Liquidity')),
            ],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'max_min_order_amount' => ['nullable', 'integer', 'min:0'],
            ...app(TeamLeaderInsuranceService::class)->teamLeaderConfigurationRules($roleName === 'Team Leader'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roleName = Role::query()->whereKey($this->integer('role_id'))->value('name');
            $insuranceService = app(TeamLeaderInsuranceService::class);

            if ($roleName === 'Trader') {
                $insuranceService->validateTraderTeamLeaderAssignment(
                    $validator,
                    $this->integer('team_leader_id') ?: null
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'role_id' => __('роль'),
            'team_leader_id' => __('тим лидер'),
            'max_min_order_amount' => __('максимальная минимальная сумма сделки'),
            'telegram_username' => __('telegram'),
            ...app(TeamLeaderInsuranceService::class)->teamLeaderConfigurationAttributes(),
        ];
    }
}
