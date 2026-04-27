<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CascadeProvider\StoreRequest;
use App\Http\Requests\Admin\CascadeProvider\UpdateRequest;
use App\Http\Resources\TableCascadeProviderResource;
use App\Models\CascadeProvider;
use App\Models\Merchant;
use App\Services\Cascade\CascadeProviderDiscoveryService;
use Inertia\Inertia;

class CascadeProviderController extends Controller
{
    public function index(CascadeProviderDiscoveryService $discoveryService)
    {
        $providers = CascadeProvider::query()
            ->with('targetMerchant')
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(request()->integer('per_page', 10))
            ->withQueryString();

        $cascadeProviders = TableCascadeProviderResource::collection($providers);
        $implementedProviders = $discoveryService->implementedProviders()->values();
        $providerCallbackBaseUrl = rtrim(url('/api/v2/providers'), '/');
        $providerTypes = collect(ProviderType::cases())
            ->map(fn (ProviderType $type) => [
                'code' => $type->value,
                'name' => $type === ProviderType::INTERNAL ? 'Внутренний' : 'Внешний',
            ])
            ->values();
        $merchants = Merchant::query()
            ->select(['id', 'uuid', 'name'])
            ->orderBy('id')
            ->get()
            ->map(fn (Merchant $merchant) => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'uuid' => $merchant->uuid,
            ])
            ->values();

        return Inertia::render('Admin/CascadeProviders/Index', compact(
            'cascadeProviders',
            'implementedProviders',
            'providerCallbackBaseUrl',
            'providerTypes',
            'merchants'
        ));
    }

    public function store(StoreRequest $request)
    {
        CascadeProvider::query()->create($request->validated());

        return redirect()->route('admin.cascade-providers.index');
    }

    public function update(UpdateRequest $request, CascadeProvider $cascadeProvider)
    {
        $cascadeProvider->update($request->validated());

        return redirect()->route('admin.cascade-providers.index');
    }
}
