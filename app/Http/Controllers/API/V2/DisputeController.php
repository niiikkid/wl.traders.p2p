<?php

namespace App\Http\Controllers\API\V2;

use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Dispute\StoreRequest;
use App\Jobs\RecordCascadeMerchantLogJob;
use App\Models\CascadeDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    public function show(CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $dispute = services()->cascade()->getDispute($cascadeDeal);
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success($dispute);
    }

    public function store(StoreRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        $started_at = microtime(true);
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $dispute = services()->cascade()->openDispute($cascadeDeal, $request->validated());
        } catch (CascadeException $e) {
            $response_payload = [
                'success' => false,
                'message' => $e->getMessage(),
            ];

            $this->recordMerchantLog($cascadeDeal, 'openDispute', $request->all(), $response_payload, 400, $started_at, false, get_class($e), $e->getMessage());

            return response()->json($response_payload, 400);
        }

        $response_payload = [
            'success' => true,
            'data' => $dispute,
        ];

        $this->recordMerchantLog($cascadeDeal, 'openDispute', $request->all(), $response_payload, 200, $started_at, true);

        return response()->json($response_payload);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    private function recordMerchantLog(
        CascadeDeal $cascadeDeal,
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
            'cascade_deal_id' => $cascadeDeal->id,
            'merchant_id' => $cascadeDeal->merchant_id,
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
}
