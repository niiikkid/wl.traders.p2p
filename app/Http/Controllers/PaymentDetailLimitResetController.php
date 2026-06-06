<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetail\ResetLimitsRequest;
use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailLimitResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PaymentDetailLimitResetController extends Controller
{
    public function store(
        ResetLimitsRequest $request,
        PaymentDetail $paymentDetail,
        PaymentDetailLimitResetService $paymentDetailLimitResetService,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        if ($request->validated('type') === 'daily') {
            $paymentDetailLimitResetService->resetDailyLimitsForPaymentDetail($paymentDetail);
        } else {
            $paymentDetailLimitResetService->resetMonthlyLimitsForPaymentDetail($paymentDetail);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
