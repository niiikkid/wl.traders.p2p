<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TrafficCategory;

use Illuminate\Foundation\Http\FormRequest;

class SyncMerchantCategoriesRequest extends FormRequest
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
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_ids' => 'категории',
            'category_ids.*' => 'категория',
        ];
    }
}
