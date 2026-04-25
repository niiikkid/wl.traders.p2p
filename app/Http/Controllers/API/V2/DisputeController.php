<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Dispute\StoreRequest;
use App\Models\CascadeDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    public function show(CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        $cache_key = "cascade-dispute-{$cascadeDeal->uuid}";
        $dispute = Cache::get($cache_key);

        if (! $dispute) {
            return response()->failWithMessage('По сделке пока что небыло споров.');
        }

        return response()->success($dispute);
    }

    public function store(StoreRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        $dispute = [
            'order_id' => $cascadeDeal->uuid,
            'status' => 'pending',
            'cancel_reason' => null,
            'receipts' => $request->receipts,
        ];

        Cache::put("cascade-dispute-{$cascadeDeal->uuid}", $dispute, now()->addDay());

        return response()->success($dispute);
    }

    public function cancel(\App\Http\Requests\API\V2\Dispute\CancelRequest $request, CascadeDeal $cascadeDeal): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $cascadeDeal->merchant);

        $cache_key = "cascade-dispute-{$cascadeDeal->uuid}";
        $dispute = Cache::get($cache_key);

        if (! $dispute) {
            return response()->failWithMessage('По сделке пока что небыло споров.');
        }

        $dispute['status'] = 'cancelled';
        $dispute['cancel_reason'] = $request->cancel_reason;

        Cache::put($cache_key, $dispute, now()->addDay());

        return response()->success($dispute);
    }
}
