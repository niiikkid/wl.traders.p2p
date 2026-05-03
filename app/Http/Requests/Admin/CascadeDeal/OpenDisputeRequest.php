<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CascadeDeal;

use Illuminate\Foundation\Http\FormRequest;

class OpenDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipts' => ['nullable', 'array', 'max:3'],
            'receipts.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],
        ];
    }
}
