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
            NotificationEvent::TRUST_BALANCE_LOW => new NotificationContent(
                title: trans('notifications.templates.trust_balance_low.title'),
                body: trans('notifications.templates.trust_balance_low.body', [
                    'current_balance' => $payload['current_balance'] ?? '',
                    'currency' => strtoupper($payload['currency'] ?? ''),
                ]),
                payload: $payload
            ),
            NotificationEvent::MESSAGE_RECEIVED => new NotificationContent(
                title: trans('notifications.templates.message_received.title'),
                body: $this->buildMessageReceivedBody($payload),
                payload: $payload
            ),
        };
    }

    protected function buildMessageReceivedBody(array $payload): string
    {
        $lines = [];
        $lines[] = 'Тип: '.($payload['message_type'] ?? 'UNKNOWN');
        $lines[] = 'Устройство: '.($payload['device_name'] ?? 'Неизвестно');

        if (! empty($payload['bank_name'])) {
            $lines[] = 'Банк: '.$payload['bank_name'];
        } else {
            $lines[] = 'Отправитель: '.($payload['sender'] ?? 'Неизвестно');
        }

        if (! empty($payload['amount'])) {
            $lines[] = 'Сумма в сообщении: '.$payload['amount'];
        }

        if (! empty($payload['card_last_digits'])) {
            $lines[] = 'Карта: *'.$payload['card_last_digits'];
        }

        $lines[] = 'Текст: '.($payload['message'] ?? '-');

        if (! empty($payload['has_order'])) {
            $lines[] = '';
            $lines[] = 'Сделка:';
            $lines[] = 'UID: '.($payload['order_uid'] ?? '-');
            $lines[] = 'Создана: '.($payload['order_created_at'] ?? '-');
            $lines[] = 'Реквизит: '.($payload['payment_detail'] ?? '-');
            $lines[] = 'Название: '.($payload['payment_detail_name'] ?? '-');
            $lines[] = 'Владелец: '.($payload['payment_detail_owner'] ?? '-');
            $lines[] = 'Сумма: '
                .($payload['order_amount_fiat'] ?? '-')
                .' '
                .($payload['order_amount_fiat_currency'] ?? '')
                .' / '
                .($payload['order_amount_usdt'] ?? '-')
                .' USDT';
        }

        return implode("\n", $lines);
    }
}
