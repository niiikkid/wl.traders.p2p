<?php

namespace App\Http\Requests\API\V2\Order;

use App\Enums\CascadePaymentMethod;
use App\Enums\DetailType;
use App\Enums\MarketEnum;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * payment_detail_type Остается только на уровне данного класса. Она обеспечивает синхронизацию интерфейсов. Дальше используется только payment_method.
 */
class StoreRequest extends FormRequest
{
    public static function pendingCascadeDealCacheKey(int $merchant_id, string $external_id): string
    {
        return "pending_cascade_deal_external_id_{$external_id}_merchant_{$merchant_id}";
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('payment_detail_type')) {
            return;
        }

        $detail_type = DetailType::tryFrom(strtolower(trim((string) $this->input('payment_detail_type'))));
        $payment_method = $detail_type ? CascadePaymentMethod::fromDetailType($detail_type) : null;

        if (! $payment_method) {
            throw ValidationException::withMessages([
                'payment_detail_type' => ['Указанный payment_detail_type не поддерживается каскадом.'],
            ]);
        }

        $this->merge([
            'payment_detail_type' => $detail_type->value,
        ]);

        if (! $this->filled('payment_method')) {
            $this->merge(['payment_method' => $payment_method->value]);

            return;
        }

        $provided_payment_method = CascadePaymentMethod::tryFrom(strtolower(trim((string) $this->input('payment_method'))));
        if ($provided_payment_method !== $payment_method) {
            throw ValidationException::withMessages([
                'payment_method' => ["При payment_detail_type {$detail_type->value} ожидается payment_method {$payment_method->value}."],
            ]);
        }
    }

    public function rules(): array
    {
        $merchant = queries()->merchant()->findByUUID($this->merchant_id);

        // TODO: минимальная сумма для каскада по merchant.min_order_amounts и currency (как в H2H)
        // — поведение не доведено, бизнес-контекст уточнить перед включением реальной проверки.
        $min_amount = 1;

        $callback_validation_rules = ['nullable'];
        if (! is_local()) {
            $callback_validation_rules = ['nullable', 'string', 'url:https', 'max:256'];
        }

        return [
            'external_id' => [
                'required',
                function ($attribute, $value, $fail) use ($merchant) {
                    if (! $merchant instanceof Merchant) {
                        return;
                    }

                    $cache_key = "cascade_deal_external_id_{$value}_merchant_{$merchant->id}";

                    $exists = Cache::get($cache_key);

                    if ($exists === null) {
                        $exists = DB::table('cascade_deals')
                            ->where('external_id', $value)
                            ->where('merchant_id', $merchant->id)
                            ->exists();

                        if ($exists) {
                            Cache::put($cache_key, true, 3600);
                        }
                    }

                    if ($exists) {
                        $fail('Сделка с таким external_id уже существует для данного мерчанта.');

                        return;
                    }

                    $pending_key = static::pendingCascadeDealCacheKey($merchant->id, $value);
                    if (Cache::has($pending_key)) {
                        $fail('Сделка с таким external_id уже в процессе создания для данного мерчанта.');

                        return;
                    }

                    Cache::put($pending_key, true, 60 * 60);
                },
                'max:255',
            ],
            'merchant_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $cache_key = "merchant_exists_{$value}";

                    $exists = Cache::remember($cache_key, 3600, function () use ($value) {
                        return DB::table('merchants')
                            ->where('uuid', $value)
                            ->exists();
                    });

                    if (! $exists) {
                        $fail('Выбранный мерчант не существует.');
                    }
                },
            ],
            'amount' => ['required', 'integer', "min:$min_amount"],
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'payment_method' => ['required', Rule::in(CascadePaymentMethod::values())],
            'callback_url' => $callback_validation_rules,
            'client_id' => ['nullable', 'string', 'max:255'],
            'rate' => ['nullable'],
            'payment_detail_type' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('manual_control_acquiring')),
                Rule::in(DetailType::values()),
            ],
            'manual_control_acquiring' => ['nullable', 'boolean'],
            'card_number' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('manual_control_acquiring')),
                Rule::prohibitedIf(fn () => ! $this->boolean('manual_control_acquiring')),
                'string',
                'max:32',
            ],
            'expiry_month' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('manual_control_acquiring')),
                Rule::prohibitedIf(fn () => ! $this->boolean('manual_control_acquiring')),
                'integer',
                'min:1',
                'max:12',
            ],
            'expiry_year' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('manual_control_acquiring')),
                Rule::prohibitedIf(fn () => ! $this->boolean('manual_control_acquiring')),
                'integer',
                'min:2000',
                'max:2999',
            ],
            'cvc' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('manual_control_acquiring')),
                Rule::prohibitedIf(fn () => ! $this->boolean('manual_control_acquiring')),
                'string',
                'min:1',
                'max:20',
            ],
            'cardholder_name' => [
                'nullable',
                Rule::prohibitedIf(fn () => ! $this->boolean('manual_control_acquiring')),
                'string',
                'max:255',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $merchant = queries()->merchant()->findByUUID($this->merchant_id);
            if (! $merchant instanceof Merchant) {
                return;
            }

            if (! $this->filled('currency')) {
                return;
            }

            try {
                $currency = Currency::make((string) $this->input('currency'));
            } catch (\Throwable) {
                return;
            }

            $geo_market = $merchant->getGeoMarket($currency);
            $rate = $this->input('rate');

            if ($geo_market?->equals(MarketEnum::MERCHANT_API)) {
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

            if ($this->boolean('manual_control_acquiring')) {
                $payment_detail_type = (string) $this->input('payment_detail_type');

                if ($payment_detail_type !== DetailType::CARD->value) {
                    $validator->errors()->add(
                        'payment_detail_type',
                        'Для manual_control_acquiring доступен только тип реквизита card.'
                    );
                }

                $card_number = preg_replace('/\D+/', '', (string) $this->input('card_number'));
                if (! preg_match('/^\d{12,19}$/', $card_number)) {
                    $validator->errors()->add(
                        'card_number',
                        'Поле card_number должно содержать от 12 до 19 цифр.'
                    );
                }
            }
        });
    }

    protected function isDecimalWithinPrecision(string $value, int $max_scale): bool
    {
        $normalized = trim($value);
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(?:\.(\d+))?$/', $normalized, $matches)) {
            return false;
        }

        $scale = isset($matches[1]) ? strlen($matches[1]) : 0;

        return $scale <= $max_scale;
    }
}
