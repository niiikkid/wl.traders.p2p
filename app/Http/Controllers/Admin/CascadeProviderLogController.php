<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeProviderLogResource;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CascadeProviderLogController extends Controller
{
    public function index(Request $request)
    {
        $this->abortIfCascadeHidden();

        $filters = [
            'type' => $request->string('type')->toString(),
            'provider_id' => $request->integer('provider_id') ?: null,
            'operation' => $request->string('operation')->toString(),
            'is_successful' => $request->string('is_successful')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $logsQuery = CascadeProviderLog::query()
            ->with(['cascadeDeal.merchant', 'cascadeTransaction', 'provider'])
            ->when($filters['type'] === 'api', fn (Builder $query) => $query->where('operation', '!=', 'callback'))
            ->when($filters['type'] === 'callback', fn (Builder $query) => $query->where('operation', 'callback'))
            ->when($filters['provider_id'], fn (Builder $query, int $providerId) => $query->where('provider_id', $providerId))
            ->when($filters['operation'], fn (Builder $query, string $operation) => $query->where('operation', $operation))
            ->when($filters['is_successful'] !== '', fn (Builder $query) => $query->where('is_successful', $filters['is_successful'] === '1'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('url', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%")
                        ->orWhereRelation('cascadeDeal', 'uuid', 'like', "%{$search}%")
                        ->orWhereRelation('cascadeDeal', 'external_id', 'like', "%{$search}%")
                        ->orWhereRelation('cascadeTransaction', 'provider_deal_id', 'like', "%{$search}%");
                });
            })
            ->latest();

        $summary = [
            'total' => (clone $logsQuery)->count(),
            'api' => (clone $logsQuery)->where('operation', '!=', 'callback')->count(),
            'callback' => (clone $logsQuery)->where('operation', 'callback')->count(),
            'failed' => (clone $logsQuery)->where('is_successful', false)->count(),
        ];

        $logs = $logsQuery
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return Inertia::render('Admin/CascadeProviderLogs/Index', [
            'logs' => TableCascadeProviderLogResource::collection($logs),
            'summary' => $summary,
            'filters' => $filters,
            'filterOptions' => [
                'providers' => CascadeProvider::query()
                    ->orderBy('name')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (CascadeProvider $provider) => [
                        'id' => $provider->id,
                        'label' => "{$provider->name} ({$provider->code})",
                    ]),
                'operations' => CascadeProviderLog::query()
                    ->select('operation')
                    ->distinct()
                    ->orderBy('operation')
                    ->pluck('operation')
                    ->map(fn (string $operation) => [
                        'value' => $operation,
                        'label' => CascadeProviderLog::operationLabel($operation),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function abortIfCascadeHidden(): void
    {
        abort(404);
    }
}
