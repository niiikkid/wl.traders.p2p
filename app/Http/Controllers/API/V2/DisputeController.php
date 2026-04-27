<?php

namespace App\Http\Controllers\API\V2;

use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Dispute\CancelRequest;
use App\Http\Requests\API\V2\Dispute\StoreRequest;
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
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $dispute = services()->cascade()->openDispute($cascadeDeal, $request->validated());
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success($dispute);
    }

    public function cancel(CancelRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $dispute = services()->cascade()->cancelDispute(
                $cascadeDeal,
                $request->string('cancel_reason')->toString(),
            );
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success($dispute);
    }
}
