<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V2\Payout;

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
            'currency' => [
                'required',
                'string',
                Rule::in(Currency::getAllCodes()),
            ],
            'payout_method' => ['required', 'string', Rule::in(PayoutMethodType::values())],
            'payout_details' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:30'],
            'callback_url' => ['nullable', 'string', 'url:https', 'max:256'],
            'exchange_rate' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payout_method') && is_string($this->payout_method)) {
            $this->merge(['payout_method' => strtolower($this->payout_method)]);
        }

        if ($this->has('currency') && is_string($this->currency)) {
            $currency = trim($this->currency);
            $this->merge(['currency' => $currency !== '' ? strtolower($currency) : null]);
        }
    }

    public function after(): array
    {
        return [
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
                $exchangeRate = $this->input('exchange_rate');

                if ($geoMarket?->equals(MarketEnum::MERCHANT_API)) {
                    if ($exchangeRate === null || $exchangeRate === '') {
                        $validator->errors()->add('exchange_rate', 'Поле exchange_rate обязательно для выбранного источника курсов.');
                        return;
                    }

                    if (! is_numeric($exchangeRate)) {
                        $validator->errors()->add('exchange_rate', 'Поле exchange_rate должно быть числом.');
                        return;
                    }

                    if ((float) $exchangeRate <= 0) {
                        $validator->errors()->add('exchange_rate', 'Поле exchange_rate должно быть больше 0.');
                        return;
                    }

                    if (! $this->isDecimalWithinPrecision((string) $exchangeRate, $currency->getPrecision())) {
                        $validator->errors()->add(
                            'exchange_rate',
                            "Поле exchange_rate может содержать не более {$currency->getPrecision()} знаков после запятой для валюты {$currency->getCode()}."
                        );
                    }

                    return;
                }

                if ($exchangeRate !== null && $exchangeRate !== '') {
                    $validator->errors()->add('exchange_rate', 'Поле exchange_rate недоступно для выбранного источника курсов.');
                }
            },
        ];
    }

    protected function resolveCurrencyCode(): ?string
    {
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
