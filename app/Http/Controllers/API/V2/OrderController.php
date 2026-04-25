<?php

namespace App\Http\Controllers\API\V2;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Order\StoreRequest;
use App\Http\Resources\API\V2\OrderResource;
use App\Models\CascadeDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function show(CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        return response()->success(
            OrderResource::make($cascadeDeal)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        Gate::authorize('api-access-to-merchant', $merchant);

        $dto = CreateCascadeDealDTO::makeFromRequest([
            ...$request->validated(),
            'merchant_id' => $merchant->id,
        ]);

        try {
            $cascade_deal = services()->cascade()->createDeal($dto);
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success(
            OrderResource::make($cascade_deal)
        );
    }
}
