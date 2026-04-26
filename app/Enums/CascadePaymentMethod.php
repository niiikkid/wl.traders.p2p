<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadePaymentMethod: string
{
    use Enumable;

    case CARD = 'card'; // Оплата картой
    case SBP = 'sbp'; // Система быстрых платежей
    case MOBILE_COMMERCE = 'mobile_commerce'; // Мобильная коммерция
    case IBAN_UAH = 'iban_uah'; // IBAN UAH
    case E_COM = 'e-com'; // E-COM

    public function detailType(): DetailType
    {
        return match ($this) {
            self::CARD => DetailType::CARD,
            self::SBP => DetailType::PHONE,
            self::MOBILE_COMMERCE => DetailType::MOBILE_COMMERCE,
            self::IBAN_UAH => DetailType::IBAN_UAH,
            self::E_COM => DetailType::E_COM,
        };
    }

    public static function fromDetailType(DetailType $detailType): ?self
    {
        return match ($detailType) {
            DetailType::CARD => self::CARD,
            DetailType::PHONE => self::SBP,
            DetailType::MOBILE_COMMERCE => self::MOBILE_COMMERCE,
            DetailType::IBAN_UAH => self::IBAN_UAH,
            DetailType::E_COM => self::E_COM,
            default => null,
        };
    }
}
