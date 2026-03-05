<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewaySettingsRequest extends FormRequest
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
            'gateway_settings' => ['nullable', 'array'],
            'gateway_settings.*.active' => ['nullable', 'boolean'],
            'gateway_settings.*.custom_gateway_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gateway_settings.*.custom_gateway_reservation_time' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
