<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TransactionType: string
{
    use Enumable;

    //out
    case PAYMENT_FOR_OPENED_ORDER = 'payment_for_opened_order';
    case PAYMENT_FOR_OPENED_DISPUTE = 'payment_for_opened_dispute';
    case WITHDRAWAL_BY_ADMIN = 'withdrawal_by_admin';
    case WITHDRAWAL_BY_USER = 'withdrawal_by_user';
    case ROLLBACK_INCOME_FROM_A_SUCCESSFUL_ORDER = 'rollback_income_from_a_successful_order';
    case ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER = 'rollback_income_from_referrals_successful_order';
    case PAYMENT_FOR_CHANGE_ORDER_AMOUNT = 'payment_for_change_order_amount';
    case PAYMENT_FOR_OPENED_PAYOUT = 'payment_for_opened_payout';
    case ROLLBACK_INCOME_FROM_SUCCESSFUL_PAYOUT = 'rollback_income_from_successful_payout';
    case ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT = 'rollback_income_from_referrals_successful_payout';

    //in
    case REFUND_FOR_CANCELED_ORDER = 'refund_for_canceled_order';
    case REFUND_FOR_CANCELED_DISPUTE = 'refund_for_canceled_dispute';
    case DEPOSIT_BY_ADMIN = 'deposit_by_admin';
    case DEPOSIT_BY_USER = 'deposit_by_user';
    case ROLLBACK_FOR_USER_WITHDRAWAL = 'rollback_for_user_withdrawal';
    case INCOME_FROM_A_SUCCESSFUL_ORDER = 'income_from_a_successful_order';
    case INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER = 'income_from_referrals_successful_order';
    case REFUND_FOR_CHANGE_ORDER_AMOUNT = 'refund_for_change_order_amount';
    case REFUND_FOR_CANCELED_PAYOUT = 'refund_for_canceled_payout';
    case INCOME_FROM_SUCCESSFUL_PAYOUT = 'income_from_successful_payout';
    case INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT = 'income_from_referrals_successful_payout';

    public function direction(): TransactionDirection
    {
        return match ($this)
        {
            static::PAYMENT_FOR_OPENED_ORDER,
            static::PAYMENT_FOR_OPENED_DISPUTE,
            static::WITHDRAWAL_BY_ADMIN,
            static::WITHDRAWAL_BY_USER,
            static::PAYMENT_FOR_CHANGE_ORDER_AMOUNT,
            static::ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER,
            static::ROLLBACK_INCOME_FROM_A_SUCCESSFUL_ORDER,
            static::ROLLBACK_INCOME_FROM_SUCCESSFUL_PAYOUT,
            static::ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT,
            static::PAYMENT_FOR_OPENED_PAYOUT => TransactionDirection::OUT,
            static::REFUND_FOR_CANCELED_ORDER,
            static::REFUND_FOR_CANCELED_DISPUTE,
            static::DEPOSIT_BY_ADMIN,
            static::DEPOSIT_BY_USER,
            static::ROLLBACK_FOR_USER_WITHDRAWAL,
            static::INCOME_FROM_A_SUCCESSFUL_ORDER,
            static::REFUND_FOR_CHANGE_ORDER_AMOUNT,
            static::INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER,
            static::REFUND_FOR_CANCELED_PAYOUT,
            static::INCOME_FROM_SUCCESSFUL_PAYOUT,
            static::INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT => TransactionDirection::IN,
        };
    }
}
