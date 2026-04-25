<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\SmsLog;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class MessageReceivedNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected SmsLog $smsLog,
        protected ?PaymentGateway $paymentGateway = null,
        protected ?Order $order = null,
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::MESSAGE_RECEIVED;
    }

    public function recipients(): Collection
    {
        return collect([$this->smsLog->user])->filter();
    }

    public function currency(): ?Currency
    {
        return null;
    }

    public function amount(): ?Money
    {
        return null;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        $parsingResult = is_array($this->smsLog->parsing_result) ? $this->smsLog->parsing_result : [];
        $order = $this->order ?? $this->smsLog->order;
        $paymentDetail = $order?->paymentDetail;
        $orderCurrency = $order?->currency?->getCode();

        return [
            'has_order' => (bool) $order,
            'message_type' => strtoupper($this->smsLog->type->value),
            'sender' => $this->smsLog->sender,
            'message' => $this->smsLog->message,
            'bank_name' => $this->paymentGateway?->name,
            'amount' => $parsingResult['amount'] ?? null,
            'card_last_digits' => $parsingResult['card'] ?? null,
            'device_name' => $this->smsLog->device?->name,
            'order_uid' => $order?->uuid,
            'order_created_at' => $order?->created_at?->format('d.m.Y H:i'),
            'order_amount_fiat' => $order?->amount?->toBeauty(),
            'order_amount_fiat_currency' => $orderCurrency ? strtoupper($orderCurrency) : null,
            'order_amount_usdt' => $order?->total_profit?->toBeauty(),
            'payment_detail_name' => $paymentDetail?->name,
            'payment_detail_owner' => $paymentDetail?->initials,
            'payment_detail' => $paymentDetail?->detail,
        ];
    }
}
