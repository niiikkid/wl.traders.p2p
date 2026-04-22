<?php

namespace App\Services\Sms\Profiles;

use App\Services\Money\Currency;
use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;

class SmsAmountParsingProfileResolver
{
    public function resolve(Currency $currency): SmsAmountParsingProfileContract
    {
        return match ($currency->getCode()) {
            'uah' => new UahSmsAmountParsingProfile(),
            'kzt' => new KztSmsAmountParsingProfile(),
            'rub' => new RubSmsAmountParsingProfile(),
            default => new RubSmsAmountParsingProfile(),
        };
    }
}
