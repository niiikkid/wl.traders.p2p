<?php

namespace App\Services\Sms\Profiles;

use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;

/**
 * Паттерны SMS для тенге (KZT). Казахский банк (Home Credit KZ) + те же русскоязычные триггеры, что у RUB (SMS часто на русском).
 */
class KztSmsAmountParsingProfile implements SmsAmountParsingProfileContract
{
    public function triggerPatterns(): array
    {
        return array_merge(
            [
                'home\scredit\skazakhstan\sкарточка\s.+\sпополнена\sна\s',
            ],
            (new RubSmsAmountParsingProfile())->triggerPatterns(),
        );
    }

    public function exceptionPatterns(): array
    {
        return [];
    }

    public function amountCurrencyMarkers(): string
    {
        return '₸|kzt|KZT|тг|тенге|tenge|Tenge';
    }

    public function cardLastDigitsPattern(): string
    {
        return '(\*|^mir|\smir|счёт|mir-|ecmc|\s••\s|\s\d{6}\.\.|карта\s\*\*\*\s|^карта\s|\s··|\sмир)(?<card_last_digits>\d{4})(\D|$)';
    }
}
