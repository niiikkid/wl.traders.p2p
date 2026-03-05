<?php

namespace App\Exceptions;

class OrderException extends BaseException
{
    public static function orderIsFinished($order): OrderException
    {
        return new self("Сделка завершена. Order ID: $order->id");
    }

    public static function orderAlreadyFinished($order): OrderException
    {
        return new self("Сделка уже завершена. Order ID: $order->id");
    }

    public static function orderAlreadyOpened($order): OrderException
    {
        return new self("Сделка уже открыта. Order ID: $order->id");
    }

    public static function merchantIsUnderModeration(): OrderException
    {
        return new self("Мерчант находится на модерации.");
    }

    public static function merchantBlocked(): OrderException
    {
        return new self("Мерчант заблокирован.");
    }

    public static function merchantDisabled(): OrderException
    {
        return new self("Мерчант отключен.");
    }

    public static function noSuccessUrlForH2HOrders(): OrderException
    {
        return new self('Для H2H сделок невозможно указать success url.');
    }

    public static function noFailUrlForH2HOrders(): OrderException
    {
        return new self('Для H2H сделок невозможно указать fail url.');
    }

    public static function noH2HAndManually(): OrderException
    {
        return new self('Сделка не может быть одновременно H2H и Manually.');
    }

    public static function geoNotConfigured(string $currency): OrderException
    {
        return new self(
            "Для валюты {$currency} не настроен источник курсов. Обратитесь к администратору для настройки GEO мерчанта."
        );
    }

    public static function geoUnsupported(string $currency, string $market): OrderException
    {
        return new self(
            "Маркет {$market} не поддерживает валюту {$currency}. Обратитесь к администратору для настройки GEO."
        );
    }

    public static function marketPriceUnavailable(): OrderException
    {
        return new self('Не удалось получить актуальный курс конвертации.');
    }
}
