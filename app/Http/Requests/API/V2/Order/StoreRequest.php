<?php

namespace App\Http\Requests\API\V2\Order;

use App\Enums\CascadePaymentMethod;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $merchant = queries()->merchant()->findByUUID($this->merchant_id);

        $currency = $this->currency;
        $min_amount = 1;

        if ($merchant && $currency) {
            $min_amount = $merchant->min_order_amounts[$currency] ?? 1;
        }

        $callback_validation_rules = ['nullable'];
        if (! is_local()) {
            $callback_validation_rules = ['nullable', 'string', 'url:https', 'max:256'];
        }

        return [
            'external_id' => [
                'required',
                'max:255',
                Rule::unique('cascade_deals', 'external_id')->where(function ($query) use ($merchant) {
                    if ($merchant) {
                        $query->where('merchant_id', $merchant->id);
                    }
                }),
            ],
            'merchant_id' => ['required', Rule::exists('merchants', 'uuid')],
            'amount' => ['required', 'integer', "min:$min_amount"],
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'payment_method' => ['required', Rule::in(CascadePaymentMethod::values())],
            'callback_url' => $callback_validation_rules,
            'client_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
