<?php

namespace App\Http\Requests\PaymentDetailTag;

use App\Models\PaymentDetailTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
        /** @var PaymentDetailTag|null $tag */
        $tag = $this->route('paymentDetailTag');

        return [
            'name' => [
                'required',
                'string',
                'max:10',
                Rule::unique('payment_detail_tags', 'name')
                    ->where('user_id', $this->user()?->id)
                    ->ignore($tag?->id),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
