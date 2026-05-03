<?php

namespace App\Http\Controllers\API\V2;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Exceptions\CascadeException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\H2H\Order\StoreConfirmationCodeRequest;
use App\Http\Requests\API\V2\Order\IndexRequest;
use App\Http\Requests\API\V2\Order\StoreRequest;
use App\Http\Resources\API\V2\OrderResource;
use App\Jobs\RecordCascadeMerchantLogJob;
use App\Models\CascadeDeal;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $merchant = $this->authenticatedMerchant($request);

        $orders = CascadeDeal::query()
            ->with('merchant')
            ->where('merchant_id', $merchant->id)
            ->visibleInMerchantApi()
            ->orderBy('id', $this->resolveIndexSortDirection($request))
            ->paginate($this->resolveIndexPerPage($request));

        return response()->success(
            OrderResource::collection($orders)
        );
    }

    public function show(CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-v2-access-to-merchant', $cascadeDeal->merchant);
        abort_unless($cascadeDeal->isVisibleInMerchantApi(), 404);

        return response()->success(
            OrderResource::make($cascadeDeal)
        );
    }

    public function showByExternal(Request $request, string $external_id): JsonResponse
    {
        $merchant = $this->authenticatedMerchant($request);

        $cascade_deal = services()->cascade()->findDealByExternalId($merchant->uuid, $external_id);

        return response()->success(
            OrderResource::make($cascade_deal)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $started_at = microtime(true);
        $merchant = $request->authenticatedMerchant();
        abort_unless($merchant instanceof Merchant, 404);

        Gate::authorize('api-v2-access-to-merchant', $merchant);

        $dto = CreateCascadeDealDTO::makeFromRequest(
            $request->toCreateCascadeDealData($merchant->id)
        );

        try {
            $cascade_deal = services()->cascade()->createDeal($dto);
        } catch (CascadeException $e) {
            $response_payload = ['message' => $e->getMessage()];
            $status_code = str_contains($e->getMessage(), 'вовремя') ? 504 : 400;

            $this->recordMerchantLog(
                merchant: $merchant,
                cascadeDeal: null,
                operation: 'createDeal',
                requestPayload: $request->all(),
                responsePayload: $response_payload,
                statusCode: $status_code,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            return response()->failWithMessage($e->getMessage(), $status_code);
        }

        $response_payload = OrderResource::make($cascade_deal)->resolve();

        $this->recordMerchantLog(
            merchant: $merchant,
            cascadeDeal: $cascade_deal,
            operation: 'createDeal',
            requestPayload: $request->all(),
            responsePayload: $response_payload,
            statusCode: 200,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return response()->success(
            OrderResource::make($cascade_deal)
        );
    }

    public function cancel(CascadeDeal $cascadeDeal): JsonResponse
    {
        $started_at = microtime(true);
        Gate::authorize('api-v2-access-to-merchant', $cascadeDeal->merchant);
        abort_unless($cascadeDeal->isVisibleInMerchantApi(), 404);

        try {
            $cascade_deal = services()->cascade()->cancelDeal($cascadeDeal);
        } catch (CascadeException|OrderException $e) {
            $response_payload = ['message' => $e->getMessage()];

            $this->recordMerchantLog(
                merchant: $cascadeDeal->merchant,
                cascadeDeal: $cascadeDeal,
                operation: 'cancelDeal',
                requestPayload: request()->all(),
                responsePayload: $response_payload,
                statusCode: 400,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            return response()->failWithMessage($e->getMessage());
        }

        $response_payload = OrderResource::make($cascade_deal)->resolve();

        $this->recordMerchantLog(
            merchant: $cascade_deal->merchant,
            cascadeDeal: $cascade_deal,
            operation: 'cancelDeal',
            requestPayload: request()->all(),
            responsePayload: $response_payload,
            statusCode: 200,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return response()->success(
            OrderResource::make($cascade_deal)
        );
    }

    public function storeConfirmationCode(StoreConfirmationCodeRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        $started_at = microtime(true);
        Gate::authorize('api-v2-access-to-merchant', $cascadeDeal->merchant);
        abort_unless($cascadeDeal->isVisibleInMerchantApi(), 404);

        try {
            $confirmation_code = services()->cascade()->storeConfirmationCode(
                $cascadeDeal,
                (string) $request->input('confirmation_code'),
            );
        } catch (CascadeException|OrderException $e) {
            $response_payload = ['message' => $e->getMessage()];

            $this->recordMerchantLog(
                merchant: $cascadeDeal->merchant,
                cascadeDeal: $cascadeDeal,
                operation: 'storeConfirmationCode',
                requestPayload: $request->all(),
                responsePayload: $response_payload,
                statusCode: 400,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            return response()->failWithMessage($e->getMessage());
        }

        $response_payload = $confirmation_code;

        $this->recordMerchantLog(
            merchant: $cascadeDeal->merchant,
            cascadeDeal: $cascadeDeal,
            operation: 'storeConfirmationCode',
            requestPayload: $request->all(),
            responsePayload: $response_payload,
            statusCode: 200,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return response()->success($confirmation_code);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    private function recordMerchantLog(
        Merchant $merchant,
        ?CascadeDeal $cascadeDeal,
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
            'cascade_deal_id' => $cascadeDeal?->id,
            'merchant_id' => $merchant->id,
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
