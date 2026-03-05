<?php

namespace App\DTO\PaymentDetail;

use App\DTO\BaseDTO;

readonly class PaymentDetailUpdateDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $initials,
        public bool $is_active,
        public int $daily_limit,
        public ?int $daily_successful_orders_limit,
        /** @var array<int> */
        public array $payment_gateway_ids,
        public int $max_pending_orders_quantity,
        public ?int $order_interval_minutes,
        public ?int $user_device_id,
        public ?int $min_order_amount = null,
        public ?int $max_order_amount = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            name: $data['name'],
            initials: $data['initials'],
            is_active: (bool) $data['is_active'],
            daily_limit: (int) $data['daily_limit'],
            daily_successful_orders_limit: isset($data['daily_successful_orders_limit'])
                ? (int) $data['daily_successful_orders_limit']
                : null,
            payment_gateway_ids: array_map('intval', $data['payment_gateway_ids']),
            max_pending_orders_quantity: (int) $data['max_pending_orders_quantity'],
            order_interval_minutes: isset($data['order_interval_minutes']) ? (int) $data['order_interval_minutes'] : null,
            user_device_id: isset($data['user_device_id']) ? (int) $data['user_device_id'] : null,
            min_order_amount: isset($data['min_order_amount']) ? (int) $data['min_order_amount'] : null,
            max_order_amount: isset($data['max_order_amount']) ? (int) $data['max_order_amount'] : null,
        );
    }
}


