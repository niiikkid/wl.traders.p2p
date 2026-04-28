<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TransactionType: string
{
    use Enumable;

    // out
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
    case CASCADE_PROVIDER_COLLATERAL_HOLD = 'cascade_provider_collateral_hold';
    case CASCADE_PROVIDER_ADMIN_WITHDRAWAL = 'cascade_provider_admin_withdrawal';

    // in
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
    case CASCADE_PROVIDER_COLLATERAL_RELEASE = 'cascade_provider_collateral_release';
    case CASCADE_PROVIDER_ADMIN_DEPOSIT = 'cascade_provider_admin_deposit';

    public function direction(): TransactionDirection
    {
        return match ($this) {
            self::PAYMENT_FOR_OPENED_ORDER,
            self::PAYMENT_FOR_OPENED_DISPUTE,
            self::WITHDRAWAL_BY_ADMIN,
            self::WITHDRAWAL_BY_USER,
            self::PAYMENT_FOR_CHANGE_ORDER_AMOUNT,
            self::ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER,
            self::ROLLBACK_INCOME_FROM_A_SUCCESSFUL_ORDER,
            self::ROLLBACK_INCOME_FROM_SUCCESSFUL_PAYOUT,
            self::ROLLBACK_INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT,
            self::PAYMENT_FOR_OPENED_PAYOUT,
            self::CASCADE_PROVIDER_COLLATERAL_HOLD,
            self::CASCADE_PROVIDER_ADMIN_WITHDRAWAL => TransactionDirection::OUT,
            self::REFUND_FOR_CANCELED_ORDER,
            self::REFUND_FOR_CANCELED_DISPUTE,
            self::DEPOSIT_BY_ADMIN,
            self::DEPOSIT_BY_USER,
            self::ROLLBACK_FOR_USER_WITHDRAWAL,
            self::INCOME_FROM_A_SUCCESSFUL_ORDER,
            self::REFUND_FOR_CHANGE_ORDER_AMOUNT,
            self::INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER,
            self::REFUND_FOR_CANCELED_PAYOUT,
            self::INCOME_FROM_SUCCESSFUL_PAYOUT,
            self::INCOME_FROM_REFERRALS_SUCCESSFUL_PAYOUT,
            self::CASCADE_PROVIDER_COLLATERAL_RELEASE,
            self::CASCADE_PROVIDER_ADMIN_DEPOSIT => TransactionDirection::IN,
        };
    }
}
