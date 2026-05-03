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
use App\Jobs\RecordCascadeMerchantLogJob;
use App\Models\CascadeMerchantLog;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Models\Payout\Payout;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayoutController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $merchant = $this->authenticatedMerchant($request);

        $payouts = Payout::query()
            ->with(['merchant', 'receipts'])
            ->where('merchant_id', $merchant->id)
            ->orderBy('id', $this->resolveIndexSortDirection($request))
            ->paginate($this->resolveIndexPerPage($request));

        return response()->success(
            PayoutResource::collection($payouts)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $started_at = microtime(true);
        $merchant = $request->authenticatedMerchant();
        abort_unless($merchant instanceof Merchant, 404);

        Gate::authorize('api-v2-access-to-merchant', $merchant);

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
            merchantRate: $request->filled('exchange_rate')
                ? Money::fromPrecision((string) $request->validated('exchange_rate'), $currencyCode)
                : null,
            apiVersion: 2,
        );

        try {
            $payout = services()->payout()->create($dto);
        } catch (PayoutException $exception) {
            $responsePayload = ['message' => $exception->getMessage()];

            $this->recordMerchantLog(
                merchant: $merchant,
                payout: null,
                operation: 'createPayout',
                requestPayload: $request->all(),
                responsePayload: $responsePayload,
                statusCode: 400,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($exception),
                errorMessage: $exception->getMessage(),
            );

            return response()->failWithMessage($exception->getMessage());
        }

        $responsePayload = PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))->resolve();

        $this->recordMerchantLog(
            merchant: $merchant,
            payout: $payout,
            operation: 'createPayout',
            requestPayload: $request->all(),
            responsePayload: $responsePayload,
            statusCode: 200,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return response()->success(
            PayoutResource::make($payout)
        );
    }

    public function show(Payout $payout): JsonResponse
    {
        Gate::authorize('api-v2-access-to-merchant', $payout->merchant);

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))
        );
    }

    public function cancel(Payout $payout): JsonResponse
    {
        $started_at = microtime(true);
        Gate::authorize('api-v2-access-to-merchant', $payout->merchant);

        try {
            $payout = services()->payout()->cancel($payout);
        } catch (PayoutException $exception) {
            $responsePayload = ['message' => $exception->getMessage()];

            $this->recordMerchantLog(
                merchant: $payout->merchant,
                payout: $payout,
                operation: 'cancelPayout',
                requestPayload: request()->all(),
                responsePayload: $responsePayload,
                statusCode: 400,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($exception),
                errorMessage: $exception->getMessage(),
            );

            return response()->failWithMessage($exception->getMessage());
        }

        $responsePayload = PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway', 'receipts'))->resolve();

        $this->recordMerchantLog(
            merchant: $payout->merchant,
            payout: $payout,
            operation: 'cancelPayout',
            requestPayload: request()->all(),
            responsePayload: $responsePayload,
            statusCode: 200,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return response()->successWithMessage(
            'Выплата отменена.',
            PayoutResource::make($payout)
        );
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    private function recordMerchantLog(
        Merchant $merchant,
        ?Payout $payout,
        string $operation,
        array $requestPayload,
        array $responsePayload,
        int $statusCode,
        float $startedAt,
        bool $isSuccessful,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        RecordCascadeMerchantLogJob::dispatch([
            'payout_id' => $payout?->id,
            'merchant_id' => $merchant->id,
            'payment_type' => CascadeMerchantLog::PAYMENT_TYPE_PAYOUT,
            'operation' => $operation,
            'direction' => 'incoming',
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'status_code' => $statusCode,
            'execution_time' => round(microtime(true) - $startedAt, 4),
            'is_successful' => $isSuccessful,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    private function authenticatedMerchant(Request $request): Merchant
    {
        $merchant = queries()->merchant()->findByID(
            (string) $request->attributes->get('merchant_api_credential')->merchant_id
        );

        abort_unless($merchant instanceof Merchant, 404);

        Gate::authorize('api-v2-access-to-merchant', $merchant);

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
