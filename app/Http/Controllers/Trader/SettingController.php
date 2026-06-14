<?php

namespace App\Http\Controllers\Trader;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $markets = [];
        $settings = auth()->user()->meta;

        foreach (MarketEnum::selectableCases() as $market) {
            $markets[] = [
                'name' => trans("market.name.{$market->value}"),
                'value' => $market,
            ];
        }

        return Inertia::render('Settings/Trader/Index', compact('settings', 'markets'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'allowed_markets' => ['nullable', 'array'],
            'allowed_markets.*' => ['required', 'string', Rule::in(MarketEnum::selectableValues())],
        ]);

        $user = auth()->user();

        $user->meta->update([
            'allowed_markets' => $request->allowed_markets,
        ]);
    }
}
