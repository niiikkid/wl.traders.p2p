<?php

namespace App\Enums;

enum ShadowSmsLogFilterReason: string
{
    case SenderStopList = 'sender_stop_list';
    case StopWord = 'stop_word';
    case MaxMessageLength = 'max_message_length';

    public function label(): string
    {
        return match ($this) {
            self::SenderStopList => 'Отправитель в стоп-листе',
            self::StopWord => 'Стоп-слово',
            self::MaxMessageLength => 'Превышена длина сообщения',
        };
    }
}
