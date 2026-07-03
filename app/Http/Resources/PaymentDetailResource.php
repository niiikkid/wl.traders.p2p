<?php

namespace App\Http\Resources;

use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailScheduleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var PaymentDetail $this
         */
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'uuid_short' => mb_substr((string) $this->uuid, 0, 8),
            'user_id' => $this->user_id,
            'name' => $this->name,
            'detail' => $this->detail,
            'detail_type' => $this->detail_type->value,
            'initials' => $this->initials,
            'additional_info' => $this->additional_info,
            'is_active' => $this->is_active,
            'daily_limit' => $this->daily_limit?->toBeauty(),
            'current_daily_limit' => $this->current_daily_limit->toBeauty(),
            'monthly_limit' => $this->monthly_limit?->toBeauty(),
            'current_monthly_limit' => $this->current_monthly_limit->toBeauty(),
            'monthly_limit_reset_day' => $this->monthly_limit_reset_day,
            'monthly_successful_orders_limit' => $this->monthly_successful_orders_limit,
            'current_monthly_successful_orders_count' => $this->current_monthly_successful_orders_count,
            'daily_successful_orders_limit' => $this->daily_successful_orders_limit,
            'current_daily_successful_orders_count' => $this->current_daily_successful_orders_count,
            'pending_orders_count' => $this->pending_orders_count,
            'max_pending_orders_quantity' => $this->max_pending_orders_quantity,
            'min_order_amount' => $this->min_order_amount?->toBeauty(),
            'max_order_amount' => $this->max_order_amount?->toBeauty(),
            'order_interval_minutes' => $this->order_interval_minutes,
            'currency' => $this->currency->getCode(),
            'user_device_id' => $this->user_device_id,
            'payment_detail_schedule_id' => $this->payment_detail_schedule_id,
            'schedule' => app(PaymentDetailScheduleAvailabilityService::class)
                ->resolveStatusForPaymentDetail($this->resource),
            'created_at' => $this->created_at->toDateString(),
            'payment_gateway_ids' => $this->payment_gateway_ids ?? [],
            'successful_orders_total_count' => $this->resource->successful_orders_total_count ?? 0,
            'successful_orders_total_turnover_fiat' => $this->resource->successful_orders_total_turnover_fiat ?? null,
            'successful_orders_total_turnover_usdt' => $this->resource->successful_orders_total_turnover_usdt ?? null,
            'last_deal_at' => $this->resource->last_deal_at,
            $this->mergeWhen($this->resource->relationLoaded('paymentGateways'), function () {
                /**
                 * @var PaymentDetail $this
                 */
                $paymentGateway = $this->paymentGateways->first();

                return [
                    'payment_gateway' => [
                        'name' => $paymentGateway->name,
                        'logo_path' => $paymentGateway?->logoUrl(),
                    ],
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('user'), function () {
                $user = $this->user;

                return [
                    'owner_id' => $user->id,
                    'owner_name' => $user->name,
                    'owner_email' => $user->email,
                    'owner_can_set_order_amount_limits' => (bool) $user->can_set_order_amount_limits,
                    'owner_can_work_without_device' => (bool) $user->can_work_without_device,
                    'owner_max_min_order_amount' => $user->effectiveMaxMinOrderAmount(),
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('userDevice'), function () {
                $device = $this->userDevice;
                if (! $device) {
                    return [];
                }

                return [
                    'device_name' => $device->name,
                    'device_model' => $device->device_model,
                    'device_android_version' => $device->android_version,
                ];
            }),
        ];
    }
}
