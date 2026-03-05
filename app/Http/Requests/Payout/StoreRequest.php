<?php

namespace App\Http\Requests\Payout;

use App\Enums\PayoutMethodType;
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
        $callbackUrlRules = ['nullable', 'string', 'max:256'];

        if (app()->environment('production')) {
            $callbackUrlRules[] = 'url:https';
        } else {
            $callbackUrlRules[] = 'url:http,https';
        }

        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'external_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('payouts', 'external_id')
                    ->where(fn ($query) => $query->where('merchant_id', $this->input('merchant_id'))),
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
            'requisites' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $type = strtolower((string) $this->input('payout_method_type'));
                    $digits = preg_replace('/\s+/', '', (string) $value);

                    if ($type === PayoutMethodType::SBP->value) {
                        if (! preg_match('/^(\+7|7|8)\d{10}$/', $digits)) {
                            $fail('Укажите российский номер в формате +7XXXXXXXXXX.');
                        }
                        return;
                    }

                    if ($type === PayoutMethodType::CARD->value) {
                        if (! preg_match('/^\d{16}$/', $digits)) {
                            $fail('Укажите номер карты из 16 цифр без пробелов.');
                        }
                    }
                },
            ],
            'initials' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:30'],
            'callback_url' => $callbackUrlRules,
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

