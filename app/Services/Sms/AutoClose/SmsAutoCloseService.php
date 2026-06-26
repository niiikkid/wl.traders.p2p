<?php

declare(strict_types=1);

namespace App\Services\Sms\AutoClose;

use App\Enums\DetailType;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Jobs\SendNotificationJob;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Sms\Parser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Безопасное автоматическое закрытие сделок по входящему поступлению.
 *
 * Закрываем сделку только при однозначном совпадении. Любая неоднозначность
 * или отсутствие подходящей сделки уходит трейдеру на ручную обработку — без угадывания.
 */
class SmsAutoCloseService
{
    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    /**
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    public function attempt(SmsLog $smsLog, UserDevice $device, User $user, array $parsingResult): void
    {
        // Закрываем только поступления (IN).
        if (! $this->isIncoming($parsingResult)) {
            return;
        }

        $amount = $this->roundedAmount($parsingResult['amount'] ?? null);

        if ($amount === null) {
            return;
        }

        // Круг поиска — устройство, с которого пришло сообщение.
        $candidates = queries()->order()
            ->pendingForDevice($device->id, $user->id)
            ->filter(fn (Order $order): bool => $this->orderRoundedAmount($order) === $amount)
            ->values();

        if ($candidates->isEmpty()) {
            $this->notifyManualReview($user, $smsLog, $parsingResult, noMatchingOrder: true);

            return;
        }

        if ($candidates->count() === 1) {
            $this->resolveSingleCandidate($candidates->first(), $smsLog, $user, $parsingResult);

            return;
        }

        $this->resolveMultipleCandidates($candidates, $smsLog, $user, $parsingResult);
    }

    /**
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function resolveSingleCandidate(Order $order, SmsLog $smsLog, User $user, array $parsingResult): void
    {
        // Карта есть в сообщении и явно не совпала с реквизитом — не закрываем, отдаём на ручную обработку.
        if ($this->matchCard($order, $parsingResult) === CardMatchResult::Mismatched) {
            $this->notifyManualReview($user, $smsLog, $parsingResult);

            return;
        }

        $this->closeOrder($order, $smsLog);
    }

    /**
     * Разрешение спора: Способ А (карта) -> Способ Б (ИИ по банку) -> Способ В (Telegram).
     *
     * @param  Collection<int, Order>  $candidates
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function resolveMultipleCandidates(Collection $candidates, SmsLog $smsLog, User $user, array $parsingResult): void
    {
        // Способ А — по карте.
        $cardMatched = $candidates
            ->filter(fn (Order $order): bool => $this->matchCard($order, $parsingResult) === CardMatchResult::Matched)
            ->values();

        if ($cardMatched->count() === 1) {
            $this->closeOrder($cardMatched->first(), $smsLog);

            return;
        }

        // Способ Б — по банку через ИИ (только если сделки на разные банки).
        $resolvedByBank = $this->resolveByBank($candidates, $smsLog, $parsingResult);

        if ($resolvedByBank instanceof Order) {
            $this->closeOrder($resolvedByBank, $smsLog);

            return;
        }

        // Способ В — ручная обработка.
        $this->notifyManualReview($user, $smsLog, $parsingResult);
    }

    /**
     * @param  Collection<int, Order>  $candidates
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function resolveByBank(Collection $candidates, SmsLog $smsLog, array $parsingResult): ?Order
    {
        // Если все сделки на один банк — ИИ-проверка бессмысленна.
        if ($candidates->pluck('payment_gateway_id')->unique()->count() < 2) {
            return null;
        }

        $confirmedBankIds = [];

        foreach ($candidates->unique('payment_gateway_id') as $order) {
            $gateway = $order->paymentGateway;

            if ($gateway === null) {
                continue;
            }

            $belongs = $this->parser->messageBelongsToBank(
                $smsLog->sender,
                $smsLog->message,
                $smsLog->type,
                (string) $gateway->name,
                (string) $gateway->code,
            );

            if ($belongs) {
                $confirmedBankIds[] = $gateway->id;
            }
        }

        if (empty($confirmedBankIds)) {
            return null;
        }

        $confirmed = $candidates
            ->filter(fn (Order $order): bool => in_array($order->payment_gateway_id, $confirmedBankIds, true))
            ->values();

        // Закрываем только если банк однозначно указывает ровно на одну сделку.
        return $confirmed->count() === 1 ? $confirmed->first() : null;
    }

    private function closeOrder(Order $order, SmsLog $smsLog): void
    {
        try {
            services()->order()->finishOrderAsSuccessful($order->id, OrderSubStatus::SUCCESSFULLY_PAID);
            $smsLog->update(['order_id' => $order->id]);
        } catch (OrderException $exception) {
            // Сделка уже завершена/изменена параллельным процессом — повторно не закрываем.
            Log::info('SMS auto-close skipped: order is no longer pending', [
                'order_id' => $order->id,
                'sms_log_id' => $smsLog->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function notifyManualReview(User $user, SmsLog $smsLog, array $parsingResult, bool $noMatchingOrder = false): void
    {
        $body = $noMatchingOrder
            ? 'Пришло зачисление, но открытой сделки с такой суммой на этом устройстве не найдено. Проверьте сообщение и обработайте вручную.'
            : 'Мы не смогли определить, для какой сделки пришёл платёж, пожалуйста, разберитесь вручную.';

        $amount = $parsingResult['amount'] ?? null;
        if (is_string($amount) && $amount !== '') {
            $body .= "\nСумма: {$amount}";
        }

        $bank = $parsingResult['bank'] ?? null;
        if (is_string($bank) && $bank !== '') {
            $body .= "\nОтправитель: {$bank}";
        }

        $rawMessage = trim($smsLog->message) !== '' ? $smsLog->message : '-';
        $body .= "\n\n<b>Текст</b>\n<blockquote>".e($rawMessage).'</blockquote>';

        SendNotificationJob::dispatch(
            $user->id,
            '<b>Платёж требует ручной обработки</b>',
            $body,
        )->onQueue('notifications');
    }

    /**
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function isIncoming(array $parsingResult): bool
    {
        return strtolower((string) ($parsingResult['operation_type'] ?? '')) === 'in';
    }

    private function roundedAmount(?string $raw): ?int
    {
        if (! is_string($raw) || ! is_numeric($raw)) {
            return null;
        }

        return (int) round((float) $raw);
    }

    private function orderRoundedAmount(Order $order): int
    {
        return (int) round((float) $order->amount->toPrecision());
    }

    /**
     * @param  array{operation_type?: string, amount?: ?string, card?: ?string, balance?: ?string, bank?: ?string}  $parsingResult
     */
    private function matchCard(Order $order, array $parsingResult): CardMatchResult
    {
        $smsCard = $this->lastFourDigits($parsingResult['card'] ?? null);

        if ($smsCard === null) {
            return CardMatchResult::Unknown;
        }

        $detail = $order->paymentDetail;

        // Сверка по карте имеет смысл только если реквизит — карта.
        if ($detail === null || $detail->detail_type !== DetailType::CARD) {
            return CardMatchResult::Unknown;
        }

        $detailCard = $this->lastFourDigits($detail->detail);

        if ($detailCard === null) {
            return CardMatchResult::Unknown;
        }

        return $smsCard === $detailCard ? CardMatchResult::Matched : CardMatchResult::Mismatched;
    }

    private function lastFourDigits(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (! is_string($digits) || strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }
}
