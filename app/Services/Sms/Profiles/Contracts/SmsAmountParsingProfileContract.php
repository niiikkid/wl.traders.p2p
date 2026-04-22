<?php

namespace App\Services\Sms\Profiles\Contracts;

/**
 * Набор правил парсинга суммы из SMS для одной валюты.
 *
 * Заполняйте реализации в app/Services/Sms/Profiles/*SmsAmountParsingProfile.php
 */
interface SmsAmountParsingProfileContract
{
    /**
     * Фразы/фрагменты: при совпадении ищем сумму через {@see self::amountCurrencyMarkers()}.
     *
     * @return list<string>
     */
    public function triggerPatterns(): array;

    /**
     * Полные regex-шаблоны с именованной группой (?<amount>...), дающие сумму напрямую.
     *
     * @return list<string>
     */
    public function exceptionPatterns(): array;

    /**
     * Часть regex после суммы: альтернативы кодов/символов валюты (без внешних скобок не обязательно — см. Parser).
     */
    public function amountCurrencyMarkers(): string;

    /**
     * Тело regex для последних 4 цифр карты (без ограничителей /.../ и флагов).
     */
    public function cardLastDigitsPattern(): string;
}
