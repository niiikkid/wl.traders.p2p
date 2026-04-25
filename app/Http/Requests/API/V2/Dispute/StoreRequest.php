<?php

namespace App\Http\Requests\API\V2\Dispute;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipts' => ['required', 'array'],
            'receipts.*' => ['string', 'max:2048'],
        ];
    }
}
