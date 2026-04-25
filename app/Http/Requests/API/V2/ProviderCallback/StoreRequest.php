<?php

namespace App\Http\Requests\API\V2\ProviderCallback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_code' => $this->route('provider_code'),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider_code' => ['required', Rule::exists('cascade_providers', 'code')],
        ];
    }
}
