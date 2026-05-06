<?php

namespace App\Services\Sms\Profiles;

use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;

class UahSmsAmountParsingProfile implements SmsAmountParsingProfileContract
{
    public function triggerPatterns(): array
    {
        return [
            'зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
            'perekaz:\s+\S+\s+\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2}\s+na\s+kartku\s+\d+\*+\d+\s+na\s+sumu',
        ];
    }

    public function exceptionPatterns(): array
    {
        return [
            // 1) Любой префикс без цифр (эмодзи, слова, пробелы) — до первой суммы перед uah.
            '^\P{Nd}*(?<amount>\d+(.\d+){0,3})\s*uah\s*,\s*зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
            // 2) Минимальный префикс из любых символов до суммы (ленивая .+? — первая подходящая сумма в строке).
            '^.+?(?<amount>\d+(.\d+){0,3})\s*uah\s*,\s*зарахування\s+на\s+картку\s+\*+\d{4}\s+з\s+картки\s+іншого\s+банку',
            // freebank: только зачисление — «операция на +…» (минус / списание не обрабатываем).
            // После суммы комиссии отсекаем шаблон выплат «…₴ на картку:» (см. дебетовые SMS).
            '^freebank.*?операція\s+на\s+\+(?<amount>\d+(.\d+){0,3})\s*₴\s*комісія:\s*\d+(.\d+){0,3}\s*₴(?!\s*на\s+картку)',
            // freebank: сумма с пробелом тысяч («+1 210.00₴»); после комиссии «на картку:» — входящее (не шаблон выплат из предыдущей строки).
            '^freebank.*?операція\s+на\s+\+(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*₴\s*комісія:\s*\d+(.\d+){0,3}\s*₴\s*на\s+картку:',
            // Входящий перевод: стрелка ➡️ (U+27A1 + опц. VS) — сумма сразу после неё, не баланс в конце.
            '^\x{27A1}\x{FE0F}?\s+(?<amount>\d+(.\d+){0,3})\s*uah\s*\|\s*від\b',
            // Только со стрелкой в начале: зарахування на «кейс», сумма может быть «5 000.00» (не путать с балансом в конце).
            '^\x{27A1}\x{FE0F}?\s+(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*uah\s*\|\s*зарахування\s+на\s+кейс\b',
            // Rozetka MC: та же логика — первая сумма после ➡️, не «баланс: …».
            '^\x{27A1}\x{FE0F}?\s+(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*uah\s*\|\s*rozetka\s+card\s+mc\b',
            // A-Bank account-to-card (a2c): сумма без тысяч («529.00») или с пробелом — только после ➡️.
            '^\x{27A1}\x{FE0F}?\s+(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*uah\s*\|\s*a\-bank\s+a2c\b',
            // Mono / зараховано: … после «zarahovano +»; ФИО не перечисляем — \S+ и опциональные «слова» между * и card (латиница/кириллица в \S).
            'zarahovano\s+\+(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*uah\s+(?:(?:monodirect|abnk)\s+card|a2c\s+pumb\s+online\s+mob\s+card|p24\s+\*\S+(?:\s+\S+)*\s+card|fuib\s+moneytransfer\s+card|mono\*\S+(?:\s+\S+)*\s+card|abnk\*\S+(?:\s+\S+)*\s+card)\b',
            // «+… ₴ | переказ на картку …» — операційна сума на початку, не «баланс: …» в кінці.
            '^\+\s*(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*₴\s*\|\s*переказ\s+на\s+картку\b',
            // Monobank: «надходження | …uah monobank …», не «доступно: …».
            '^надходження\s*\|\s*(?<amount>\d+(?:\s\d{3})*(?:\.\d{2})?)\s*uah\s+monobank\b',
            // 💰 +сума ₴ | … баланс: … — толерантно к префиксу, пробелам, |/¦ и дробной части через . или ,.
            '(?:^|\s)\x{1F4B0}\x{FE0F}?\s*\+(?<amount>\d+(?:[\s\p{Zs}]\d{3})*(?:[.,]\d{2})?)\s*₴\s*[|¦](?=.*баланс\s*:)',
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
