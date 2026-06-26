<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Exceptions\OrderSmsLogException;
use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderSmsLogLinkService
{
    private const UNLINKED_SMS_LIMIT = 50;

    /**
     * @return Collection<int, SmsLog>
     */
    public function unlinkedIncomingForOrder(Order $order): Collection
    {
        $order->loadMissing('paymentDetail:id,user_device_id');

        return SmsLog::query()
            ->incomingPayments()
            ->awaitingLink()
            ->where('user_id', $order->trader_id)
            ->when(
                $order->paymentDetail?->user_device_id,
                fn ($query, int $deviceId) => $query->where('user_device_id', $deviceId),
            )
            ->with('device:id,name')
            ->orderByDesc('id')
            ->limit(self::UNLINKED_SMS_LIMIT)
            ->get();
    }

    public function paginateUnlinkedOrdersForSmsLog(
        SmsLog $smsLog,
        ?string $amount,
        ?string $paymentDetail,
        int $perPage,
    ): LengthAwarePaginator {
        $smsLog->loadMissing('device:id,name');

        return Order::query()
            ->whereNotNull('payment_detail_id')
            ->where('trader_id', $smsLog->user_id)
            ->whereRelation('paymentDetail', 'user_id', $smsLog->user_id)
            ->whereDoesntHave('smsLog')
            ->whereRelation('paymentDetail', 'user_device_id', $smsLog->user_device_id)
            ->with([
                'trader:id,email,name',
                'paymentGateway:id,logo,name',
                'paymentDetail:id,detail,detail_type,name,additional_info,user_device_id,user_id',
                'paymentDetail.userDevice:id,name',
                'paymentDetail.user:id,name,email',
            ])
            ->select([
                'id',
                'uuid',
                'amount',
                'currency',
                'total_profit',
                'status',
                'created_at',
                'payment_gateway_id',
                'payment_detail_id',
                'trader_id',
                'manual_control_acquiring',
            ])
            ->when(filled($amount), fn (Builder $query) => $this->applyExactAmountFilter($query, (string) $amount))
            ->when(filled($paymentDetail), function (Builder $query) use ($paymentDetail): void {
                $query->whereRelation('paymentDetail', 'detail', 'LIKE', '%'.$paymentDetail.'%');
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function link(Order $order, int $smsLogId): SmsLog
    {
        return DB::transaction(function () use ($order, $smsLogId): SmsLog {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (SmsLog::query()->where('order_id', $lockedOrder->id)->exists()) {
                throw OrderSmsLogException::orderAlreadyHasSms();
            }

            $smsLog = SmsLog::query()
                ->unlinked()
                ->notRejected()
                ->linkableToOrder()
                ->whereKey($smsLogId)
                ->where('user_id', $lockedOrder->trader_id)
                ->lockForUpdate()
                ->first();

            if ($smsLog === null) {
                throw OrderSmsLogException::smsLogNotAvailable();
            }

            $lockedOrder->loadMissing('paymentDetail:id,user_device_id');

            if ($lockedOrder->paymentDetail?->user_device_id !== $smsLog->user_device_id) {
                throw OrderSmsLogException::smsLogNotAvailable();
            }

            $smsLog->update(['order_id' => $lockedOrder->id]);

            return $smsLog->fresh(['device:id,name']);
        });
    }

    public function roundedOrderAmount(Order $order): ?int
    {
        return (int) round((float) $order->amount->toPrecision());
    }

    public function roundedSmsAmount(?string $rawAmount): ?int
    {
        if (! is_string($rawAmount) || ! is_numeric($rawAmount)) {
            return null;
        }

        return (int) round((float) $rawAmount);
    }

    private function applyExactAmountFilter(Builder $query, string $amount): void
    {
        $query->where(function (Builder $query) use ($amount): void {
            foreach (Currency::getAll() as $currency) {
                if ($currency->getCode() === Currency::USDT()->getCode()) {
                    continue;
                }

                $units = Money::fromPrecision($amount, $currency->getCode())->toUnits();

                $query->orWhere(function (Builder $query) use ($currency, $units): void {
                    $query->where('currency', $currency->getCode())
                        ->where('amount', $units);
                });
            }
        });
    }
}
