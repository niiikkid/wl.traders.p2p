<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CascadeProvider\ReorderCascadeProvidersRequest;
use App\Http\Requests\Admin\CascadeProvider\StoreRequest;
use App\Http\Requests\Admin\CascadeProvider\UpdateRequest;
use App\Http\Resources\TableCascadeProviderResource;
use App\Models\CascadeProvider;
use App\Models\User;
use App\Services\Cascade\CascadeProviderDiscoveryService;
use App\Services\Money\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CascadeProviderController extends Controller
{
    public function index(CascadeProviderDiscoveryService $discoveryService)
    {
        $providers = CascadeProvider::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $cascadeProviders = TableCascadeProviderResource::collection($providers)->resolve();
        $implementedProviders = $discoveryService->implementedProviders()->values();
        $providerCallbackBaseUrl = rtrim(url('/api/v2/providers'), '/');
        $liquidityUsers = User::role('Provider Liquidity')
            ->orderBy('email')
            ->get(['id', 'email']);
        $currencies = Currency::getAll()
            ->map(fn (Currency $currency): array => [
                'code' => strtoupper($currency->getCode()),
                'name' => $currency->getName(),
            ])
            ->values();

        return Inertia::render('Admin/CascadeProviders/Index', compact(
            'cascadeProviders',
            'implementedProviders',
            'providerCallbackBaseUrl',
            'liquidityUsers',
            'currencies'
        ));
    }

    public function store(StoreRequest $request)
    {
        $provider = CascadeProvider::query()->create($request->validated());
        $this->ensureLiquidityProviderWallet($provider);

        return redirect()->route('admin.cascade-providers.index');
    }

    public function update(UpdateRequest $request, CascadeProvider $cascadeProvider)
    {
        $cascadeProvider->update($request->validated());
        $this->ensureLiquidityProviderWallet($cascadeProvider);

        return redirect()->route('admin.cascade-providers.index');
    }

    public function reorder(ReorderCascadeProvidersRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');

        DB::transaction(static function () use ($ids): void {
            foreach ($ids as $index => $id) {
                CascadeProvider::query()->whereKey($id)->update(['priority' => $index]);
            }
        });

        return redirect()->route('admin.cascade-providers.index');
    }

    private function ensureLiquidityProviderWallet(CascadeProvider $provider): void
    {
        $provider->loadMissing('user.wallet');

        if (! $provider->user || $provider->user->wallet) {
            return;
        }

        services()->wallet()->create($provider->user);
    }
}
