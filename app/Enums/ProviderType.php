<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum ProviderType: string
{
    use Enumable;

    case INTERNAL = 'internal'; // Внутренний провайдер (наши трейдеры/реквизиты)
    case EXTERNAL = 'external'; // Внешний провайдер (внешний сервис)
}
