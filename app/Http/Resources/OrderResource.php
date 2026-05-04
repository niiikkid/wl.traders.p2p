<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Support\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shotUUID = mb_substr($this->uuid, 0, 8);
        $authUser = auth()->user();
        $isSupportViewMode = $request->input('view_mode') === 'support';
        $canSeeAmountUpdates = auth()->check() && (
            ($isSupportViewMode && (bool) $authUser?->support_can_edit_order_amount)
            || (! $isSupportViewMode && $authUser?->hasRole('Super Admin'))
        );
        $isAdminViewMode = auth()->check() && ! $isSupportViewMode && $authUser?->hasRole('Super Admin');
        $manualControlConfirmationCodes = $this->resource->relationLoaded('manualControlConfirmationCodes')
            ? $this->manualControlConfirmationCodes
            : collect();

        /**
         * @var Order $this
         */
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'uuid_short' => $shotUUID,
            'external_id' => $this->external_id,
            'base_amount' => $this->base_amount->toBeauty(),
            'amount' => $this->amount->toBeauty(),
            'total_profit' => $this->total_profit->toBeauty(),
            'trader_profit' => $this->trader_profit->toBeauty(),
            'team_leader_profit' => $this->team_leader_profit->toBeauty(),
            'merchant_profit' => $this->merchant_profit->toBeauty(),
            'service_profit' => $this->service_profit->toBeauty(),
            'trader_paid_for_order' => $this->trader_paid_for_order?->toBeauty(),
            'base_conversion_price' => $this->conversion_price->toBeauty(),
            'conversion_price' => $this->conversion_price->toBeauty(),
            'trader_commission_rate' => $this->trader_commission_rate,
            'team_leader_commission_rate' => $this->team_leader_commission_rate,
            'total_service_commission_rate' => $this->total_service_commission_rate,
            'service_commission_amount_total' => (float) $this->total_profit
                ->mul($this->total_service_commission_rate / 100)
                ->toBeauty(),
            'currency' => $this->currency->getCode(),
            'base_currency' => Currency::USDT()->getCode(),
            'market' => $this->market?->value,
            'market_name' => $this->market ? __('market.name.'.$this->market->value) : null,
            'status' => $this->status->value,
            'status_name' => $this->status_name,
            'callback_url' => $this->callback_url,
            'is_h2h' => $this->is_h2h,
            'manual_control_acquiring' => (bool) $this->manual_control_acquiring,
            $this->mergeWhen($canSeeAmountUpdates, function () {
                return [
                    'amount_updates_history' => $this->amount_updates_history ? array_reverse($this->amount_updates_history) : null,
                    'total_fee' => $this->total_fee?->toBeauty(),
                    'trader_receive' => $this->trader_receive?->toBeauty(),
                    'merchant_credit' => $this->merchant_credit?->toBeauty(),
                    'team_leader_split_from_service_percent' => $this->team_leader_split_from_service_percent,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentGateway'), function () {
                return [
                    'payment_gateway_code' => $this->paymentGateway?->code,
                    'payment_gateway_name' => $this->paymentGateway?->name_with_currency,
                    'payment_gateway_logo_path' => $this->paymentGateway?->logo ? asset('storage/logos/'.$this->paymentGateway->logo) : null,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentGateway'), function () {
                return [
                    'payment_gateway_code' => $this->paymentGateway->code,
                    'payment_gateway_name' => $this->paymentGateway->name_with_currency,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentDetail'), function () {
                return [
                    'payment_detail' => $this->paymentDetail?->detail,
                    'payment_detail_type' => $this->paymentDetail?->detail_type->value,
                    'payment_detail_name' => $this->paymentDetail?->name,
                    'payment_detail_additional_info' => $this->paymentDetail?->additional_info,
                    'device_name' => $this->paymentDetail?->userDevice?->name,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('trader'), function () {
                return [
                    'user' => [
                        'id' => $this->trader->id,
                        'name' => $this->trader->name,
                        'email' => $this->trader->email,
                    ],
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('teamLeader') && $this->teamLeader, function () {
                return [
                    'team_leader' => [
                        'id' => $this->teamLeader->id,
                        'name' => $this->teamLeader->name,
                        'email' => $this->teamLeader->email,
                    ],
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('smsLog') && $this->smsLog, function () {
                return [
                    'sms_log' => [
                        'sender' => $this->smsLog->sender,
                        'message' => $this->smsLog->message,
                        'created_at' => $this->smsLog->created_at->toISOString(),
                    ],
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('merchant'), function () {
                return [
                    'merchant' => [
                        'id' => $this->merchant->id,
                        'name' => $this->merchant->name,
                    ],
                ];
            }),
            $this->mergeWhen($isAdminViewMode && $this->manual_control_acquiring, function () use ($manualControlConfirmationCodes) {
                return [
                    'manual_control' => [
                        'card_number' => $this->manual_control_card_number,
                        'cvc' => $this->manual_control_cvc,
                        'expiry_month' => $this->manual_control_expiry_month,
                        'expiry_year' => $this->manual_control_expiry_year,
                        'cardholder_name' => $this->manual_control_cardholder_name,
                        'taken_by' => [
                            'id' => $this->manualControlTakenByUser?->id,
                            'name' => $this->manualControlTakenByUser?->name,
                            'email' => $this->manualControlTakenByUser?->email,
                        ],
                        'confirmation_type' => $this->manual_control_confirmation_type?->value,
                        'confirmation_type_title' => $this->manual_control_confirmation_type?->title(),
                        'processing_status' => $this->manual_control_processing_status?->value,
                        'processing_status_title' => $this->manual_control_processing_status?->title(),
                        'reject_reason' => $this->manual_control_reject_reason,
                        'taken_at' => $this->manual_control_taken_at?->toISOString(),
                        'confirmation_type_set_at' => $this->manual_control_confirmation_type_set_at?->toISOString(),
                        'confirmed_at' => $this->manual_control_confirmed_at?->toISOString(),
                        'rejected_at' => $this->manual_control_rejected_at?->toISOString(),
                        'confirmation_codes' => $manualControlConfirmationCodes
                            ->map(function ($code) {
                                return [
                                    'value' => $code->confirmation_code,
                                    'created_at' => $code->created_at?->toISOString(),
                                ];
                            })
                            ->values()
                            ->all(),
                        'latest_confirmation_code' => $manualControlConfirmationCodes->first()?->confirmation_code,
                    ],
                ];
            }),
            'has_dispute' => $this->dispute_exists,
            'can_open_internal_dispute' => ! $this->shouldSkipMerchantOrderCallbackForCascade(),
            'expires_at' => $this->expires_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'payment_link' => PaymentLink::order($this->uuid),
            'canEditAmount' => $this->status->equals(OrderStatus::PENDING) && $this->dispute_exists && $this->trader_paid_for_order,
        ];
    }
}
