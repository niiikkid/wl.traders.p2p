<?php

namespace App\Exceptions;

class PayoutException extends BaseException
{
    public static function gatewayInactive(): self
    {
        return new self('Выбранный банк недоступен.');
    }

    public static function gatewayPayoutsDisabled(): self
    {
        return new self('Для выбранного банка выплаты отключены.');
    }

    public static function merchantIsUnderModeration(): self
    {
        return new self('Мерчант находится на модерации.');
    }

    public static function merchantBlocked(): self
    {
        return new self('Мерчант заблокирован.');
    }

    public static function merchantDisabled(): self
    {
        return new self('Мерчант отключен.');
    }

    public static function merchantWalletMissing(): self
    {
        return new self('Для мерчанта не найден кошелёк.');
    }

    public static function insufficientMerchantFunds(): self
    {
        return new self('Недостаточно средств для резервирования выплаты.');
    }

    public static function marketPriceUnavailable(): self
    {
        return new self('Не удалось получить актуальный курс конвертации.');
    }

    public static function payoutNotOpen(): self
    {
        return new self('Выплату нельзя отменить в текущем статусе.');
    }

    public static function payoutAlreadyTaken(): self
    {
        return new self('Выплату нельзя отменить: она уже взята трейдером.');
    }

    public static function payoutUnavailableForTaking(): self
    {
        return new self('Эта выплата уже недоступна.');
    }

    public static function traderActiveLimitReached(int $limit): self
    {
        return new self("Достигнут лимит активных выплат ({$limit}). Завершите текущие выплаты прежде чем брать новые.");
    }

    public static function payoutNotAssignedToTrader(): self
    {
        return new self('Выплата недоступна: она закреплена за другим трейдером.');
    }

    public static function payoutAlreadyMarkedAsSent(): self
    {
        return new self('Вы уже отметили отправку средств по этой выплате.');
    }

    public static function payoutAlreadyCompleted(): self
    {
        return new self('Выплата уже завершена.');
    }

    public static function invalidPayoutStatus(): self
    {
        return new self('Нельзя изменить статус выплаты на текущем этапе.');
    }

    public static function geoNotConfigured(string $currency): self
    {
        return new self(
            "Для валюты {$currency} не настроен источник курсов. Обратитесь к администратору для настройки GEO мерчанта."
        );
    }

    public static function geoUnsupported(string $currency, string $market): self
    {
        return new self(
            "Маркет {$market} не поддерживает валюту {$currency}. Обратитесь к администратору для настройки GEO."
        );
    }
}

