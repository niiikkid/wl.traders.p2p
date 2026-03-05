<?php

namespace App\Services\Notification\Templates;

use App\Enums\NotificationEvent;
use App\Services\Notification\Events\NotificationEventInterface;

class NotificationTemplateResolver
{
    public function resolve(NotificationEventInterface $event): NotificationContent
    {
        $payload = $event->payload();
        $type = $event->type();

        return match ($type) {
            NotificationEvent::WITHDRAWAL_REQUESTED => new NotificationContent(
                title: trans('notifications.templates.withdrawal_requested.title'),
                body: trans('notifications.templates.withdrawal_requested.body', [
                    'user_email' => $payload['user_email'] ?? '',
                    'amount' => $payload['amount'] ?? '',
                    'currency' => strtoupper($payload['currency'] ?? ''),
                    'invoice_id' => $payload['invoice_id'] ?? '',
                ]),
                payload: $payload
            ),
            NotificationEvent::ORDER_ASSIGNED => new NotificationContent(
                title: trans('notifications.templates.order_assigned.title'),
                body: trans('notifications.templates.order_assigned.body', [
                    'order_uuid' => $payload['order_uuid'] ?? '',
                    'amount' => $payload['amount'] ?? '',
                    'currency' => strtoupper($payload['currency'] ?? ''),
                ]),
                payload: $payload
            ),
            NotificationEvent::DISPUTE_OPENED => new NotificationContent(
                title: trans('notifications.templates.dispute_opened.title'),
                body: trans('notifications.templates.dispute_opened.body', [
                    'order_uuid' => $payload['order_uuid'] ?? '',
                    'dispute_id' => $payload['dispute_id'] ?? '',
                ]),
                payload: $payload
            ),
        };
    }
}
