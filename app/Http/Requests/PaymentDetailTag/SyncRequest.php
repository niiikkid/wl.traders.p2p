<?php

namespace App\Http\Requests\PaymentDetailTag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequest extends FormRequest
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
            'tags' => ['nullable', 'array', 'max:3'],
            'tags.*' => [
                'integer',
                'distinct',
                Rule::exists('payment_detail_tags', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
