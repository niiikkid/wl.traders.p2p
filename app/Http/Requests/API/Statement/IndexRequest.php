<?php

namespace App\Http\Requests\API\Statement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['nullable', 'string', 'exists:merchants,uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['new', 'old'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('merchant_id') && is_string($this->merchant_id)) {
            $merchantId = trim($this->merchant_id);
            $this->merge(['merchant_id' => $merchantId !== '' ? $merchantId : null]);
        }

        if ($this->has('sort') && is_string($this->sort)) {
            $sort = strtolower(trim($this->sort));
            $this->merge(['sort' => $sort !== '' ? $sort : null]);
        }
    }
}
