<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AccountTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'one_time_password' => ['required', 'string', 'size:6'],
        ];
    }

    public function oneTimePassword(): string
    {
        return (string) $this->string('one_time_password');
    }
}
