<?php

namespace App\Http\Requests\Admin\PaymentGateway;

use App\Enums\DetailType;
use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use App\Support\TraderCommissionTierResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Совместимость с разными именами параметров маршрута
        $paymentGateway = $this->route('paymentGateway') ?? $this->route('payment_gateway');

        return [
            'name' => ['required', 'string', 'min:3', 'max:30'],
            'code' => ['required', 'string', 'min:3', 'max:30', Rule::unique(PaymentGateway::class)->ignore($paymentGateway?->id)],
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'detail_types' => ['required', 'array'],
            'detail_types.*' => ['nullable', Rule::in(DetailType::values())],
            'min_limit' => ['required', 'integer', 'min:1'],
            'max_limit' => ['required', 'integer', 'min:1'],
            'trader_commission_rate_for_orders' => ['required', 'numeric', 'min:0'],
            'use_flexible_trader_commission_for_orders' => ['sometimes', 'boolean'],
            'trader_commission_tiers_for_orders' => ['nullable', 'array'],
            'trader_commission_tiers_for_orders.*.from' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'trader_commission_tiers_for_orders.*.to' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'trader_commission_tiers_for_orders.*.rate' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders' => ['nullable', 'array'],
            'total_service_commission_tiers_for_orders.*.from' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders.*.to' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders.*.rate' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_rate_for_orders' => ['required', 'numeric', 'min:0'],
            'trader_commission_rate_for_payouts' => ['required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_payouts' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_intrabank' => ['required', 'boolean'],
            'reservation_time_for_orders' => ['required', 'integer', 'min:1'],
            'reservation_time_for_payouts' => ['required', 'integer', 'min:1'],
            'logo' => ['nullable', 'image', 'mimes:png', 'max:2048', Rule::dimensions()->ratio(1.0)],
        ];
    }

    public function attributes()
    {
        return [
            'name' => __('название'),
            'code' => __('код метода'),
            'currency' => __('валюта'),
            'detail_types' => __('тип реквизитов'),
            'max_limit' => __('макс лимит'),
            'trader_commission_rate_for_orders' => __('комиссия трейдера'),
            'use_flexible_trader_commission_for_orders' => __('гибкая комиссия трейдера'),
            'trader_commission_tiers_for_orders' => __('уровни комиссии трейдера'),
            'total_service_commission_tiers_for_orders' => __('уровни тотал комиссии сервиса'),
            'total_service_commission_rate_for_orders' => __('комиссия сервиса'),
            'trader_commission_rate_for_payouts' => __('комиссия трейдера (выплаты)'),
            'total_service_commission_rate_for_payouts' => __('комиссия сервиса (выплаты)'),
            'is_active' => __('активность'),
            'is_intrabank' => __('внутрибанковский перевод'),
            'reservation_time_for_orders' => __('время на сделку'),
            'reservation_time_for_payouts' => __('время на выплату'),
        ];
    }

    protected function prepareForValidation()
    {
        $currency = strtolower($this->currency ?? '');
        $payload = [
            'currency' => $currency,
            'use_flexible_trader_commission_for_orders' => $this->boolean('use_flexible_trader_commission_for_orders'),
        ];

        if (! $payload['use_flexible_trader_commission_for_orders']) {
            $payload['trader_commission_tiers_for_orders'] = [];
            $payload['total_service_commission_tiers_for_orders'] = [];
        }

        $this->merge($payload);
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->is_intrabank && is_array($this->detail_types)) {
                    // Удаляем типы телефонных реквизитов, если установлен внутрибанковский перевод
                    $detail_types = array_filter($this->detail_types, function ($type) {
                        return ! in_array($type, ['phone', 'mobile_commerce'], true);
                    });
                    $this->merge(['detail_types' => $detail_types]);
                }

                $primeTimeRate = (float) services()->settings()->getPrimeTimeBonus()->rate;
                $useFlexible = $this->boolean('use_flexible_trader_commission_for_orders');
                $traderRate = (float) $this->input('trader_commission_rate_for_orders');
                $totalServiceRate = (float) $this->input('total_service_commission_rate_for_orders');
                if (! $useFlexible && ($traderRate + $primeTimeRate) > $totalServiceRate) {
                    $validator->errors()->add(
                        'trader_commission_rate_for_orders',
                        'Комиссия трейдера с учетом прайм-тайма не может быть больше тотал комиссии сервиса.'
                    );
                }

                if (! $useFlexible) {
                    return;
                }

                $tiers = $this->input('trader_commission_tiers_for_orders', []);
                if (! is_array($tiers) || empty($tiers)) {
                    $validator->errors()->add(
                        'trader_commission_tiers_for_orders',
                        'Для гибкой комиссии добавьте хотя бы один уровень.'
                    );

                    return;
                }

                $totalServiceTiers = $this->input('total_service_commission_tiers_for_orders', []);
                if (! is_array($totalServiceTiers) || empty($totalServiceTiers)) {
                    $validator->errors()->add(
                        'total_service_commission_tiers_for_orders',
                        'Для гибкой тотал комиссии добавьте хотя бы один уровень.'
                    );

                    return;
                }

                $validatedTrader = TraderCommissionTierResolver::normalizeAndValidate(
                    tiers: $tiers,
                    minLimit: (float) $this->input('min_limit'),
                    maxLimit: (float) $this->input('max_limit')
                );

                foreach ($validatedTrader['errors'] as $error) {
                    $validator->errors()->add('trader_commission_tiers_for_orders', $error);
                }

                $validatedTotalService = TraderCommissionTierResolver::normalizeAndValidate(
                    tiers: $totalServiceTiers,
                    minLimit: (float) $this->input('min_limit'),
                    maxLimit: (float) $this->input('max_limit')
                );

                foreach ($validatedTotalService['errors'] as $error) {
                    $validator->errors()->add('total_service_commission_tiers_for_orders', $error);
                }

                if (count($validatedTrader['normalized']) !== count($validatedTotalService['normalized'])) {
                    $validator->errors()->add(
                        'total_service_commission_tiers_for_orders',
                        'Количество уровней для комиссии трейдера и тотал комиссии сервиса должно совпадать.'
                    );

                    return;
                }

                foreach ($validatedTrader['normalized'] as $index => $traderTier) {
                    $totalTier = $validatedTotalService['normalized'][$index];

                    if (
                        abs($traderTier['from'] - $totalTier['from']) > TraderCommissionTierResolver::EPSILON
                        || abs($traderTier['to'] - $totalTier['to']) > TraderCommissionTierResolver::EPSILON
                    ) {
                        $validator->errors()->add(
                            'total_service_commission_tiers_for_orders',
                            'Диапазоны гибкой комиссии трейдера и тотал комиссии сервиса должны совпадать по границам.'
                        );
                    }

                    if (($traderTier['rate'] + $primeTimeRate) > $totalTier['rate']) {
                        $validator->errors()->add(
                            "trader_commission_tiers_for_orders.{$index}.rate",
                            'Комиссия уровня трейдера с учетом прайм-тайма не может быть больше тотал комиссии сервиса на этом уровне.'
                        );
                    }
                }

            },
        ];
    }
}
