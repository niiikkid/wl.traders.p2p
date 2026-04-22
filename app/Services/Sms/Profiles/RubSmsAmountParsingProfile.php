<?php

namespace App\Services\Sms\Profiles;

use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;

class RubSmsAmountParsingProfile implements SmsAmountParsingProfileContract
{
    public function triggerPatterns(): array
    {
        return [
            'перевод\s(?<amount>\d+(.\d+){0,3})р\sот\s.+\sбаланс',
            'перевод\sна\sсумму\s.+\sиз\s.+\sот\s',
            'perevod\s.+\sot\s.+\siz\s.+\sna\sschet\s',
            'зачислен перевод по',
            'поступление',
            'пополнение',
            'перевод по сбп',
            'зачисление',
            'зачислено',
            '[а-я]+\sпополнена',
            'popolnenie scheta',
            'postuplenie sredstv na schet',
            'postuplenie',
            'получен перевод',
            'popolnenie',
            'приход на карту',
            'перевод из',
            'vneseno',
            'перевел\(а\) вам',
            'postupil perevod',
            'перевод денежных средств',
            'перевод на карту',
            'zachislenie',
            '^перевод\sот\s',
            'приход',
            'пополнили карту',
            '\sперевод\s.+\sна\sкарту',
            '\sвы\sполучили\sперевод:\s',
        ];
    }

    public function exceptionPatterns(): array
    {
        return [
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\.\sтеперь\sна\sкарте\s.+₽$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s-\sбаланс\:\s.+$',
            '^\d{2}\.\d{2}\.\d{2}\s\d{2}\:\d{2}\sзачисление\s\*(?<card_last_digits>\d{4})\srur\s(?<amount>\d+(.\d+){0,3})\;\sостаток\s.+$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\sот\s.+теперь\sна\sсчете\s.+₽$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sтеперь\sу\sвас\:\s.+$',
            '^\d{2}\:\d{2}\sперевод\s(?<amount>\d+(.\d+){0,3})р\sна\sкарту\s.+\sбаланс\s.+$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sбаланс\:\s.+$',
            '^совкомбанк\s\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sбаланс\:\s.+(?<card_last_digits>\d{4})$',
        ];
    }

    public function amountCurrencyMarkers(): string
    {
        return 'RUB|rub|р|p|₽|RUR|rur|rurcard2card|руб';
    }

    public function cardLastDigitsPattern(): string
    {
        return '(\*|^mir|\smir|счёт|mir-|ecmc|\s••\s|\s\d{6}\.\.|карта\s\*\*\*\s|^карта\s|\s··|\sмир)(?<card_last_digits>\d{4})(\D|$)';
    }
}
