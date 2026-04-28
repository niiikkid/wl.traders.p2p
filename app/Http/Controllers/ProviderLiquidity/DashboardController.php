<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProviderLiquidity;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TableCascadeDealResource;
use App\Http\Resources\TableCascadeProviderLogResource;
use App\Http\Resources\TransactionResource;
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

    public function wallet(Request $request)
    {
        $provider = $this->providerLiquidityDashboardService->resolveProvider($request);
        $wallet = $provider?->user?->wallet;
        $walletStats = services()->wallet()->getWalletStats($wallet)->toArray();

        $tabs = [
            'invoices' => [
                'key' => 'invoices',
                'name' => 'Инвойсы',
            ],
            'transactions' => [
                'key' => 'transactions',
                'name' => 'Транзакции',
            ],
        ];

        $filters = [
            'invoices' => [
                'invoiceTypes' => [
                    'all' => [
                        'key' => 'all',
                        'name' => 'Тип инвойса',
                    ],
                    InvoiceType::DEPOSIT->value => [
                        'key' => InvoiceType::DEPOSIT->value,
                        'name' => 'Пополнение',
                    ],
                    InvoiceType::WITHDRAWAL->value => [
                        'key' => InvoiceType::WITHDRAWAL->value,
                        'name' => 'Вывод',
                    ],
                ],
            ],
        ];

        $currentTab = request()->input('tab', 'invoices');
        if (empty($tabs[$currentTab])) {
            $currentTab = 'invoices';
        }

        $currentFilters = [
            'invoices' => [
                'invoiceTypes' => request()->input('currentFilters.invoices.invoiceTypes', 'all'),
            ],
        ];

        $invoices = null;
        $transactions = null;

        if ($currentTab === 'invoices') {
            $invoices = queries()->invoice()->paginate(
                wallet: $wallet,
                invoiceType: InvoiceType::tryFrom($currentFilters['invoices']['invoiceTypes']),
                balanceType: BalanceType::PROVIDER,
            );
            $invoices = InvoiceResource::collection($invoices);
        } elseif ($currentTab === 'transactions') {
            $transactions = queries()->transaction()->paginate(
                wallet: $wallet,
                balanceType: BalanceType::PROVIDER,
            );
            $transactions = TransactionResource::collection($transactions);
        }

        $walletSurfaces = [
            'trust' => false,
            'merchant' => false,
            'teamleader' => false,
            'provider' => true,
            'escrow' => false,
            'dispute' => false,
        ];

        return Inertia::render('Wallet/Index', compact(
            'walletStats',
            'invoices',
            'transactions',
            'tabs',
            'filters',
            'currentTab',
            'currentFilters',
            'walletSurfaces',
        ));
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
