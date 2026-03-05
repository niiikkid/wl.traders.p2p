<?php

namespace App\Http\Controllers\API\Payout;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutMethodType;
use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Payout\StoreRequest;
use App\Http\Resources\API\Payout\PayoutResource;
use App\Models\Payout\Payout;
use App\Models\PaymentGateway;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PayoutController extends Controller
{
    public function store(StoreRequest $request): JsonResponse
    {
        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        Gate::authorize('api-access-to-merchant', $merchant);

        $paymentGateway = null;
        $gatewayCode = $request->validated('payment_gateway');
        if ($gatewayCode) {
            $paymentGateway = PaymentGateway::query()
                ->where('code', $gatewayCode)
                ->where('is_payouts_enabled', true)
                ->active()
                ->firstOrFail();
        }

        $currencyCode = $paymentGateway
            ? strtoupper($paymentGateway->currency->getCode())
            : strtoupper($request->validated('currency'));

        $dto = PayoutCreateDTO::make(
            merchant: $merchant,
            paymentGateway: $paymentGateway,
            externalId: $request->external_id,
            amountFiat: Money::fromPrecision($request->amount, $currencyCode),
            methodType: PayoutMethodType::from($request->payout_method_type),
            requisites: $request->requisites,
            initials: $request->initials,
            currencyCode: $currencyCode,
            callbackUrl: $request->callback_url,
            bankName: $request->validated('bank_name'),
        );

        try {
            $payout = services()->payout()->create($dto);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    public function show(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    public function cancel(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        try {
            $payout = services()->payout()->cancel($payout);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->successWithMessage(
            'Выплата отменена.',
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    public function confirmPaid(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        try {
            $payout = services()->payout()->confirmPaid($payout);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->successWithMessage(
            'Выплата подтверждена и холд снят.',
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }
}

