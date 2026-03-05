<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeTransactionStatus: string
{
    use Enumable;

    case CREATED = 'created'; // Сделка создана у провайдера
    case FAILED = 'failed'; // Не удалось создать сделку
    case CANCELLED = 'cancelled'; // Сделка отменена (не выбрана как победитель)
    case SUCCESS = 'success'; // Сделка успешно завершена
}
