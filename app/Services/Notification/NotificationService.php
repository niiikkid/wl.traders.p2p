<?php

namespace App\Services\Notification;

use App\Contracts\NotificationServiceContract;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEvent;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Services\Money\Money;
use App\Services\Notification\Events\NotificationEventInterface;
use App\Services\Notification\Templates\NotificationTemplateResolver;

class NotificationService implements NotificationServiceContract
{
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

            $rules = NotificationRule::query()
                ->where('user_id', $user->id)
                ->where('event', $event->type()->value)
                ->where('enabled', true)
                ->get();

            if ($rules->isEmpty()) {
                continue;
            }

            $content = $this->templateResolver->resolve($event);

            foreach ($rules as $rule) {
                if (! $this->matchesRule($rule, $event)) {
                    continue;
                }

                foreach ($rule->channels as $channel) {
                    $notification = Notification::create([
                        'user_id' => $user->id,
                        'event' => $event->type(),
                        'channel' => $channel,
                        'status' => NotificationDeliveryStatus::PENDING,
                        'title' => $content->title,
                        'body' => $content->body,
                        'payload' => $content->payload,
                    ]);

                    \App\Jobs\SendNotificationJob::dispatch($notification->id)->onQueue('notifications');
                }
            }
        }
    }

    protected function matchesRule(NotificationRule $rule, NotificationEventInterface $event): bool
    {
        if (empty($rule->channels)) {
            return false;
        }

        if ($rule->event instanceof NotificationEvent && $rule->event->notEquals($event->type())) {
            return false;
        }

        $eventCurrency = $event->currency();

        if ($event->type() !== NotificationEvent::WITHDRAWAL_REQUESTED) {
            if ($rule->currency && $eventCurrency && $rule->currency->notEquals($eventCurrency)) {
                return false;
            }

            if ($rule->min_amount_minor) {
                $eventAmount = $event->amount();
                if (! $eventAmount || ! $eventCurrency) {
                    return false;
                }

                $minCurrency = $rule->currency?->getCode() ?? $eventCurrency->getCode();
                $minAmount = Money::fromUnits($rule->min_amount_minor, $minCurrency);

                if ($eventAmount->lessThan($minAmount)) {
                    return false;
                }
            }
        }

        if (! empty($rule->statuses)) {
            $status = $event->status();

            if (! $status || ! in_array($status, $rule->statuses, true)) {
                return false;
            }
        }

        return true;
    }

}
