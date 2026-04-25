<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeTransactionStatus: string
{
    use Enumable;

    case OPENED = 'opened'; // Сделка успешно открыта у провайдера
    case FAILED_TO_OPEN = 'failed_to_open'; // Не удалось открыть сделку у провайдера
    case CANCELLED = 'cancelled'; // Сделка отклонена, закрыта во внешнем сервисе
    case ACCEPTED = 'accepted'; // Сделка принята в работу, выбрана для использования
}
