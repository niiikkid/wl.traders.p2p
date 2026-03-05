<?php

namespace App\Services\Order\Features\OrderDetailProvider;

use App\Enums\DetailType;
use App\Exceptions\OrderException;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Order\Features\OrderDetailProvider\Classes\FindAvailablePaymentDetail;
use App\Services\Order\Features\OrderDetailProvider\Values\Detail;

class OrderDetailProvider
{
    public function __construct(
        protected Order $order,
        protected Merchant $merchant,
        protected Money $amount,
        protected ?Currency $currency = null,
        protected ?PaymentGateway $gateway = null,
        protected ?DetailType $detailType = null,
    )
    {}

    /**
     * @throws OrderException
     */
    public function provide(): Detail
    {
        $findAvailablePaymentDetail = new FindAvailablePaymentDetail(
            $this->merchant,
            $this->order->market,
            $this->amount,
            $this->detailType,
            $this->currency,
            $this->gateway,
        );

        $selectedDetail = $findAvailablePaymentDetail->get();

        if (! $selectedDetail) {
            throw OrderException::make('Подходящие платежные реквизиты не найдены.');
        }

        return $selectedDetail;
    }
}
