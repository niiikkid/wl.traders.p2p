<?php

namespace App\Models\ValueObjects\Settings;

class CurrencyPriceParserSideSettings
{
    public function __construct(
        public ?int $amount = null,
        public array $payment_methods = [],
        public ?int $ad_quantity = null,
        public ?int $min_recent_orders = null,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];

        $paymentMethods = $data['payment_methods'] ?? $data['payment_method'] ?? [];
        if (! is_array($paymentMethods)) {
            $paymentMethods = $paymentMethods ? [$paymentMethods] : [];
        }

        return new self(
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            payment_methods: array_values(array_filter(
                array_map(fn ($method) => is_numeric($method) ? (int) $method : null, $paymentMethods),
                fn ($method) => $method !== null
            )),
            ad_quantity: self::normalizeAdQuantity(isset($data['ad_quantity']) ? (int) $data['ad_quantity'] : null),
            min_recent_orders: self::normalizeMinRecentOrders(isset($data['min_recent_orders']) ? (int) $data['min_recent_orders'] : null),
        );
    }

    public static function defaults(): self
    {
        return new self(
            amount: null,
            payment_methods: [],
            ad_quantity: 50,
            min_recent_orders: 100,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'payment_methods' => $this->payment_methods,
            'ad_quantity' => $this->ad_quantity,
            'min_recent_orders' => $this->min_recent_orders,
        ];
    }

    protected static function normalizeAdQuantity(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(1, min(200, $value));
    }

    protected static function normalizeMinRecentOrders(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(0, $value);
    }
}
