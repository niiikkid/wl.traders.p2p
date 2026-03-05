<?php

namespace App\Http\Controllers\API\Statement;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Statement\IndexRequest;
use App\Http\Resources\API\Statement\OrderStatementResource;
use App\Http\Resources\API\Statement\PayoutStatementResource;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StatementController extends Controller
{
    public function orders(IndexRequest $request): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);
        if ($merchant instanceof JsonResponse) {
            return $merchant;
        }

        $orders = Order::query()
            ->whereRelation('merchant', 'user_id', $request->user()->id)
            ->when($merchant, function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            })
            ->orderBy('created_at', $this->resolveSortDirection($request))
            ->paginate($this->resolvePerPage($request));

        return response()->success(
            OrderStatementResource::collection($orders)
        );
    }

    public function payouts(IndexRequest $request): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);
        if ($merchant instanceof JsonResponse) {
            return $merchant;
        }

        $payouts = Payout::query()
            ->whereRelation('merchant', 'user_id', $request->user()->id)
            ->when($merchant, function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            })
            ->orderBy('created_at', $this->resolveSortDirection($request))
            ->paginate($this->resolvePerPage($request));

        return response()->success(
            PayoutStatementResource::collection($payouts)
        );
    }

    private function resolveMerchant(IndexRequest $request): Merchant|JsonResponse|null
    {
        if (! $request->filled('merchant_id')) {
            return null;
        }

        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        if (! $merchant) {
            return response()->failWithMessage('Мерчант не найден.', 404);
        }

        Gate::authorize('api-access-to-merchant', $merchant);

        return $merchant;
    }

    private function resolvePerPage(IndexRequest $request): int
    {
        $perPage = $request->integer('per_page', 20);

        return max(1, min($perPage, 100));
    }

    private function resolveSortDirection(IndexRequest $request): string
    {
        return $request->input('sort', 'new') === 'old' ? 'asc' : 'desc';
    }
}
