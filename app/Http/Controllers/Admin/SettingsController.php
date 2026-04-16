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
        $supportLink = services()->settings()->getSupportLink();
        $landingTelegramLink = services()->settings()->getLandingTelegramLink();
        $fundsOnHoldTime = services()->settings()->getFundsOnHoldTime();
        $maxPendingDisputes = services()->settings()->getMaxPendingDisputes();
        $maxRejectedDisputes = services()->settings()->getMaxRejectedDisputes();
        $tempVipRequiredDeals = services()->settings()->getTempVipRequiredDeals();
        $tempVipDurationMinutes = services()->settings()->getTempVipDurationMinutes();
        $tempVipEnabled = services()->settings()->isTempVipEnabled();
        $defaultReserveBalanceLimit = services()->settings()->getDefaultReserveBalanceLimit();

        return Inertia::render('Settings/Index', compact(
            'appSlogan',
            'primeTimeBonus',
            'supportLink',
            'landingTelegramLink',
            'fundsOnHoldTime',
            'maxPendingDisputes',
            'maxRejectedDisputes',
            'tempVipRequiredDeals',
            'tempVipDurationMinutes',
            'tempVipEnabled',
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

    public function updateSupportLink(Request $request)
    {
        $request->validate(['support_link' => 'required', 'url:https']);

        services()->settings()->updateSupportLink($request->support_link);

        return redirect()->route('admin.settings.index');
    }

    public function updateLandingTelegramLink(Request $request)
    {
        $raw = $request->input('landing_telegram_link');

        if (! is_string($raw) || trim($raw) === '') {
            services()->settings()->updateLandingTelegramLink(null);

            return redirect()->route('admin.settings.index');
        }

        $request->validate([
            'landing_telegram_link' => ['required', 'string', 'url:https', 'max:512'],
        ]);

        services()->settings()->updateLandingTelegramLink(trim($raw));

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

    public function updateTempVip(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'required_deals' => ['required', 'integer', 'min:1'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        if (array_key_exists('enabled', $validated)) {
            services()->settings()->updateTempVipEnabled((bool) $validated['enabled']);
        }
        services()->settings()->updateTempVipRequiredDeals($validated['required_deals']);
        services()->settings()->updateTempVipDurationMinutes($validated['duration_minutes']);

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
