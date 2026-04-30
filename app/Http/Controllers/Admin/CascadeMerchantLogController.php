<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
        $filters = [
            'direction' => $request->string('direction')->toString(),
            'merchant_id' => $request->integer('merchant_id') ?: null,
            'operation' => $request->string('operation')->toString(),
            'is_successful' => $request->string('is_successful')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $logsQuery = CascadeMerchantLog::query()
            ->with(['cascadeDeal', 'merchant'])
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
            ->latest();

        $summary = [
            'total' => (clone $logsQuery)->count(),
            'incoming' => (clone $logsQuery)->where('direction', 'incoming')->count(),
            'outgoing' => (clone $logsQuery)->where('direction', 'outgoing')->count(),
            'failed' => (clone $logsQuery)->where('is_successful', false)->count(),
        ];

        $logs = $logsQuery
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return Inertia::render('Admin/CascadeMerchantLogs/Index', [
            'logs' => TableCascadeMerchantLogResource::collection($logs),
            'summary' => $summary,
            'filters' => $filters,
            'filterOptions' => [
                'merchants' => Merchant::query()
                    ->orderBy('name')
                    ->get(['id', 'uuid', 'name'])
                    ->map(fn (Merchant $merchant) => [
                        'id' => $merchant->id,
                        'label' => "{$merchant->name} ({$merchant->uuid})",
                    ]),
                'operations' => CascadeMerchantLog::query()
                    ->select('operation')
                    ->distinct()
                    ->orderBy('operation')
                    ->pluck('operation')
                    ->map(fn (string $operation) => [
                        'value' => $operation,
                        'label' => CascadeMerchantLog::operationLabel($operation),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
