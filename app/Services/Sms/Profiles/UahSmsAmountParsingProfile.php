<?php

namespace App\Services\Sms\Profiles;

use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;


class UahSmsAmountParsingProfile implements SmsAmountParsingProfileContract
{
    public function triggerPatterns(): array
    {
        return [
            'зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
            'perekaz:\s+\S+\s+\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2}\s+na\s+kartku\s+\d+\*+\d+\s+na\s+sumu'
        ];
    }

    public function exceptionPatterns(): array
    {
        return [
            // 1) Любой префикс без цифр (эмодзи, слова, пробелы) — до первой суммы перед uah.
            '^\P{Nd}*(?<amount>\d+(.\d+){0,3})\s*uah\s*,\s*зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
            // 2) Минимальный префикс из любых символов до суммы (ленивая .+? — первая подходящая сумма в строке).
            '^.+?(?<amount>\d+(.\d+){0,3})\s*uah\s*,\s*зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
        ];
    }

    public function amountCurrencyMarkers(): string
    {
        // Коды/суффиксы после числа в SMS (дополните под ваши шаблоны).
        return 'UAH|uah|грн|₴|грив';
    }

    public function cardLastDigitsPattern(): string
    {
        return '(?:зарахування\s+на\s+картку\s+\*+|\*|^mir|\smir|счёт|mir-|ecmc|\s••\s|\s\d{6}\.\.|карта\s\*\*\*\s|^карта\s|\s··|\sмир)(?<card_last_digits>\d{4})(\D|$)';
    }
}
