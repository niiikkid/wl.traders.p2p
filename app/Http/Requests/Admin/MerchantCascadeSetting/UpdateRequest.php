<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\MerchantCascadeSetting;

use App\Models\CascadeProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cascade_enabled' => ['required', 'boolean'],
            'allow_internal_providers' => ['required', 'boolean'],
            'allow_external_providers' => ['required', 'boolean'],
            'manual_control_internal_only' => ['required', 'boolean'],
            'internal_first_cascade_enabled' => ['required', 'boolean'],
            'allowed_provider_ids' => ['nullable', 'array'],
            'allowed_provider_ids.*' => [
                'integer',
                Rule::exists((new CascadeProvider)->getTable(), 'id'),
            ],
        ];
    }
}
