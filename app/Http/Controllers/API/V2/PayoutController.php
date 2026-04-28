<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V2\Payout\IndexRequest;
use App\Http\Resources\API\V2\PayoutStatementResource;
use App\Models\Merchant;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PayoutController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $merchant = $this->resolveIndexMerchant($request);
        if ($merchant instanceof JsonResponse) {
            return $merchant;
        }

        $payouts = Payout::query()
            ->whereRelation('merchant', 'user_id', $request->user()->id)
            ->when($merchant, function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            })
            ->orderBy('id', $this->resolveIndexSortDirection($request))
            ->paginate($this->resolveIndexPerPage($request));

        return response()->success(
            PayoutStatementResource::collection($payouts)
        );
    }

    private function resolveIndexMerchant(IndexRequest $request): Merchant|JsonResponse|null
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
