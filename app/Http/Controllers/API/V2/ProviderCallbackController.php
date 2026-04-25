<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\ProviderCallback\StoreRequest;
use Illuminate\Http\JsonResponse;

class ProviderCallbackController extends Controller
{
    public function store(StoreRequest $request, string $provider_code): JsonResponse
    {
        return response()->success([
            'provider_code' => $provider_code,
            'received' => $request->all(),
        ]);
    }
}
