<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum PaymentDetailScheduleStatus: string
{
    use Enumable;

    case NotConfigured = 'not_configured';
    case Working = 'working';
    case DayOff = 'day_off';
    case StartsLater = 'starts_later';
    case BreakUntil = 'break_until';
    case Finished = 'finished';
    case Invalid = 'invalid';

    public function label(?string $time = null): string
    {
        return match ($this) {
            self::NotConfigured => 'Без расписания',
            self::Working => 'Работает',
            self::DayOff => 'Выходной',
            self::StartsLater => 'Скоро начнёт работу',
            self::BreakUntil => $time !== null ? "Перерыв до {$time}" : 'Перерыв',
            self::Finished => 'Рабочее время закончилось',
            self::Invalid => 'Некорректное расписание',
        };
    }
}
