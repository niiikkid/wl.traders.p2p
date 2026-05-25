<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet\TraderTransfer;

use App\Http\Requests\Wallet\TraderTransfer\Concerns\AuthorizesTraderBalanceTransfer;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use PragmaRX\Google2FALaravel\Google2FA;

class StoreTransferRequest extends FormRequest
{
    use AuthorizesTraderBalanceTransfer;

    private const AMOUNT_REGEX = '/^\d+(\.\d{1,2})?$/';

    protected function prepareForValidation(): void
    {
        if ($this->has('recipient_login') && is_string($this->recipient_login)) {
            $this->merge(['recipient_login' => trim($this->recipient_login)]);
        }

        if ($this->has('amount') && is_string($this->amount)) {
            $this->merge(['amount' => trim($this->amount)]);
        }

        if ($this->has('one_time_password') && is_string($this->one_time_password)) {
            $this->merge(['one_time_password' => trim($this->one_time_password)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_login' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string', 'regex:'.self::AMOUNT_REGEX],
            'one_time_password' => [
                Rule::requiredIf(fn (): bool => $this->senderRequires2fa()),
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
            'recipient_login' => 'логин получателя',
            'amount' => 'сумма',
            'one_time_password' => 'код 2FA',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_login.required' => 'Укажите логин получателя.',
            'amount.required' => 'Укажите сумму перевода.',
            'amount.regex' => 'Введите сумму больше 0, максимум с двумя знаками после запятой.',
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

                $amount = $this->string('amount')->toString();

                if (! $this->isPositiveAmount($amount)) {
                    $validator->errors()->add(
                        'amount',
                        'Введите сумму больше 0, максимум с двумя знаками после запятой.',
                    );

                    return;
                }

                if ($this->senderRequires2fa() && ! $this->isValid2faCode()) {
                    $validator->errors()->add('one_time_password', 'Неверный код 2FA.');
                }
            },
        ];
    }

    public function recipientLogin(): string
    {
        return $this->string('recipient_login')->toString();
    }

    public function amountMoney(): Money
    {
        return Money::fromPrecision($this->string('amount')->toString(), Currency::USDT()->getCode());
    }

    private function senderRequires2fa(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->google2fa_secret !== null;
    }

    private function isPositiveAmount(string $amount): bool
    {
        if (! preg_match(self::AMOUNT_REGEX, $amount)) {
            return false;
        }

        try {
            $money = Money::fromPrecision($amount, Currency::USDT()->getCode());
        } catch (\Throwable) {
            return false;
        }

        return $money->greaterThanZero();
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
