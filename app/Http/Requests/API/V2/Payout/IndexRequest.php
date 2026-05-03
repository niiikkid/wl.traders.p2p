<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V2\Payout;

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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['new', 'old'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sort') && is_string($this->sort)) {
            $sort = strtolower(trim($this->sort));
            $this->merge(['sort' => $sort !== '' ? $sort : null]);
        }
    }
}
