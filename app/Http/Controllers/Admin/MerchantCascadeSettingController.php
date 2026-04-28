<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MerchantCascadeSetting\UpdateRequest;
use App\Http\Resources\TableCascadeProviderResource;
use App\Http\Resources\TableMerchantCascadeSettingResource;
use App\Models\CascadeProvider;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MerchantCascadeSettingController extends Controller
{
    public function index()
    {
        $merchants = Merchant::query()
            ->with(['user', 'cascadeSetting'])
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $merchants = TableMerchantCascadeSettingResource::collection($merchants)->resolve();

        $cascadeProviders = TableCascadeProviderResource::collection(
            CascadeProvider::query()
                ->orderBy('priority', 'asc')
                ->orderBy('id', 'asc')
                ->get()
        )->resolve();

        return Inertia::render('Admin/CascadeMerchantSettings/Index', compact(
            'merchants',
            'cascadeProviders'
        ));
    }

    public function update(UpdateRequest $request, Merchant $merchant): RedirectResponse
    {
        $validated = $request->validated();
        $validated['allowed_provider_ids'] = collect($validated['allowed_provider_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $merchant->cascadeSetting()->updateOrCreate(
            ['merchant_id' => $merchant->id],
            $validated
        );

        return redirect()->route('admin.cascade-merchant-settings.index');
    }
}
