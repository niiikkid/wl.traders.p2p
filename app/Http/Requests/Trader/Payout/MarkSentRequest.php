<?php

namespace App\Http\Requests\Trader\Payout;

use App\Rules\ReceiptFileRule;
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
                'required_without:receipts',
                'file',
                'max:10240',
                new ReceiptFileRule(),
            ],
            'receipts' => [
                'required_without:receipt',
                'array',
                'min:1',
                'max:5',
            ],
            'receipts.*' => [
                'file',
                'max:10240',
                new ReceiptFileRule(),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'receipt' => 'чек выплаты',
            'receipts' => 'чеки выплаты',
            'receipts.*' => 'чек выплаты',
        ];
    }
}


