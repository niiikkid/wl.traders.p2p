<?php

namespace App\Http\Requests\Trader\Payout;

use Illuminate\Foundation\Http\FormRequest;

class MarkSentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:10240',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'receipt' => 'чек выплаты',
        ];
    }
}


