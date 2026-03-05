<?php

namespace App\Enums;

use App\Models\User;
use App\Traits\Enumable;

enum NotificationEvent: string
{
    use Enumable;

    case WITHDRAWAL_REQUESTED = 'withdrawal.requested';
    case ORDER_ASSIGNED = 'order.assigned';
    case DISPUTE_OPENED = 'dispute.opened';

    public function label(): string
    {
        return trans("notifications.events.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::WITHDRAWAL_REQUESTED => ['Super Admin'],
            self::ORDER_ASSIGNED => ['Trader'],
            self::DISPUTE_OPENED => ['Trader'],
        };
    }

    public function isAllowedForUser(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->hasRole($this->allowedRoles());
    }

    /**
     * @return array<int, self>
     */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(static::cases(), function (self $event) use ($user) {
            return $event->isAllowedForUser($user);
        }));
    }
}
