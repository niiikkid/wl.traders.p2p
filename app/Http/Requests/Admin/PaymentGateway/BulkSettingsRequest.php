<?php

namespace App\Http\Requests\Admin\PaymentGateway;

use App\Enums\DetailType;
use App\Services\Money\Currency;
use App\Support\TraderCommissionTierResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSettingsRequest extends FormRequest
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
        return [
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'detail_types' => ['sometimes', 'required', 'array'],
            'detail_types.*' => ['nullable', Rule::in(DetailType::values())],
            'min_limit' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_limit' => ['sometimes', 'required', 'integer', 'min:1'],
            'trader_commission_rate_for_orders' => ['sometimes', 'required', 'numeric', 'min:0'],
            'use_flexible_trader_commission_for_orders' => ['sometimes', 'required', 'boolean'],
            'trader_commission_tiers_for_orders' => ['sometimes', 'required', 'array'],
            'trader_commission_tiers_for_orders.*.from' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'trader_commission_tiers_for_orders.*.to' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'trader_commission_tiers_for_orders.*.rate' => ['required_with:trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders' => ['sometimes', 'required', 'array'],
            'total_service_commission_tiers_for_orders.*.from' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders.*.to' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_tiers_for_orders.*.rate' => ['required_with:total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'total_service_commission_rate_for_orders' => ['sometimes', 'required', 'numeric', 'min:0'],
            'trader_commission_rate_for_payouts' => ['sometimes', 'required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_payouts' => ['sometimes', 'required', 'numeric', 'min:0'],
            'reservation_time_for_orders' => ['sometimes', 'required', 'integer', 'min:1'],
            'reservation_time_for_payouts' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'is_payouts_enabled' => ['sometimes', 'required', 'boolean'],
        ];
    }

    protected function prepareForValidation()
    {
        $currency = strtolower($this->currency ?? '');
        $payload = [
            'currency' => $currency,
        ];

        if (array_key_exists('use_flexible_trader_commission_for_orders', $this->all())) {
            $payload['use_flexible_trader_commission_for_orders'] = $this->boolean('use_flexible_trader_commission_for_orders');
        }

        $this->merge($payload);
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $all = $this->all();
                if (! array_key_exists('use_flexible_trader_commission_for_orders', $all)) {
                    if (
                        array_key_exists('trader_commission_rate_for_orders', $all)
                        && array_key_exists('total_service_commission_rate_for_orders', $all)
                    ) {
                        $primeTimeRate = (float) services()->settings()->getPrimeTimeBonus()->rate;
                        $traderRate = (float) $this->input('trader_commission_rate_for_orders');
                        $totalServiceRate = (float) $this->input('total_service_commission_rate_for_orders');
                        if (($traderRate + $primeTimeRate) > $totalServiceRate) {
                            $validator->errors()->add(
                                'trader_commission_rate_for_orders',
                                'Комиссия трейдера с учетом прайм-тайма не может быть больше тотал комиссии сервиса.'
                            );
                        }
                    }
                    return;
                }

                $useFlexible = $this->boolean('use_flexible_trader_commission_for_orders');
                if (! $useFlexible) {
                    if (
                        array_key_exists('trader_commission_rate_for_orders', $all)
                        && array_key_exists('total_service_commission_rate_for_orders', $all)
                    ) {
                        $primeTimeRate = (float) services()->settings()->getPrimeTimeBonus()->rate;
                        $traderRate = (float) $this->input('trader_commission_rate_for_orders');
                        $totalServiceRate = (float) $this->input('total_service_commission_rate_for_orders');
                        if (($traderRate + $primeTimeRate) > $totalServiceRate) {
                            $validator->errors()->add(
                                'trader_commission_rate_for_orders',
                                'Комиссия трейдера с учетом прайм-тайма не может быть больше тотал комиссии сервиса.'
                            );
                        }
                    }
                    return;
                }

                if (! array_key_exists('min_limit', $all) || ! array_key_exists('max_limit', $all)) {
                    $validator->errors()->add(
                        'trader_commission_tiers_for_orders',
                        'Для массовой гибкой комиссии нужно одновременно указать min_limit и max_limit.'
                    );
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

                $primeTimeRate = (float) services()->settings()->getPrimeTimeBonus()->rate;
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

            }
        ];
    }
}
