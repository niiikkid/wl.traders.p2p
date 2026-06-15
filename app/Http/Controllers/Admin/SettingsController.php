<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdatePrimeTimeBonusRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $appSlogan = services()->settings()->getAppSlogan();
        $primeTimeBonus = services()->settings()->getPrimeTimeBonus()->toArray();
        $fundsOnHoldTime = services()->settings()->getFundsOnHoldTime();
        $maxPendingDisputes = services()->settings()->getMaxPendingDisputes();
        $maxRejectedDisputes = services()->settings()->getMaxRejectedDisputes();
        $defaultReserveBalanceLimit = services()->settings()->getDefaultReserveBalanceLimit();

        return Inertia::render('Settings/Index', compact(
            'appSlogan',
            'primeTimeBonus',
            'fundsOnHoldTime',
            'maxPendingDisputes',
            'maxRejectedDisputes',
            'defaultReserveBalanceLimit'
        ));
    }

    public function updateAppSlogan(Request $request)
    {
        $request->validate([
            'app_slogan' => ['required', 'string', 'max:120'],
        ]);

        services()->settings()->updateAppSlogan($request->app_slogan);

        return redirect()->route('admin.settings.index');
    }

    public function updatePrimeTimeBonus(UpdatePrimeTimeBonusRequest $request)
    {
        services()->settings()->updatePrimeTimeBonus(
            starts: $request->starts,
            ends: $request->ends,
            rate: $request->rate,
        );

        return redirect()->route('admin.settings.index');
    }

    public function updateFundsOnHold(Request $request)
    {
        $request->validate(['hold_time' => 'required', 'integer', 'min:0']);

        services()->settings()->updateFundsOnHoldTime($request->hold_time);

        return redirect()->route('admin.settings.index');
    }

    public function updateMaxPendingDisputes(Request $request)
    {
        $request->validate(['max_pending_disputes' => 'required', 'integer', 'min:0']);

        services()->settings()->updateMaxPendingDisputes($request->max_pending_disputes);

        return redirect()->route('admin.settings.index');
    }

    public function updateMaxRejectedDisputes(Request $request)
    {
        $request->validate([
            'count' => 'required|integer|min:0',
            'period' => 'required|integer|min:0',
        ]);

        services()->settings()->updateMaxRejectedDisputes(
            count: $request->count,
            period: $request->period
        );

        return redirect()->route('admin.settings.index');
    }

    public function updateDefaultReserveBalanceLimit(Request $request)
    {
        $request->validate([
            'default_reserve_balance_limit' => ['required', 'integer', 'min:0'],
        ]);

        services()->settings()->updateDefaultReserveBalanceLimit((int) $request->default_reserve_balance_limit);

        return redirect()->route('admin.settings.index');
    }
}
