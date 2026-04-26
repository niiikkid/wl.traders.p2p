<?php

namespace App\Http\Controllers\API\V2;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Exceptions\CascadeException;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\H2H\Order\StoreConfirmationCodeRequest;
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

    public function showByExternal(string $merchant_id, string $external_id): JsonResponse
    {
        $cascade_deal = services()->cascade()->findDealByExternalId($merchant_id, $external_id);

        Gate::authorize('api-access-to-merchant', $cascade_deal->merchant);

        return response()->success(
            OrderResource::make($cascade_deal)
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

    public function cancel(CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $cascade_deal = services()->cascade()->cancelDeal($cascadeDeal);
        } catch (CascadeException|OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success(
            OrderResource::make($cascade_deal)
        );
    }

    public function storeConfirmationCode(StoreConfirmationCodeRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        try {
            $confirmation_code = services()->cascade()->storeConfirmationCode(
                $cascadeDeal,
                (string) $request->input('confirmation_code'),
            );
        } catch (CascadeException|OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return response()->success($confirmation_code);
    }
}
