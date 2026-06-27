<?php

namespace App\Services\Notification;

use App\Contracts\NotificationServiceContract;
use App\Enums\NotificationEvent;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use App\Services\Money\Money;
use App\Services\Notification\Events\NotificationEventInterface;
use App\Services\Notification\Events\TrustBalanceLowNotificationEvent;
use App\Services\Notification\Templates\NotificationTemplateResolver;

class NotificationService implements NotificationServiceContract
{
    protected const TRUST_BALANCE_LOW_THRESHOLD = '300';

    public function __construct(
        protected NotificationTemplateResolver $templateResolver
    ) {}

    public function dispatch(NotificationEventInterface $event): void
    {
        $recipients = $event->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $user) {
            if (! $event->type()->isAllowedForUser($user)) {
                continue;
            }

            if (! $this->hasActiveTelegramAccount($user)) {
                continue;
            }

            if (! $this->shouldDispatch($event)) {
                continue;
            }

            $content = $this->templateResolver->resolve($event);

            SendNotificationJob::dispatch(
                $user->id,
                $content->title,
                $content->body
            )->onQueue('notifications');
        }
    }

    protected function shouldDispatch(NotificationEventInterface $event): bool
    {
        if ($event->type()->equals(NotificationEvent::TRUST_BALANCE_LOW)) {
            if (! $event instanceof TrustBalanceLowNotificationEvent) {
                return false;
            }

            $threshold = Money::fromPrecision(self::TRUST_BALANCE_LOW_THRESHOLD, $event->currency()?->getCode() ?? 'USDT');

            return $event->isBelow($threshold);
        }

        return true;
    }

    protected function hasActiveTelegramAccount(User $user): bool
    {
        $account = $user->relationLoaded('telegramAccount')
            ? $user->telegramAccount
            : $user->telegramAccount()->first();

        return (bool) $account?->is_active && ! empty($account->chat_id);
    }
}
