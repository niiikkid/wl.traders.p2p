<?php

namespace App\Http\Requests\Admin\AntiFraudSetting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => [
                'required',
                'integer',
                'exists:merchants,id',
                Rule::unique('anti_fraud_settings', 'merchant_id'),
            ],
            'enabled' => ['nullable', 'boolean'],
            'primary_max_pending' => ['nullable', 'integer', 'min:0'],
            'primary_rate_limits' => ['nullable', 'array'],
            'primary_rate_limits.*.count' => ['required_with:primary_rate_limits', 'integer', 'min:1'],
            'primary_rate_limits.*.minutes' => ['required_with:primary_rate_limits', 'integer', 'min:1'],
            'primary_failed_limit' => ['nullable', 'integer', 'min:0'],
            'primary_block_days' => ['nullable', 'integer', 'min:0'],
            'secondary_enabled' => ['nullable', 'boolean'],
            'secondary_max_pending' => ['nullable', 'integer', 'min:0'],
            'secondary_rate_limits' => ['nullable', 'array'],
            'secondary_rate_limits.*.count' => ['required_with:secondary_rate_limits', 'integer', 'min:1'],
            'secondary_rate_limits.*.minutes' => ['required_with:secondary_rate_limits', 'integer', 'min:1'],
            'secondary_failed_limit' => ['nullable', 'integer', 'min:0'],
            'secondary_block_days' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
