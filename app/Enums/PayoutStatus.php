<?php

namespace App\Enums;

use App\Traits\Enumable;

enum PayoutStatus: string
{
    use Enumable;

    case OPEN = 'open';          // в стакане
    case TAKEN = 'taken';        // закреплена за трейдером
    case SENT = 'sent';          // трейдер отметил, что отправил деньги
    case COMPLETED = 'completed';// холд истёк/деньги зачислены трейдеру
    case CANCELED = 'canceled';  // отменена и средства возвращены мерчанту
}


