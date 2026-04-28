<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProviderLiquidity;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeDealResource;
use App\Http\Resources\TableCascadeProviderLogResource;
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
        $provider = $this->providerLiquidityDashboardService->resolveProvider($request);

        return Inertia::render('ProviderLiquidity/Services', [
            'services' => $provider ? [[
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'provider_type' => $provider->provider_type?->value,
                'is_active' => $provider->is_active,
                'base_url' => $provider->base_url,
                'access_token' => $provider->access_token,
                'currency_code' => $provider->currency_code,
                'timeout' => $provider->timeout,
                'verify_ssl' => $provider->verify_ssl,
                'created_at' => $provider->created_at?->toISOString(),
            ]] : [],
        ]);
    }

    public function deals(Request $request)
    {
        $provider = $this->providerLiquidityDashboardService->resolveProvider($request);
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $deals = $provider
            ? TableCascadeDealResource::collection(
                $provider->deals()
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

    public function logs(Request $request)
    {
        $provider = $this->providerLiquidityDashboardService->resolveProvider($request);
        $filters = [
            'type' => $request->string('type')->toString(),
            'operation' => $request->string('operation')->toString(),
            'is_successful' => $request->string('is_successful')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $logs = $provider
            ? TableCascadeProviderLogResource::collection(
                CascadeProviderLog::query()
                    ->where('provider_id', $provider->id)
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
                    ->paginate($request->integer('per_page', 20))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Logs', [
            'logs' => $logs,
            'filters' => $filters,
            'filterOptions' => [
                'operations' => $provider
                    ? CascadeProviderLog::query()
                        ->where('provider_id', $provider->id)
                        ->select('operation')
                        ->distinct()
                        ->orderBy('operation')
                        ->pluck('operation')
                        ->values()
                    : [],
            ],
        ]);
    }
}
