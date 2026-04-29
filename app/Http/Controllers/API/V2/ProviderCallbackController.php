<?php

namespace App\Http\Controllers\API\V2;

use App\Exceptions\CascadeException;
use App\Http\Controllers\Controller;
use App\Models\CascadeProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderCallbackController extends Controller
{
    public function store(Request $request, CascadeProvider $cascadeProvider): JsonResponse
    {
        try {
            $result = services()->cascade()->handleProviderCallback($request, $cascadeProvider);
        } catch (CascadeException $e) {
            return response()->failWithMessage($e->getMessage(), 422);
        }

        return response()->success($result);
    }
}
