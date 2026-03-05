<?php

namespace App\Http\Controllers;

use App\Enums\DetailType;
use App\Enums\OrderStatus;
use App\Models\PaymentDetail;
use App\Utils\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentDetailArchiveController extends Controller
{
    public function store(PaymentDetail $paymentDetail)
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        if ($paymentDetail->orders()->where('status', OrderStatus::PENDING)->exists()) {
            return redirect()->back()->with('error', 'Реквизит не должен иметь открытые сделки.');
        }

        Transaction::run(function () use ($paymentDetail) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetail->id)->lockForUpdate()->first();

            $paymentDetail->update([
                'archived_at' => now(),
                'is_active' => false,
            ]);
        });

        return redirect()->back();
    }

    public function destroy(PaymentDetail $paymentDetail)
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        if (! $paymentDetail->archived_at) {
            return redirect()->back()->with('error', 'Реквизит уже не находится в архиве.');
        }

        $hasActiveDetail = PaymentDetail::query()
            ->where('detail', $paymentDetail->detail)
            ->when(
                in_array($paymentDetail->detail_type->value, [DetailType::PHONE->value, DetailType::MOBILE_COMMERCE->value], true),
                function ($query) use ($paymentDetail) {
                    $paymentGatewayId = $paymentDetail->paymentGateways()->first()?->id;
                    if ($paymentGatewayId) {
                        $query->whereExists(function ($subQuery) use ($paymentGatewayId) {
                            $subQuery->from('payment_detail_payment_gateway')
                                ->whereColumn('payment_detail_payment_gateway.payment_detail_id', 'payment_details.id')
                                ->where('payment_detail_payment_gateway.payment_gateway_id', $paymentGatewayId);
                        });
                    }
                }
            )
            ->whereNull('archived_at')
            ->exists();

        if ($hasActiveDetail) {
            return redirect()->back()->with('error', 'Уже есть такой активный реквизит.');
        }

        Transaction::run(function () use ($paymentDetail) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetail->id)->lockForUpdate()->first();

            $paymentDetail->update([
                'archived_at' => null,
                'is_active' => false,
            ]);
        });

        return redirect()->back();
    }
}
