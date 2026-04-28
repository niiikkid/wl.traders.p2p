<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CascadeProvider\StoreRequest;
use App\Http\Requests\Admin\CascadeProvider\UpdateRequest;
use App\Http\Resources\TableCascadeProviderResource;
use App\Models\CascadeProvider;
use App\Models\User;
use App\Services\Cascade\CascadeProviderDiscoveryService;
use Inertia\Inertia;

class CascadeProviderController extends Controller
{
    public function index(CascadeProviderDiscoveryService $discoveryService)
    {
        $providers = CascadeProvider::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(request()->integer('per_page', 10))
            ->withQueryString();

        $cascadeProviders = TableCascadeProviderResource::collection($providers);
        $implementedProviders = $discoveryService->implementedProviders()->values();
        $existingProviderCodes = CascadeProvider::query()->pluck('code')->all();
        $providerCallbackBaseUrl = rtrim(url('/api/v2/providers'), '/');
        $providerTypes = collect(ProviderType::cases())
            ->map(fn (ProviderType $type) => [
                'code' => $type->value,
                'name' => $type === ProviderType::INTERNAL ? 'Внутренний' : 'Внешний',
            ])
            ->values();
        $liquidityUsers = User::role('Provider Liquidity')
            ->orderBy('email')
            ->get(['id', 'email']);

        return Inertia::render('Admin/CascadeProviders/Index', compact(
            'cascadeProviders',
            'implementedProviders',
            'existingProviderCodes',
            'providerCallbackBaseUrl',
            'providerTypes',
            'liquidityUsers'
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

    private function ensureLiquidityProviderWallet(CascadeProvider $provider): void
    {
        $provider->loadMissing('user.wallet');

        if (! $provider->user || $provider->user->wallet) {
            return;
        }

        services()->wallet()->create($provider->user);
    }
}
