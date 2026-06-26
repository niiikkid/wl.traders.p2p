<?php

namespace App\DTO\PaymentDetail;

use App\DTO\BaseDTO;

readonly class PaymentDetailUpdateDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $initials,
        public ?string $additional_info,
        public bool $is_active,
        public ?int $daily_limit,
        public ?int $monthly_limit,
        public ?int $monthly_limit_reset_day,
        public ?int $monthly_successful_orders_limit,
        public ?int $daily_successful_orders_limit,
        /** @var array<int> */
        public array $payment_gateway_ids,
        public int $max_pending_orders_quantity,
        public ?int $order_interval_minutes,
        public ?int $user_device_id,
        public ?int $min_order_amount = null,
        public ?int $max_order_amount = null,
        public ?int $payment_detail_schedule_id = null,
        public bool $updates_schedule = false,
    ) {}

    public static function makeFromRequest(array $data, bool $updates_schedule = false): static
    {
        return new static(
            name: $data['name'],
            initials: $data['initials'],
            additional_info: $data['additional_info'] ?? null,
            is_active: (bool) $data['is_active'],
            daily_limit: isset($data['daily_limit']) ? (int) $data['daily_limit'] : null,
            monthly_limit: isset($data['monthly_limit']) ? (int) $data['monthly_limit'] : null,
            monthly_limit_reset_day: isset($data['monthly_limit_reset_day']) ? (int) $data['monthly_limit_reset_day'] : null,
            monthly_successful_orders_limit: isset($data['monthly_successful_orders_limit'])
                ? (int) $data['monthly_successful_orders_limit']
                : null,
            daily_successful_orders_limit: isset($data['daily_successful_orders_limit'])
                ? (int) $data['daily_successful_orders_limit']
                : null,
            payment_gateway_ids: array_map('intval', $data['payment_gateway_ids']),
            max_pending_orders_quantity: (int) $data['max_pending_orders_quantity'],
            order_interval_minutes: isset($data['order_interval_minutes']) ? (int) $data['order_interval_minutes'] : null,
            user_device_id: isset($data['user_device_id']) ? (int) $data['user_device_id'] : null,
            min_order_amount: isset($data['min_order_amount']) ? (int) $data['min_order_amount'] : null,
            max_order_amount: isset($data['max_order_amount']) ? (int) $data['max_order_amount'] : null,
            payment_detail_schedule_id: $updates_schedule && array_key_exists('payment_detail_schedule_id', $data)
                ? ($data['payment_detail_schedule_id'] !== null ? (int) $data['payment_detail_schedule_id'] : null)
                : null,
            updates_schedule: $updates_schedule,
        );
    }
}
