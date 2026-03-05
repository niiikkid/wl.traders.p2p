<?php

namespace App\Services\Order\Features;

use App\DTO\Order\AssignDetailsToOrderDTO;
use App\Enums\BalanceType;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\TransactionType;
use App\Events\DetailsAssignedToOrderEvent;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\Order\Features\OrderDetailProvider\OrderDetailProvider;
use App\Services\Order\Utils\DailyLimit;
use App\Services\Order\Utils\DailySuccessfulOrdersLimit;

class OrderDetailAssigner
{
    public function __construct(
        protected Order $order,
        protected AssignDetailsToOrderDTO $data
    )
    {
        if ($this->order->status->notEquals(OrderStatus::PENDING)) {
            throw OrderException::orderIsFinished($this->order);
        }
    }

    public function assign(): Order
    {
        $merchant = queries()->merchant()->findByID($this->order->merchant_id);

        $details = (new OrderDetailProvider(
            order: $this->order,
            merchant: $merchant,
            amount: $this->order->base_amount,
            currency: $this->data->gateway?->currency ?? $this->order->currency,
            gateway: $this->data->gateway,
            detailType: $this->data->detailType,
        ))->provide();

        $profits = services()->profit()->calculateInBody(
            sourceAmount: $details->amount,
            exchangeRate: $details->exchangePrice,
            totalFeeRate: $details->gateway->serviceCommissionRate,
            traderFeeRate: $details->traderCommissionRate,
            teamLeaderFeeRate: $details->teamLeaderCommissionRate,
            teamLeaderServiceSplitPercent: $details->trader->teamLeaderSplitFromServicePercent
        );

        $this->order->update([
            'amount' => $details->amount,
            'total_profit' => $profits->convertedAmount,
            'total_fee' => $profits->totalFee,
            'merchant_profit' => $profits->merchantCredit,
            'service_profit' => $profits->serviceFee,
            'trader_profit' => $profits->traderFee,
            'team_leader_profit' => $profits->teamLeaderFee,
            'trader_paid_for_order' => $profits->traderDebit,
            'team_leader_split_from_service_percent' => $details->trader->teamLeaderSplitFromServicePercent,
            'conversion_price' => $details->exchangePrice,
            'rate_fixed_at' => now(),
            'trader_commission_rate' => $details->traderCommissionRate,
            'team_leader_commission_rate' => $details->teamLeaderCommissionRate,
            'total_service_commission_rate' => $details->gateway->serviceCommissionRate,
            'payment_gateway_id' => $details->gateway->id,
            'payment_detail_id' => $details->id,
            'trader_id' => $details->trader->id,
            'team_leader_id' => $details->trader->teamLeaderID,
            'expires_at' => now()->addMinutes($details->gateway->reservationTime),
            'sub_status' => OrderSubStatus::WAITING_FOR_PAYMENT,
        ]);

        DailyLimit::increment($this->order->payment_detail_id, $this->order->amount, $this->order->created_at);
        DailySuccessfulOrdersLimit::increment($this->order->payment_detail_id, $this->order->created_at);

        services()->wallet()->takeFromBalance(
            $this->order->trader->wallet->id,
            $this->order->trader_paid_for_order,
            TransactionType::PAYMENT_FOR_OPENED_ORDER,
            BalanceType::TRUST
        );

        DetailsAssignedToOrderEvent::dispatch($this->order);

        return $this->order;
    }

}
