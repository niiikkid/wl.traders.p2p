<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AntiFraudSetting\StoreRequest;
use App\Http\Requests\Admin\AntiFraudSetting\UpdateRequest;
use App\Models\AntiFraudSetting;
use App\Models\Merchant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AntiFraudSettingController extends Controller
{
    public function index()
    {
        $merchants = Merchant::query()
            ->select(['id', 'name', 'uuid'])
            ->orderBy('name')
            ->get();

        $settings = AntiFraudSetting::query()
            ->with('merchant:id,name,uuid')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Admin/AntiFraud/Index', compact('merchants', 'settings'));
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        services()->antiFraudSetting()->create($request->validated());

        return back();
    }

    public function update(UpdateRequest $request, AntiFraudSetting $anti_fraud_setting): RedirectResponse
    {
        services()->antiFraudSetting()->update($anti_fraud_setting, $request->validated());

        return back();
    }

    public function destroy(AntiFraudSetting $anti_fraud_setting): RedirectResponse
    {
        services()->antiFraudSetting()->delete($anti_fraud_setting);

        return back();
    }
}
