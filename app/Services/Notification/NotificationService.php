<?php

namespace App\Services\Notification;

use App\Contracts\NotificationServiceContract;
use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationRule;
use App\Services\Money\Money;
use App\Services\Notification\Events\NotificationEventInterface;
use App\Services\Notification\Events\TrustBalanceLowNotificationEvent;
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

                SendNotificationJob::dispatch(
                    $user->id,
                    $content->title,
                    $content->body
                )->onQueue('notifications');
            }
        }
    }

    protected function matchesRule(NotificationRule $rule, NotificationEventInterface $event): bool
    {
        if ($rule->event instanceof NotificationEvent && $rule->event->notEquals($event->type())) {
            return false;
        }

        if ($event->type()->equals(NotificationEvent::MESSAGE_RECEIVED)) {
            $eventPayload = $event->payload();
            $operationType = strtolower((string) ($eventPayload['operation_type'] ?? 'none'));

            if (! in_array($operationType, ['in', 'out'], true)) {
                return false;
            }

            $messageScope = $rule->message_scope ?? NotificationMessageScope::ALL;
            $hasOrder = (bool) ($eventPayload['has_order'] ?? false);

            if ($messageScope === NotificationMessageScope::WITH_ORDER && ! $hasOrder) {
                return false;
            }

            return true;
        }

        $eventCurrency = $event->currency();

        if ($event->type()->equals(NotificationEvent::TRUST_BALANCE_LOW)) {
            if (! $rule->min_amount_minor) {
                return false;
            }

            $thresholdCurrency = $rule->currency?->getCode() ?? $eventCurrency?->getCode();
            if (! $thresholdCurrency || ! ($event instanceof TrustBalanceLowNotificationEvent)) {
                return false;
            }

            $threshold = Money::fromUnits($rule->min_amount_minor, $thresholdCurrency);

            if (! $event->crossedBelow($threshold)) {
                return false;
            }
        } elseif ($event->type() !== NotificationEvent::WITHDRAWAL_REQUESTED) {
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
