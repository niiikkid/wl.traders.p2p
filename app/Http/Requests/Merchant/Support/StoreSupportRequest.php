<?php

namespace App\Http\Requests\Merchant\Support;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class StoreSupportRequest extends FormRequest
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
        return [
            // Поле email используется как логин
            'email' => 'required|string|max:255|unique:'.User::class.',email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'merchant_ids' => 'sometimes|array',
            'merchant_ids.*' => [
                'integer',
                'exists:merchants,id',
                function ($attribute, $value, $fail) {
                    $merchant = Merchant::find($value);
                    if ($merchant && $merchant->user_id !== auth()->id()) {
                        $fail('Вы можете выбирать только свои мерчанты.');
                    }
                }
            ],
        ];
    }

    public function attributes()
    {
        return [
            'email' => __('логин'),
            'password' => __('пароль'),
            'merchant_ids' => __('мерчанты'),
            'merchant_ids.*' => __('мерчант'),
        ];
    }
}
