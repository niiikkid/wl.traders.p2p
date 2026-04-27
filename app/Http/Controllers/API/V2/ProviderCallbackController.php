<?php

namespace App\Http\Controllers\API\V2;

use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\ProviderCallback\StoreRequest;
use Illuminate\Http\JsonResponse;

class ProviderCallbackController extends Controller
{
    public function store(StoreRequest $request, string $provider_code): JsonResponse
    {
        try {
            $result = services()->cascade()->handleProviderCallback(
                providerCode: $provider_code,
                payload: $request->except('provider_code'),
                accessToken: $request->header('Access-Token'),
            );
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage(), 422);
        }

        return response()->success($result);
    }
}
