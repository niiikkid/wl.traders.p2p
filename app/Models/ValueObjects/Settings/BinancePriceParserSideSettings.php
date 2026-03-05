<?php

namespace App\Models\ValueObjects\Settings;

class BinancePriceParserSideSettings
{
    public function __construct(
        public ?string $country = null,
        public array $payment_methods = [],
        public ?int $ad_quantity = null,
        public ?int $min_month_orders = null,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        $paymentMethods = $data['payment_methods'] ?? $data['pay_types'] ?? [];
        if (! is_array($paymentMethods)) {
            $paymentMethods = $paymentMethods ? [$paymentMethods] : [];
        }

        return new self(
            country: self::normalizeCountry($data['country'] ?? null),
            payment_methods: self::normalizePaymentMethods($paymentMethods),
            ad_quantity: self::normalizeAdQuantity(isset($data['ad_quantity']) ? (int) $data['ad_quantity'] : null),
            min_month_orders: self::normalizeMinMonthOrders(isset($data['min_month_orders']) ? (int) $data['min_month_orders'] : null),
        );
    }

    public static function defaults(): self
    {
        return new self(
            country: null,
            payment_methods: [],
            ad_quantity: 50,
            min_month_orders: 100,
        );
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'payment_methods' => $this->payment_methods,
            'ad_quantity' => $this->ad_quantity,
            'min_month_orders' => $this->min_month_orders,
        ];
    }

    protected static function normalizeCountry(?string $country): ?string
    {
        $country = is_string($country) ? trim($country) : null;
        if ($country === '') {
            return null;
        }

        return strtoupper($country);
    }

    protected static function normalizePaymentMethods(array $paymentMethods): array
    {
        return array_values(array_filter(
            array_map(fn ($method) => is_scalar($method) ? trim((string) $method) : null, $paymentMethods),
            fn ($method) => $method !== null && $method !== ''
        ));
    }

    protected static function normalizeAdQuantity(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(1, min(100, $value));
    }

    protected static function normalizeMinMonthOrders(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(0, $value);
    }
}
