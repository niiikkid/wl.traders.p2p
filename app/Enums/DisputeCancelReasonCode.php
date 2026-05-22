<?php

namespace App\Enums;

use App\Traits\Enumable;

enum DisputeCancelReasonCode: string
{
    use Enumable;

    case WRONG_DETAILS = 'wrong_details';
    case FAKE_RECEIPT = 'fake_receipt';
    case PAYMENT_RETURN = 'payment_return';
    case OTHER = 'other';

    public function isBankStatementRequired(): bool
    {
        return $this->notEquals(self::WRONG_DETAILS);
    }

    public function resolveReasonText(?string $customReason): string
    {
        return match ($this) {
            self::WRONG_DETAILS => 'Неверные реквизиты',
            self::FAKE_RECEIPT => 'Фейковый чек',
            self::PAYMENT_RETURN => 'Нет оплаты(лимит/возврат)',
            self::OTHER => trim((string) $customReason),
        };
    }
}
