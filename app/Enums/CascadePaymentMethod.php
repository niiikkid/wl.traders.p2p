<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadePaymentMethod: string
{
    use Enumable;

    case CARD = 'card'; // Оплата картой
    case SBP = 'sbp'; // Система быстрых платежей
}
