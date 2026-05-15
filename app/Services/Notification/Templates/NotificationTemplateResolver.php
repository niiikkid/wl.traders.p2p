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
                title: $this->telegramBoldTitle(trans('notifications.templates.withdrawal_requested.title')),
                body: trans('notifications.templates.withdrawal_requested.body', [
                    'user_email' => '<code>'.$this->e($payload['user_email'] ?? null).'</code>',
                    'amount' => '<b>'.$this->e($payload['amount'] ?? null).'</b>',
                    'currency' => '<b>'.$this->e(strtoupper((string) ($payload['currency'] ?? ''))).'</b>',
                    'invoice_id' => '<code>'.$this->e($payload['invoice_id'] ?? null).'</code>',
                ]),
                payload: $payload
            ),
            NotificationEvent::ORDER_ASSIGNED => new NotificationContent(
                title: $this->telegramBoldTitle(trans('notifications.templates.order_assigned.title')),
                body: trans('notifications.templates.order_assigned.body', [
                    'order_uuid' => '<code>'.$this->e($payload['order_uuid'] ?? null).'</code>',
                    'amount' => '<b>'.$this->e($payload['amount'] ?? null).'</b>',
                    'currency' => '<b>'.$this->e(strtoupper((string) ($payload['currency'] ?? ''))).'</b>',
                ]),
                payload: $payload
            ),
            NotificationEvent::DISPUTE_OPENED => new NotificationContent(
                title: $this->telegramBoldTitle(trans('notifications.templates.dispute_opened.title')),
                body: trans('notifications.templates.dispute_opened.body', [
                    'order_uuid' => '<code>'.$this->e($payload['order_uuid'] ?? null).'</code>',
                    'dispute_id' => '<code>'.$this->e($payload['dispute_id'] ?? null).'</code>',
                ]),
                payload: $payload
            ),
            NotificationEvent::TRUST_BALANCE_LOW => new NotificationContent(
                title: $this->telegramBoldTitle(trans('notifications.templates.trust_balance_low.title')),
                body: trans('notifications.templates.trust_balance_low.body', [
                    'current_balance' => '<b>'.$this->e($payload['current_balance'] ?? null).'</b>',
                    'currency' => '<b>'.$this->e(strtoupper((string) ($payload['currency'] ?? ''))).'</b>',
                ]),
                payload: $payload
            ),
            NotificationEvent::MESSAGE_RECEIVED => new NotificationContent(
                title: $this->telegramBoldTitle(trans('notifications.templates.message_received.title')),
                body: $this->buildMessageReceivedBody($payload),
                payload: $payload
            ),
        };
    }

    protected function buildMessageReceivedBody(array $payload): string
    {
        $messageType = ($payload['message_type'] ?? '') !== '' ? (string) $payload['message_type'] : 'UNKNOWN';
        $deviceName = ($payload['device_name'] ?? '') !== '' ? (string) $payload['device_name'] : 'Неизвестно';
        $operationType = strtolower((string) ($payload['operation_type'] ?? 'none'));
        $operationLabel = trans("notifications.templates.message_received.operation_types.{$operationType}");

        if ($operationLabel === "notifications.templates.message_received.operation_types.{$operationType}") {
            $operationLabel = trans('notifications.templates.message_received.operation_types.none');
        }

        $bank = ($payload['bank_name'] ?? '') !== ''
            ? (string) $payload['bank_name']
            : (($payload['sender'] ?? '') !== '' ? (string) $payload['sender'] : '-');
        $amount = ($payload['amount'] ?? '') !== '' ? (string) $payload['amount'] : '-';
        $card = ($payload['card_last_digits'] ?? '') !== '' ? '•••• '.(string) $payload['card_last_digits'] : '-';
        $balance = ($payload['balance'] ?? '') !== '' ? (string) $payload['balance'] : '-';

        $lines = [];
        $lines[] = '<b>Операция:</b> '.$this->e($operationLabel);
        $lines[] = '<b>Тип:</b> '.$this->e($messageType);
        $lines[] = '<b>Устройство:</b> '.$this->e($deviceName);
        $lines[] = '<b>Банк:</b> '.$this->e($bank);
        $lines[] = '<b>Сумма:</b> '.$this->e($amount);
        $lines[] = '<b>Карта:</b> <code>'.$this->e($card).'</code>';
        $lines[] = '<b>Баланс:</b> '.$this->e($balance);

        $rawMessage = ($payload['message'] ?? '') !== '' ? (string) $payload['message'] : '-';
        $lines[] = '<b>Текст</b>';
        $lines[] = '<blockquote>'.$this->e($rawMessage).'</blockquote>';

        if (! empty($payload['has_order'])) {
            $lines[] = '';
            $lines[] = '<b>Сделка</b>';
            $lines[] = '<b>UID:</b> <code>'.$this->e((string) ($payload['order_uid'] ?? '-')).'</code>';
            $lines[] = '<b>Создана:</b> '.$this->e((string) ($payload['order_created_at'] ?? '-'));
            $lines[] = '<b>Реквизит:</b> '.$this->e((string) ($payload['payment_detail'] ?? '-'));
            $lines[] = '<b>Название:</b> '.$this->e((string) ($payload['payment_detail_name'] ?? '-'));
            $lines[] = '<b>Владелец:</b> '.$this->e((string) ($payload['payment_detail_owner'] ?? '-'));
            $fiat = $this->e((string) ($payload['order_amount_fiat'] ?? '-'));
            $fiatCur = $this->e((string) ($payload['order_amount_fiat_currency'] ?? ''));
            $usdt = $this->e((string) ($payload['order_amount_usdt'] ?? '-'));
            $lines[] = '<b>Сумма:</b> '.$fiat.' '.$fiatCur.' / '.$usdt.' USDT';
        }

        return implode("\n", $lines);
    }

    protected function telegramBoldTitle(string $title): string
    {
        return '<b>'.$this->e($title).'</b>';
    }

    protected function e(mixed $value): string
    {
        return e((string) ($value ?? ''));
    }
}
