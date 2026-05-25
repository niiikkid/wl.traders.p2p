<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet\TraderTransfer;

use App\Http\Requests\Wallet\TraderTransfer\Concerns\AuthorizesTraderBalanceTransfer;
use Illuminate\Foundation\Http\FormRequest;

class CheckRecipientRequest extends FormRequest
{
    use AuthorizesTraderBalanceTransfer;

    protected function prepareForValidation(): void
    {
        if ($this->has('login') && is_string($this->login)) {
            $this->merge(['login' => trim($this->login)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => 'логин получателя',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Укажите логин получателя.',
        ];
    }

    public function recipientLogin(): string
    {
        return $this->string('login')->toString();
    }
}
