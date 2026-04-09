<?php

namespace App\Services\Order\Features;

use App\DTO\Order\CreateOrderDTO;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Str;

class OrderMaker
{
    protected MarketEnum $geoMarket;

    /**
     * @throws OrderException
     */
    public function __construct(
        protected CreateOrderDTO $data
    )
    {
        $this->geoMarket = $this->resolveGeoMarket();
        $this->validate();
    }

    /**
     * @throws OrderException
     */
    public function create(): Order
    {
        $conversionPrice = $this->data->merchantRate
            ?? Money::fromPrecision(0, $this->data->amount->getCurrency());

        return Order::create([
            'uuid' => (string)Str::uuid(),
            'external_id' => $this->data->externalID,
            'merchant_id' => $this->data->merchant->id,
            'merchant_client_id' => $this->data->merchantClientId,
            'base_amount' => $this->data->amount,
            'amount' => $this->data->amount,
            'total_profit' => Money::fromPrecision(0, Currency::USDT()),
            'trader_profit' => Money::fromPrecision(0, Currency::USDT()),
            'merchant_profit' => Money::fromPrecision(0, Currency::USDT()),
            'service_profit' => Money::fromPrecision(0, Currency::USDT()),
            'trader_paid_for_order' => Money::fromPrecision(0, Currency::USDT()),
            'currency' => $this->data->amount->getCurrency(),
            'conversion_price' => $conversionPrice,
            'market' => $this->geoMarket,
            'trader_commission_rate' => 0,
            'total_service_commission_rate' => 0,
            'status' => OrderStatus::PENDING,
            'sub_status' => OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
            'callback_url' => $this->data->callbackURL,
            'success_url' => $this->data->successURL,
            'fail_url' => $this->data->failURL,
            'is_h2h' => $this->data->h2h,
            'manual_control_acquiring' => $this->data->manualControlAcquiring,
            'manual_control_card_number' => $this->data->cardNumber,
            'manual_control_expiry_month' => $this->data->expiryMonth,
            'manual_control_expiry_year' => $this->data->expiryYear,
            'manual_control_cvc' => $this->data->cvc,
            'manual_control_cardholder_name' => $this->data->cardholderName !== '' ? $this->data->cardholderName : null,
            'payment_gateway_id' => null,
            'payment_detail_id' => null,
            'expires_at' => null,
        ]);
    }

    protected function validate(): void
    {
        if (!$this->data->merchant->validated_at) {
            throw OrderException::merchantIsUnderModeration();
        }
        if ($this->data->merchant->banned_at) {
            throw OrderException::merchantBlocked();
        }
        if (!$this->data->merchant->active) {
            throw OrderException::merchantDisabled();
        }
        if ($this->data->h2h && $this->data->successURL) {
            throw OrderException::noSuccessUrlForH2HOrders();
        }
        if ($this->data->h2h && $this->data->failURL) {
            throw OrderException::noFailUrlForH2HOrders();
        }
        if ($this->data->manually && $this->data->h2h) {
            throw OrderException::noH2HAndManually();
        }
        if ($this->data->manualControlAcquiring && ! $this->data->h2h) {
            throw OrderException::make('Параметр manual_control_acquiring доступен только для H2H API.');
        }

        $this->validateMerchantApiRate();
    }

    /**
     * @throws OrderException
     */
    protected function resolveGeoMarket(): MarketEnum
    {
        $currency = $this->data->amount->getCurrency();
        $geoMap = $this->data->merchant->getGeoMap();

        $marketValue = $geoMap[$currency->getCode()] ?? $geoMap[strtolower($currency->getCode())] ?? null;

        if (! $marketValue) {
            throw OrderException::geoNotConfigured(strtoupper($currency->getCode()));
        }

        $market = MarketEnum::tryFrom($marketValue);

        if (! $market) {
            throw OrderException::geoNotConfigured(strtoupper($currency->getCode()));
        }

        $supportsCurrency = services()->market()
            ->getSupportedCurrencies($market)
            ->contains(fn (Currency $supported) => $supported->getCode() === $currency->getCode());

        if (! $supportsCurrency) {
            throw OrderException::geoUnsupported(strtoupper($currency->getCode()), $market->value);
        }

        return $market;
    }

    /**
     * @throws OrderException
     */
    protected function validateMerchantApiRate(): void
    {
        $currencyCode = strtoupper($this->data->amount->getCurrency()->getCode());

        if (! $this->geoMarket->equals(MarketEnum::MERCHANT_API)) {
            if ($this->data->merchantRate) {
                throw OrderException::merchantApiRateForbidden($currencyCode);
            }

            return;
        }

        if (! $this->data->merchantRate) {
            throw OrderException::merchantApiRateRequired($currencyCode);
        }

        $merchantRateSettings = $this->data->merchant->getMerchantApiRateSetting($this->data->amount->getCurrency());

        if (! $merchantRateSettings) {
            throw OrderException::merchantApiRateSettingsMissing($currencyCode);
        }

        $referenceRate = (float) $merchantRateSettings['order_reference_rate'];
        $maxDeviationPercent = (float) $merchantRateSettings['max_deviation_percent'];

        if ($referenceRate <= 0 || $maxDeviationPercent <= 0) {
            throw OrderException::merchantApiRateSettingsMissing($currencyCode);
        }

        $requestedRate = (float) $this->data->merchantRate->toPrecision();
        $minRate = $referenceRate * (1 - ($maxDeviationPercent / 100));
        $maxRate = $referenceRate * (1 + ($maxDeviationPercent / 100));

        if ($requestedRate < $minRate || $requestedRate > $maxRate) {
            $precision = $this->data->amount->getCurrency()->getPrecision();

            throw OrderException::merchantApiRateOutOfRange(
                currency: $currencyCode,
                requestedRate: $this->data->merchantRate->toPrecision(),
                referenceRate: number_format($referenceRate, $precision, '.', ''),
                deviationPercent: number_format($maxDeviationPercent, 2, '.', ''),
                allowedMinRate: number_format($minRate, $precision, '.', ''),
                allowedMaxRate: number_format($maxRate, $precision, '.', ''),
            );
        }
    }
}
