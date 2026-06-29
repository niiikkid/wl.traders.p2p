<?php

namespace App\Http\Requests\WithdrawalAddress;

use App\Models\User;
use App\Models\WithdrawalAddress;
use App\Rules\ValidateTRC20Address;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use PragmaRX\Google2FALaravel\Google2FA;

class StoreWithdrawalAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'address', 'one_time_password'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->has('address') && is_string($this->input('address'))) {
            $this->merge(['address_hash' => WithdrawalAddress::hashAddress($this->input('address'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'min:34', 'max:34', new ValidateTRC20Address],
            'one_time_password' => [
                Rule::requiredIf(fn (): bool => $this->userRequires2fa()),
                'nullable',
                'numeric',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название адреса',
            'address' => 'адрес',
            'one_time_password' => 'код 2FA',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'one_time_password.required' => 'Введите код 2FA.',
            'one_time_password.numeric' => 'Неверный код 2FA.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->filled('address') && WithdrawalAddress::query()
                    ->where('address_hash', WithdrawalAddress::hashAddress($this->string('address')->toString()))
                    ->exists()) {
                    $validator->errors()->add('address', 'Адрес недоступен.');

                    return;
                }

                if ($this->userRequires2fa() && ! $this->isValid2faCode()) {
                    $validator->errors()->add('one_time_password', 'Неверный код 2FA.');
                }
            },
        ];
    }

    public function addressName(): ?string
    {
        $name = $this->string('name')->toString();

        return $name === '' ? null : $name;
    }

    public function address(): string
    {
        return $this->string('address')->toString();
    }

    public function addressHash(): string
    {
        return $this->string('address_hash')->toString();
    }

    private function userRequires2fa(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->google2fa_secret !== null;
    }

    private function isValid2faCode(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || $user->google2fa_secret === null) {
            return true;
        }

        $otp = $this->input('one_time_password');

        if ($otp === null || $otp === '') {
            return false;
        }

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $expected = $google2fa->getCurrentOtp($user->google2fa_secret);

        return (int) $expected === (int) $otp;
    }
}
