<?php

namespace App\Enums;

use App\Traits\Enumable;

enum UserActivitySubjectType: string
{
    use Enumable;

    case User = 'user';
    case Role = 'role';
    case Merchant = 'merchant';
    case PaymentDetail = 'payment_detail';
    case PaymentGateway = 'payment_gateway';
    case Order = 'order';
    case Payout = 'payout';
    case Dispute = 'dispute';
    case Wallet = 'wallet';
    case Transaction = 'transaction';
    case Invoice = 'invoice';
    case Setting = 'setting';
    case OpenAiSetting = 'open_ai_setting';
    case AntiFraudSetting = 'anti_fraud_setting';
    case TelegramBotSetting = 'telegram_bot_setting';
}
