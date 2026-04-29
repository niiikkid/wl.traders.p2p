<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutMethodType;
use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Payout\IndexRequest;
use App\Http\Requests\API\V2\Payout\StoreRequest;
use App\Http\Resources\API\V2\PayoutResource;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Models\Payout\Payout;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PayoutController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $merchant = $this->resolveIndexMerchant($request);
        if ($merchant instanceof JsonResponse) {
            return $merchant;
        }

        $payouts = Payout::query()
            ->with(['merchant', 'receipts'])
            ->whereRelation('merchant', 'user_id', $request->user()->id)
            ->when($merchant, function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            })
            ->orderBy('id', $this->resolveIndexSortDirection($request))
            ->paginate($this->resolveIndexPerPage($request));

        return response()->success(
            PayoutResource::collection($payouts)
        );
    }

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
            methodType: PayoutMethodType::from($request->validated('payout_method')),
            requisites: $request->validated('payout_details'),
            initials: $request->validated('recipient_name'),
            currencyCode: $currencyCode,
            callbackUrl: $request->callback_url,
            bankName: $request->validated('bank_name'),
            merchantRate: $request->filled('fiat_to_usdt_rate')
                ? Money::fromPrecision((string) $request->validated('fiat_to_usdt_rate'), $currencyCode)
                : null,
        );

        try {
            $payout = services()->payout()->create($dto);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))
        );
    }

    public function show(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))
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
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))
        );
    }

    private function resolveIndexMerchant(IndexRequest $request): Merchant|JsonResponse|null
    {
        if (! $request->filled('merchant_id')) {
            return null;
        }

        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        if (! $merchant) {
            return response()->failWithMessage('Мерчант не найден.', 404);
        }

        Gate::authorize('api-access-to-merchant', $merchant);

        return $merchant;
    }

    private function resolveIndexPerPage(IndexRequest $request): int
    {
        $perPage = $request->integer('per_page', 20);

        return max(1, min($perPage, 100));
    }

    private function resolveIndexSortDirection(IndexRequest $request): string
    {
        return $request->input('sort', 'new') === 'old' ? 'asc' : 'desc';
    }
}
