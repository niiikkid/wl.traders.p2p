<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use App\Enums\DetailType;
use App\Services\Money\Currency;
use App\Support\TraderCommissionTierResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCommissionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission_settings' => ['nullable', 'array'],
            'commission_settings.*.currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'commission_settings.*.detail_type' => ['required', Rule::in(DetailType::values())],
            'commission_settings.*.trader_commission_rate_for_orders' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_settings.*.total_service_commission_rate_for_orders' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_settings.*.use_flexible_trader_commission_for_orders' => ['required', 'boolean'],
            'commission_settings.*.trader_commission_tiers_for_orders' => ['nullable', 'array'],
            'commission_settings.*.trader_commission_tiers_for_orders.*.from' => ['required_with:commission_settings.*.trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'commission_settings.*.trader_commission_tiers_for_orders.*.to' => ['required_with:commission_settings.*.trader_commission_tiers_for_orders', 'numeric', 'min:0'],
            'commission_settings.*.trader_commission_tiers_for_orders.*.rate' => ['required_with:commission_settings.*.trader_commission_tiers_for_orders', 'numeric', 'min:0', 'max:100'],
            'commission_settings.*.total_service_commission_tiers_for_orders' => ['nullable', 'array'],
            'commission_settings.*.total_service_commission_tiers_for_orders.*.from' => ['required_with:commission_settings.*.total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'commission_settings.*.total_service_commission_tiers_for_orders.*.to' => ['required_with:commission_settings.*.total_service_commission_tiers_for_orders', 'numeric', 'min:0'],
            'commission_settings.*.total_service_commission_tiers_for_orders.*.rate' => ['required_with:commission_settings.*.total_service_commission_tiers_for_orders', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $settings = $this->input('commission_settings', []);

            foreach ($settings as $index => $setting) {
                $useFlexible = (bool) ($setting['use_flexible_trader_commission_for_orders'] ?? false);
                $traderRate = $setting['trader_commission_rate_for_orders'] ?? null;
                $totalRate = $setting['total_service_commission_rate_for_orders'] ?? null;
                $traderTiers = $setting['trader_commission_tiers_for_orders'] ?? [];
                $totalTiers = $setting['total_service_commission_tiers_for_orders'] ?? [];

                if (($traderRate !== null && $totalRate === null) || ($traderRate === null && $totalRate !== null)) {
                    $validator->errors()->add(
                        "commission_settings.{$index}.trader_commission_rate_for_orders",
                        __('Для фиксированной комиссии заполните и комиссию трейдера, и тотал комиссию сервиса.')
                    );
                }

                if ($traderRate !== null && $totalRate !== null && (float) $traderRate > (float) $totalRate) {
                    $validator->errors()->add(
                        "commission_settings.{$index}.trader_commission_rate_for_orders",
                        __('Комиссия трейдера не может быть выше тотал комиссии сервиса.')
                    );
                }

                if (! $useFlexible) {
                    continue;
                }

                if (! is_array($traderTiers) || ! count($traderTiers)) {
                    $validator->errors()->add(
                        "commission_settings.{$index}.trader_commission_tiers_for_orders",
                        __('Добавьте хотя бы один уровень гибкой комиссии трейдера.')
                    );
                    continue;
                }

                if (! is_array($totalTiers) || count($totalTiers) !== count($traderTiers)) {
                    $validator->errors()->add(
                        "commission_settings.{$index}.total_service_commission_tiers_for_orders",
                        __('Уровни тотал комиссии сервиса должны быть заданы для каждого уровня трейдера.')
                    );
                    continue;
                }

                $this->validateTiersConsistency(
                    validator: $validator,
                    traderTiers: $traderTiers,
                    totalTiers: $totalTiers,
                    index: $index
                );
            }
        });
    }

    private function validateTiersConsistency(
        Validator $validator,
        array $traderTiers,
        array $totalTiers,
        int $index
    ): void {
        $previousTo = null;

        foreach ($traderTiers as $tierIndex => $traderTier) {
            $totalTier = $totalTiers[$tierIndex] ?? null;

            if (! is_array($totalTier)) {
                $validator->errors()->add(
                    "commission_settings.{$index}.total_service_commission_tiers_for_orders",
                    __('Отсутствует уровень тотал комиссии сервиса для диапазона #:position.', ['position' => $tierIndex + 1])
                );
                continue;
            }

            $from = (float) ($traderTier['from'] ?? 0);
            $to = (float) ($traderTier['to'] ?? 0);
            $traderRate = (float) ($traderTier['rate'] ?? 0);
            $totalRate = (float) ($totalTier['rate'] ?? 0);
            $totalFrom = (float) ($totalTier['from'] ?? 0);
            $totalTo = (float) ($totalTier['to'] ?? 0);

            if ($to <= $from) {
                $validator->errors()->add(
                    "commission_settings.{$index}.trader_commission_tiers_for_orders",
                    __('Уровень #:position имеет некорректный диапазон.', ['position' => $tierIndex + 1])
                );
            }

            if ($previousTo !== null && abs($from - $previousTo) > TraderCommissionTierResolver::EPSILON) {
                $validator->errors()->add(
                    "commission_settings.{$index}.trader_commission_tiers_for_orders",
                    __('Диапазоны уровней должны идти подряд без разрывов.')
                );
            }

            if (
                abs($totalFrom - $from) > TraderCommissionTierResolver::EPSILON
                || abs($totalTo - $to) > TraderCommissionTierResolver::EPSILON
            ) {
                $validator->errors()->add(
                    "commission_settings.{$index}.total_service_commission_tiers_for_orders",
                    __('Диапазоны тотал комиссии должны совпадать с диапазонами комиссии трейдера.')
                );
            }

            if ($traderRate > $totalRate) {
                $validator->errors()->add(
                    "commission_settings.{$index}.trader_commission_tiers_for_orders",
                    __('В уровне #:position комиссия трейдера выше тотал комиссии сервиса.', ['position' => $tierIndex + 1])
                );
            }

            $previousTo = $to;
        }
    }
}

