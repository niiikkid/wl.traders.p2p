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
                'merchant_id' => $provider->merchant_id,
                'currency_code' => $provider->currency_code,
                'timeout' => $provider->timeout,
                'verify_ssl' => $provider->verify_ssl,
                'description' => $provider->description,
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
                    ->with(['merchant', 'merchantClient', 'selectedTransaction', 'collateralHolds'])
                    ->when($filters->uuid, fn ($query) => $query->where('uuid', 'like', "%{$filters->uuid}%"))
                    ->when($filters->externalID, fn ($query) => $query->where('external_id', 'like', "%{$filters->externalID}%"))
                    ->when($filters->clientId, fn ($query) => $query->whereRelation('merchantClient', 'external_id', 'like', "%{$filters->clientId}%"))
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

        $logs = $provider
            ? TableCascadeProviderLogResource::collection(
                CascadeProviderLog::query()
                    ->where('provider_id', $provider->id)
                    ->with(['cascadeDeal', 'cascadeTransaction', 'provider'])
                    ->latest('id')
                    ->paginate($request->integer('per_page', 20))
                    ->withQueryString()
            )
            : null;

        return Inertia::render('ProviderLiquidity/Logs', compact('logs'));
    }
}
