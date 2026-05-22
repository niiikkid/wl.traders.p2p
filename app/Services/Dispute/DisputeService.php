<?php

namespace App\Services\Dispute;

use App\Contracts\DisputeServiceContract;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Events\DisputeOpenedEvent;
use App\Exceptions\DisputeException;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Utils\Transaction;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DisputeService implements DisputeServiceContract
{
    private const BANK_STATEMENT_DIRECTORY = 'dispute-bank-statements';

    /**
     * @throws DisputeException
     */
    public function create(int $orderID, ?UploadedFile $receipt = null): Dispute
    {
        return Transaction::run(function () use ($orderID, $receipt) {
            $order = Order::where('id', $orderID)->with('dispute')->lockForUpdate()->first();

            if ($order->dispute) {
                throw new DisputeException('Dispute already exists.');
            }

            if ($order->status->equals(OrderStatus::PENDING)) {
                services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::CANCELED);
                $order = Order::where('id', $orderID)->lockForUpdate()->first();
            }

            if ($order->status->equals(OrderStatus::SUCCESS) || $order->status->equals(OrderStatus::FAIL)) {
                services()->order()->reopenFinishedOrder($order->id, OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED);
            }

            if ($receipt) {
                $receipt_name = 'receipt_'.strtolower(Str::random(32)).'.'.$receipt->extension();
                $receipt->move(storage_path('receipts'), $receipt_name);
            } else {
                $receipt_name = null;
            }

            $dispute = Dispute::create([
                'receipt' => $receipt_name,
                'order_id' => $order->id,
                'trader_id' => $order->paymentDetail->user_id,
                'status' => DisputeStatus::PENDING,
            ]);

            DisputeOpenedEvent::dispatch($dispute);

            return $dispute;
        });
    }

    public function accept(int $disputeID): bool
    {
        return Transaction::run(function () use ($disputeID) {
            $dispute = Dispute::where('id', $disputeID)->lockForUpdate()->first();

            if ($dispute->status->notEquals(DisputeStatus::PENDING)) {
                throw new DisputeException('Dispute must be pending.');
            }

            services()->order()->finishOrderAsSuccessful($dispute->order_id, OrderSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE);

            return $dispute->update([
                'status' => DisputeStatus::ACCEPTED,
            ]);
        });
    }

    public function cancel(int $disputeID, string $reason, UploadedFile $bankStatement): bool
    {
        return Transaction::run(function () use ($disputeID, $reason, $bankStatement) {
            $dispute = Dispute::where('id', $disputeID)->lockForUpdate()->first();

            if ($dispute->status->notEquals(DisputeStatus::PENDING)) {
                throw new DisputeException('Dispute must be pending.');
            }

            services()->order()->finishOrderAsFailed($dispute->order_id, OrderSubStatus::CANCELED_BY_DISPUTE);

            $bankStatementFilename = $this->replaceBankStatement($dispute->bank_statement, $bankStatement);

            $updated = $dispute->update([
                'status' => DisputeStatus::CANCELED,
                'reason' => $reason,
                'bank_statement' => $bankStatementFilename,
            ]);

            // Проверяем количество отклоненных споров и отключаем трафик если нужно
            $this->checkRejectedDisputesLimit($dispute->trader_id);

            return $updated;
        });
    }

    /**
     * Проверяет количество отклоненных споров у трейдера за указанный период
     * При превышении лимита отключает трафик
     */
    protected function checkRejectedDisputesLimit(int $traderId): void
    {
        $trader = User::findOrFail($traderId);
        $maxRejectedDisputes = services()->settings()->getMaxRejectedDisputes();

        // Если лимит не установлен (count = 0), то не проверяем
        if ($maxRejectedDisputes['count'] <= 0 || $maxRejectedDisputes['period'] <= 0) {
            return;
        }

        $periodMinutes = $maxRejectedDisputes['period'];
        $maxCount = $maxRejectedDisputes['count'];

        // Определяем, с какой даты считаем споры
        // Используем более позднюю из двух дат:
        // 1. Дата последнего включения трафика
        // 2. Текущее время минус период настройки
        $periodDate = Carbon::now()->subMinutes($periodMinutes);
        $sinceDate = $trader->traffic_enabled_at && $trader->traffic_enabled_at->isAfter($periodDate)
            ? $trader->traffic_enabled_at
            : $periodDate;

        // Считаем количество отклоненных споров за указанный период
        $count = Dispute::where('trader_id', $traderId)
            ->where('status', DisputeStatus::CANCELED)
            ->where('created_at', '>=', $sinceDate)
            ->count();

        // Если количество отклоненных споров превышает лимит, отключаем трафик
        if ($count >= $maxCount) {
            $trader->update([
                'stop_traffic' => true,
            ]);
        }
    }

    public function rollback(int $disputeID): bool
    {
        return Transaction::run(function () use ($disputeID) {
            $dispute = Dispute::where('id', $disputeID)->lockForUpdate()->first();

            if ($dispute->status->equals(DisputeStatus::PENDING)) {
                throw new DisputeException('Cannot rollback pending dispute.');
            }

            services()->order()->reopenFinishedOrder($dispute->order_id, OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED);

            return $dispute->update([
                'status' => DisputeStatus::PENDING,
                'reason' => null,
            ]);
        });
    }

    public function storeBankStatement(UploadedFile $bankStatement): string
    {
        $this->ensureBankStatementDirectoryExists();

        $extension = strtolower($bankStatement->getClientOriginalExtension() ?: $bankStatement->extension() ?: 'bin');
        $filename = 'bank_statement_'.strtolower(Str::random(32));
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $bankStatement->move(storage_path(self::BANK_STATEMENT_DIRECTORY), $filename);

        return $filename;
    }

    public function deleteBankStatement(?string $filename): void
    {
        if (! $filename) {
            return;
        }

        $path = storage_path(self::BANK_STATEMENT_DIRECTORY.'/'.$filename);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function replaceBankStatement(?string $existingFilename, UploadedFile $bankStatement): string
    {
        $this->deleteBankStatement($existingFilename);

        return $this->storeBankStatement($bankStatement);
    }

    private function ensureBankStatementDirectoryExists(): void
    {
        $directory = storage_path(self::BANK_STATEMENT_DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
