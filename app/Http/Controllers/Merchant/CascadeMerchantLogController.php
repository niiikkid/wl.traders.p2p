<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeMerchantLogResource;
use App\Models\CascadeMerchantLog;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CascadeMerchantLogController extends Controller
{
    public function index(Request $request)
    {
        $merchantIds = Merchant::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id');
        $merchantIdList = $merchantIds->all();

        $requestedMerchantId = $request->integer('merchant_id') ?: null;
        $merchantId = $requestedMerchantId && $merchantIds->contains($requestedMerchantId)
            ? $requestedMerchantId
            : null;

        $filters = [
            'direction' => $request->string('direction')->toString(),
            'merchant_id' => $merchantId,
            'operation' => $request->string('operation')->toString(),
            'is_successful' => $request->string('is_successful')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $logs = $merchantIds->isNotEmpty()
            ? CascadeMerchantLog::query()
                ->with(['cascadeDeal', 'merchant'])
                ->whereIn('merchant_id', $merchantIdList)
                ->when($filters['direction'], fn (Builder $query, string $direction) => $query->where('direction', $direction))
                ->when($filters['merchant_id'], fn (Builder $query, int $merchantId) => $query->where('merchant_id', $merchantId))
                ->when($filters['operation'], fn (Builder $query, string $operation) => $query->where('operation', $operation))
                ->when($filters['is_successful'] !== '', fn (Builder $query) => $query->where('is_successful', $filters['is_successful'] === '1'))
                ->when($filters['search'], function (Builder $query, string $search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('url', 'like', "%{$search}%")
                            ->orWhere('error_message', 'like', "%{$search}%")
                            ->orWhereRelation('cascadeDeal', 'uuid', 'like', "%{$search}%")
                            ->orWhereRelation('cascadeDeal', 'external_id', 'like', "%{$search}%")
                            ->orWhereRelation('merchant', 'uuid', 'like', "%{$search}%")
                            ->orWhereRelation('merchant', 'name', 'like', "%{$search}%");
                    });
                })
                ->latest('id')
                ->paginate($request->integer('per_page', 20))
                ->withQueryString()
            : null;

        return Inertia::render('Admin/CascadeMerchantLogs/Index', [
            'logs' => $logs ? TableCascadeMerchantLogResource::collection($logs) : null,
            'filters' => $filters,
            'filterOptions' => [
                'merchants' => Merchant::query()
                    ->whereIn('id', $merchantIdList)
                    ->orderBy('name')
                    ->get(['id', 'uuid', 'name'])
                    ->map(fn (Merchant $merchant) => [
                        'id' => $merchant->id,
                        'label' => "{$merchant->name} ({$merchant->uuid})",
                    ]),
                'operations' => $merchantIds->isNotEmpty()
                    ? (new CascadeMerchantLog)->newQuery()
                        ->where(function (Builder $query) use ($merchantIdList): void {
                            foreach ($merchantIdList as $merchantId) {
                                $query->orWhere('merchant_id', $merchantId);
                            }
                        })
                        ->select('operation')
                        ->distinct()
                        ->orderBy('operation')
                        ->pluck('operation')
                        ->map(fn (string $operation) => [
                            'value' => $operation,
                            'label' => CascadeMerchantLog::operationLabel($operation),
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
            'routeName' => 'merchant.cascade-merchant-logs.index',
            'showAdminNav' => false,
        ]);
    }
}
