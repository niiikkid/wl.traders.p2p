<?php

namespace App\Http\Requests\API\Payout;

use App\Enums\MarketEnum;
use App\Enums\PayoutMethodType;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $merchantUuid = $this->input('merchant_id');
        $merchant = is_string($merchantUuid) && $merchantUuid !== ''
            ? queries()->merchant()->findByUUID($merchantUuid)
            : null;

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

                    $pendingKey = "pending_payout_external_id_{$value}_merchant_{$merchant->id}";
                    if (! Cache::add($pendingKey, true, 60 * 60)) {
                        $fail('Выплата с таким external_id уже в процессе создания для данного мерчанта.');
                    }
                },
            ],
            'amount' => ['required', 'integer', 'gt:0'],
            'payout_method_type' => ['required', 'string', Rule::in(PayoutMethodType::values())],
            'payment_gateway' => [
                'nullable',
                'string',
                Rule::exists('payment_gateways', 'code')
                    ->where('is_active', 1),
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
            'rate' => ['nullable'],
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
            function (Validator $validator) {
                $merchant = queries()->merchant()->findByUUID($this->merchant_id);
                if (! $merchant instanceof Merchant) {
                    return;
                }

                $currencyCode = $this->resolveCurrencyCode();
                if (! $currencyCode) {
                    return;
                }

                try {
                    $currency = Currency::make($currencyCode);
                } catch (\Throwable) {
                    return;
                }

                $geoMarket = $merchant->getGeoMarket($currency);
                $rate = $this->input('rate');

                if ($geoMarket?->equals(MarketEnum::MERCHANT_API)) {
                    if ($rate === null || $rate === '') {
                        $validator->errors()->add('rate', 'Поле rate обязательно для выбранного источника курсов.');

                        return;
                    }

                    if (! is_numeric($rate)) {
                        $validator->errors()->add('rate', 'Поле rate должно быть числом.');

                        return;
                    }

                    if ((float) $rate <= 0) {
                        $validator->errors()->add('rate', 'Поле rate должно быть больше 0.');

                        return;
                    }

                    if (! $this->isDecimalWithinPrecision((string) $rate, $currency->getPrecision())) {
                        $validator->errors()->add(
                            'rate',
                            "Поле rate может содержать не более {$currency->getPrecision()} знаков после запятой для валюты {$currency->getCode()}."
                        );
                    }

                    return;
                }

                if ($rate !== null && $rate !== '') {
                    $validator->errors()->add('rate', 'Поле rate недоступно для выбранного источника курсов.');
                }
            },
        ];
    }

    protected function resolveCurrencyCode(): ?string
    {
        if (! empty($this->payment_gateway)) {
            try {
                $paymentGateway = queries()->paymentGateway()->getByCode($this->payment_gateway);

                return $paymentGateway->currency->getCode();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! empty($this->currency)) {
            return (string) $this->currency;
        }

        return null;
    }

    protected function isDecimalWithinPrecision(string $value, int $maxScale): bool
    {
        $normalized = trim($value);
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(?:\.(\d+))?$/', $normalized, $matches)) {
            return false;
        }

        $scale = isset($matches[1]) ? strlen($matches[1]) : 0;

        return $scale <= $maxScale;
    }
}
