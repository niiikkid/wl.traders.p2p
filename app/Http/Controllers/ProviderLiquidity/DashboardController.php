<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProviderLiquidity;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeDealResource;
use App\Http\Resources\TableCascadeProviderLogResource;
use App\Models\CascadeDeal;
use App\Models\CascadeProviderLog;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\ProviderLiquidity\ProviderLiquidityDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProviderLiquidityDashboardService $providerLiquidityDashboardService,
    ) {}

    public function services(Request $request)
    {
        $providers = $this->providerLiquidityDashboardService->resolveProviders($request)
            ->sortByDesc('id')
            ->values();

        return Inertia::render('ProviderLiquidity/Services', [
            'services' => $providers->map(fn ($provider) => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'provider_type' => $provider->provider_type?->value,
                'is_active' => $provider->is_active,
                'base_url' => $provider->base_url,
                'has_access_token' => filled($provider->access_token),
                'currency_code' => $provider->currency_code,
                'timeout' => $provider->timeout,
                'verify_ssl' => $provider->verify_ssl,
                'created_at' => $provider->created_at?->toISOString(),
            ])->values()->all(),
        ]);
    }

    public function deals(Request $request)
    {
        $providers = $this->providerLiquidityDashboardService->resolveProviders($request);
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $deals = $providers->isNotEmpty()
            ? TableCascadeDealResource::collection(
                CascadeDeal::query()
                    ->whereIn('selected_provider_id', $providers->pluck('id')->all())
                    ->with(['merchant', 'merchantClient', 'selectedTransaction'])
                    ->when($filters->uuid, fn ($query) => $query->where('uuid', 'like', "%{$filters->uuid}%"))
                    ->when($filters->externalID, fn ($query) => $query->where('external_id', 'like', "%{$filters->externalID}%"))
                    ->when($filters->amount, function ($query) use ($filters) {
                        $query->where('amount', Money::fromPrecision($filters->amount, Currency::USDT()->getCode())->toUnits());
                    })
                    ->when($filters->startDate, fn ($query) => $query->whereDate('created_at', '>=', $filters->startDate))
                    ->when($filters->endDate, fn ($query) => $query->whereDate('created_at', '<=', $filters->endDate))
                    ->latest('id')
                    ->paginate($request->integer('per_page', 10))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Deals', compact('deals', 'filters', 'filtersVariants'));
    }

    /**
     * Логи API/callback: записи по всем интеграциям пользователя ({@see ProviderLiquidityDashboardService::resolveProviders}).
     * Параметры запроса не подменяют провайдера — чужие логи недоступны.
     */
    public function logs(Request $request)
    {
        $providers = $this->providerLiquidityDashboardService->resolveProviders($request);
        $filters = [
            'type' => $request->string('type')->toString(),
            'operation' => $request->string('operation')->toString(),
            'is_successful' => $request->string('is_successful')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $logsQuery = $providers->isNotEmpty()
            ? CascadeProviderLog::query()
                ->forCascadeProviders($providers)
                ->with(['cascadeDeal', 'cascadeTransaction', 'provider'])
                ->when($filters['type'] === 'api', fn (Builder $query) => $query->where('operation', '!=', 'callback'))
                ->when($filters['type'] === 'callback', fn (Builder $query) => $query->where('operation', 'callback'))
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
                ->latest('id')
            : null;

        $summary = $logsQuery
            ? [
                'total' => (clone $logsQuery)->count(),
                'api' => (clone $logsQuery)->where('operation', '!=', 'callback')->count(),
                'callback' => (clone $logsQuery)->where('operation', 'callback')->count(),
                'failed' => (clone $logsQuery)->where('is_successful', false)->count(),
            ]
            : [
                'total' => 0,
                'api' => 0,
                'callback' => 0,
                'failed' => 0,
            ];

        $logs = $logsQuery
            ? TableCascadeProviderLogResource::collection(
                $logsQuery
                    ->paginate($request->integer('per_page', 20))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Logs', [
            'logs' => $logs,
            'summary' => $summary,
            'filters' => $filters,
            'filterOptions' => [
                'operations' => $providers->isNotEmpty()
                    ? CascadeProviderLog::query()
                        ->forCascadeProviders($providers)
                        ->select('operation')
                        ->distinct()
                        ->orderBy('operation')
                        ->pluck('operation')
                        ->map(fn (string $operation) => [
                            'value' => $operation,
                            'label' => CascadeProviderLog::operationLabel($operation),
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
        ]);
    }
}
