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
    ) {}

    /**
     * @throws OrderException
     */
    public function provide(): Detail
    {
        $forcedExchangePrice = null;
        $forcedMarket = null;
        $rateSourceId = null;

        $binding = services()->market()->resolveRateBinding(
            $this->merchant,
            $this->amount->getCurrency(),
        );

        if ($binding->isMerchantApi()) {
            if (
                $this->order->conversion_price
                && $this->order->conversion_price->greaterThanZero()
            ) {
                $forcedExchangePrice = $this->order->conversion_price;
            }
        } elseif ($binding->isSource()) {
            if (! $binding->source) {
                throw OrderException::marketPriceUnavailable();
            }

            $sourceRate = services()->market()->getSourceRate($binding->source);

            if (! $sourceRate->greaterThanZero()) {
                throw OrderException::marketPriceUnavailable();
            }

            $forcedExchangePrice = $sourceRate;
            $forcedMarket = $binding->source->type->toMarketEnum();
            $rateSourceId = $binding->source->id;
        }

        $findAvailablePaymentDetail = new FindAvailablePaymentDetail(
            $this->merchant,
            $this->order->market,
            $this->amount,
            $this->detailType,
            $this->currency,
            $this->gateway,
            $forcedExchangePrice,
            $forcedMarket,
            $rateSourceId,
        );

        $selectedDetail = $findAvailablePaymentDetail->get();

        if (! $selectedDetail) {
            throw OrderException::make('Подходящие платежные реквизиты не найдены.');
        }

        return $selectedDetail;
    }
}
