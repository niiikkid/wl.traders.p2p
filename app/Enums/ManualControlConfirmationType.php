<?php

namespace App\Enums;

use App\Traits\Enumable;

enum ManualControlConfirmationType: string
{
    use Enumable;

    case OTP_CODE = 'otp_code';
    case IN_APP_CONFIRMATION = 'in_app_confirmation';
    case BANK_CALL = 'bank_call';
    case OTP_CODE_AND_PIN_CODE = 'otp_code_and_pin_code';
    case SMS_WITH_INSTRUCTIONS = 'sms_with_instructions';

    public function title(): string
    {
        return match ($this) {
            self::OTP_CODE => 'OTP code',
            self::IN_APP_CONFIRMATION => 'In-app confirmation',
            self::BANK_CALL => 'Bank call',
            self::OTP_CODE_AND_PIN_CODE => 'OTP code and PIN code',
            self::SMS_WITH_INSTRUCTIONS => 'SMS with instructions',
        };
    }
}
