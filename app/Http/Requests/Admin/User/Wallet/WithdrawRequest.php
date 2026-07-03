<?php

namespace App\Http\Requests\Admin\User\Wallet;

use App\Enums\BalanceType;
use App\Models\User;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'amount' => ['required', 'integer', 'min:1'],
            'balance_type' => ['required', Rule::enum(BalanceType::class)],
            'merchant_id' => [
                Rule::requiredIf(fn (): bool => $user?->hasRole('Merchant') === true
                    && $this->input('balance_type') === BalanceType::MERCHANT->value),
                'nullable',
                'integer',
                Rule::exists('merchants', 'id')->where('user_id', $user?->id),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var User|null $user */
            $user = $this->route('user');

            if ($user === null) {
                return;
            }

            $balanceType = BalanceType::tryFrom((string) $this->input('balance_type'));

            app(TeamLeaderInsuranceService::class)->validateAdminWalletWithdraw(
                $validator,
                $user,
                $balanceType,
            );

            if ($user->hasRole('Merchant') && $balanceType?->equals(BalanceType::MERCHANT)) {
                $hasWallet = $user->wallets()
                    ->where('merchant_id', $this->integer('merchant_id'))
                    ->exists();

                if (! $hasWallet) {
                    $validator->errors()->add('merchant_id', __('Для выбранного мерчанта не найден кошелёк.'));
                }
            }
        });
    }
}
