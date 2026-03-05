<?php

namespace App\Contracts;

use App\DTO\SMS\SmsDTO;

interface SmsServiceContract
{
    public function handleSms(SmsDTO $sms): void;
}
