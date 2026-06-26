<?php

declare(strict_types=1);

namespace App\Http\Requests\SmsLog;

use Illuminate\Foundation\Http\FormRequest;

class LinkOrderRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ];
    }
}
