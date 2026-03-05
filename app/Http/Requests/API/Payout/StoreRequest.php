<?php

namespace App\Http\Requests\API\Payout;

use App\Enums\PayoutMethodType;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        return [
            'merchant_id' => ['required', 'exists:merchants,uuid'],
            'external_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($merchant) {
                    if (! $merchant) {
                        return;
                    }

                    $cacheKey = "payout_external_id_{$value}_merchant_{$merchant->id}";
                    $exists = Cache::get($cacheKey);

                    if ($exists === null) {
                        $exists = DB::table('payouts')
                            ->where('external_id', $value)
                            ->where('merchant_id', $merchant->id)
                            ->exists();

                        if ($exists) {
                            Cache::put($cacheKey, true, 3600);
                        }
                    }

                    if ($exists) {
                        $fail('Выплата с таким external_id уже существует для данного мерчанта.');
                        return;
                    }
                },
            ],
            'amount' => ['required', 'integer', 'gt:0'],
            'payout_method_type' => ['required', 'string', Rule::in(PayoutMethodType::values())],
            'payment_gateway' => [
                'nullable',
                'string',
                Rule::exists('payment_gateways', 'code')
                    ->where('is_active', 1)
                    ->where('is_payouts_enabled', true),
                'required_without:currency',
            ],
            'currency' => [
                'nullable',
                'string',
                Rule::in(Currency::getAllCodes()),
                'required_without:payment_gateway',
            ],
            'requisites' => ['required', 'string', 'max:255'],
            'initials' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:30'],
            'callback_url' => ['nullable', 'string', 'url:https', 'max:256'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payout_method_type') && is_string($this->payout_method_type)) {
            $this->merge(['payout_method_type' => strtolower($this->payout_method_type)]);
        }

        if ($this->has('payment_gateway') && is_string($this->payment_gateway)) {
            $gateway = trim($this->payment_gateway);
            $this->merge(['payment_gateway' => $gateway !== '' ? strtolower($gateway) : null]);
        }

        if ($this->has('currency') && is_string($this->currency)) {
            $currency = trim($this->currency);
            $this->merge(['currency' => $currency !== '' ? strtolower($currency) : null]);
        }
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->filled('payment_gateway') && $this->filled('currency')) {
                    $validator->errors()->add('payment_gateway', 'Укажите либо payment_gateway, либо currency.');
                    $validator->errors()->add('currency', 'Укажите либо currency, либо payment_gateway.');
                }
                if ($this->filled('payment_gateway') && $this->filled('bank_name')) {
                    $validator->errors()->add('bank_name', 'Поле bank_name недоступно при выборе payment_gateway.');
                }
            },
        ];
    }
}

