<?php

namespace App\Http\Requests\API\H2H\Order;

use App\Enums\DetailType;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        if (! empty($this->payment_gateway)) {
            $paymentGateway = queries()->paymentGateway()->getByCode($this->payment_gateway);

            $currency = $paymentGateway->currency->getCode();
        } else if (! empty($this->currency)) {
            $currency = $this->currency;
        }

        $minAmount = $merchant->min_order_amounts[$currency] ?? 1;

        // В локальной среде валидация callback_url не применяется
        $callbackValidationRules = ['nullable'];
        if (! is_local()) {
            $callbackValidationRules = ['nullable', 'string', 'url:https', 'max:256'];
        }

        return [
            'external_id' => [
                'required',
                function ($attribute, $value, $fail) use ($merchant) {
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
        ];
    }
}
