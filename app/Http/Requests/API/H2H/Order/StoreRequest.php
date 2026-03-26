<?php

namespace App\Http\Requests\API\H2H\Order;

use App\Enums\DetailType;
use App\Enums\MarketEnum;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
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
        $merchant = queries()->merchant()->findByUUID($this->merchant_id);
        $currency = null;

        if (! empty($this->payment_gateway)) {
            $paymentGateway = queries()->paymentGateway()->getByCode($this->payment_gateway);

            $currency = $paymentGateway->currency->getCode();
        } else if (! empty($this->currency)) {
            $currency = $this->currency;
        }

        $minOrderAmounts = is_array($merchant?->min_order_amounts) ? $merchant->min_order_amounts : [];
        $minAmount = $currency ? ($minOrderAmounts[$currency] ?? 1) : 1;

        // В локальной среде валидация callback_url не применяется
        $callbackValidationRules = ['nullable'];
        if (! is_local()) {
            $callbackValidationRules = ['nullable', 'string', 'url:https', 'max:256'];
        }

        return [
            'external_id' => [
                'required',
                function ($attribute, $value, $fail) use ($merchant) {
                    if (! $merchant instanceof Merchant) {
                        return;
                    }

                    // Проверяем существование в БД
                    $cacheKey = "order_external_id_{$value}_merchant_{$merchant->id}";

                    // Проверяем кэш только на положительный результат
                    $exists = Cache::get($cacheKey);

                    // Если в кэше не найдено или хранится false, проверяем БД напрямую
                    if ($exists === null) {
                        $exists = DB::table('orders')
                            ->where('external_id', $value)
                            ->where('merchant_id', $merchant->id)
                            ->exists();

                        // Кэшируем только положительный результат
                        if ($exists) {
                            Cache::put($cacheKey, true, 3600); // кэшируем на час
                        }
                    }

                    if ($exists) {
                        $fail('Заказ с таким external_id уже существует для данного мерчанта.');
                        return;
                    }

                    // Проверяем пендинг заказы в кэше
                    $pendingKey = "pending_order_external_id_{$value}_merchant_{$merchant->id}";
                    if (Cache::has($pendingKey)) {
                        $fail('Заказ с таким external_id уже в процессе создания для данного мерчанта.');
                        return;
                    }

                    // Помечаем, что заказ в процессе создания (час - достаточно для обработки очереди)
                    Cache::put($pendingKey, true, 60 * 60);
                },
                'max:255',
            ],
            'amount' => ['required', 'integer', "min:$minAmount"],
            'callback_url' => $callbackValidationRules,
            'payment_gateway' => [
                'required_without:currency',
                'prohibits:currency',
                function ($attribute, $value, $fail) {
                    $cacheKey = "payment_gateway_exists_{$value}";

                    $exists = Cache::remember($cacheKey, 3600, function () use ($value) {
                        return DB::table('payment_gateways')
                            ->where('code', $value)
                            ->exists();
                    });

                    if (!$exists) {
                        $fail('Выбранный платежный шлюз не существует.');
                    }
                }
            ],
            'currency' => [
                'required_without:payment_gateway',
                'prohibits:payment_gateway',
                Rule::in(Currency::getAllCodes())
            ],
            'payment_detail_type' => ['nullable', Rule::in(DetailType::values())],
            'merchant_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $cacheKey = "merchant_exists_{$value}";

                    $exists = Cache::remember($cacheKey, 3600, function () use ($value) {
                        return DB::table('merchants')
                            ->where('uuid', $value)
                            ->exists();
                    });

                    if (!$exists) {
                        $fail('Выбранный мерчант не существует.');
                    }
                }
            ],
            'client_id' => ['nullable', 'string', 'max:255'],
            'rate' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
        });
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
