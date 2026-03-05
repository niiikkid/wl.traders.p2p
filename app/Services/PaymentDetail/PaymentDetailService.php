<?php

namespace App\Services\PaymentDetail;

use App\Contracts\PaymentDetailServiceContract;
use App\DTO\PaymentDetail\PaymentDetailCreateDTO;
use App\DTO\PaymentDetail\PaymentDetailUpdateDTO;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;

class PaymentDetailService implements PaymentDetailServiceContract
{
    public function create(PaymentDetailCreateDTO $data): PaymentDetail
    {
        return $this->transaction(function () use ($data) {
            $paymentDetail = PaymentDetail::create([
                'name' => $data->name,
                'detail' => $data->detail,
                'detail_type' => $data->detail_type,
                'initials' => $data->initials,
                'is_active' => $data->is_active,
                'daily_limit' => Money::fromPrecision($data->daily_limit, Currency::make($data->currency)),
                'current_daily_limit' => Money::fromPrecision(0, Currency::make($data->currency)),
                'daily_successful_orders_limit' => $data->daily_successful_orders_limit,
                'current_daily_successful_orders_count' => 0,
                'max_pending_orders_quantity' => $data->max_pending_orders_quantity,
                'min_order_amount' => $data->min_order_amount ? Money::fromPrecision($data->min_order_amount, Currency::make($data->currency)) : null,
                'max_order_amount' => $data->max_order_amount ? Money::fromPrecision($data->max_order_amount, Currency::make($data->currency)) : null,
                'vip_min_order_amount_backup' => $data->min_order_amount ? Money::fromPrecision($data->min_order_amount, Currency::make($data->currency)) : null,
                'vip_max_order_amount_backup' => $data->max_order_amount ? Money::fromPrecision($data->max_order_amount, Currency::make($data->currency)) : null,
                'order_interval_minutes' => $data->order_interval_minutes,
                'currency' => Currency::make($data->currency),
                'user_id' => $data->user_id,
                'user_device_id' => $data->user_device_id,
                'last_used_at' => now(),
            ]);

            // sync gateways (with timestamps)
            $syncData = collect($data->payment_gateway_ids)->mapWithKeys(function ($id) {
                return [$id => ['created_at' => now(), 'updated_at' => now()]];
            })->all();
            $paymentDetail->paymentGateways()->sync($syncData);

            return $paymentDetail;
        });
    }

    public function update(PaymentDetailUpdateDTO $data, PaymentDetail $paymentDetail): PaymentDetail
    {
        return $this->transaction(function () use ($data, $paymentDetail) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetail->id)->lockForUpdate()->first();

            $paymentDetail->update([
                'name' => $data->name,
                'initials' => $data->initials,
                'is_active' => $data->is_active,
                'daily_limit' => Money::fromPrecision($data->daily_limit, $paymentDetail->currency),
                'daily_successful_orders_limit' => $data->daily_successful_orders_limit,
                'min_order_amount' => $data->min_order_amount ? Money::fromPrecision($data->min_order_amount, $paymentDetail->currency) : null,
                'max_order_amount' => $data->max_order_amount ? Money::fromPrecision($data->max_order_amount, $paymentDetail->currency) : null,
                'vip_min_order_amount_backup' => $data->min_order_amount ? Money::fromPrecision($data->min_order_amount, $paymentDetail->currency) : null,
                'vip_max_order_amount_backup' => $data->max_order_amount ? Money::fromPrecision($data->max_order_amount, $paymentDetail->currency) : null,
                'order_interval_minutes' => $data->order_interval_minutes,
                'max_pending_orders_quantity' => $data->max_pending_orders_quantity,
                'user_device_id' => $data->user_device_id,
            ]);

            $syncData = collect($data->payment_gateway_ids)->mapWithKeys(function ($id) {
                return [$id => ['created_at' => now(), 'updated_at' => now()]];
            })->all();
            $paymentDetail->paymentGateways()->sync($syncData);

            return $paymentDetail;
        });
    }

    public function toggleActive(PaymentDetail $paymentDetail): void
    {
        $this->transaction(function () use ($paymentDetail) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetail->id)->lockForUpdate()->first();
            $paymentDetail->update(['is_active' => ! $paymentDetail->is_active]);
        });
    }

    public function restoreVipLimitsForUser(User $user): void
    {
        $this->transaction(function () use ($user) {
            PaymentDetail::query()
                ->where('user_id', $user->id)
                ->get()
                ->each(function (PaymentDetail $detail) {
                    $detail->updateQuietly([
                        'min_order_amount' => $detail->vip_min_order_amount_backup,
                        'max_order_amount' => $detail->vip_max_order_amount_backup,
                    ]);
                });
        });
    }

    public function resetVipLimitsForUser(User $user): void
    {
        $this->transaction(function () use ($user) {
            PaymentDetail::query()
                ->where('user_id', $user->id)
                ->update([
                    'min_order_amount' => null,
                    'max_order_amount' => null,
                ]);
        });
    }

    protected function transaction(callable $callback): mixed
    {
        return Transaction::run(function () use ($callback) {
            return $callback();
        });
    }
}


