<?php

namespace App\Http\Controllers\Trader;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $markets = [];
        $categories = Category::all(['id', 'name'])->map(function ($category) {
            return [
                'name' => $category->name,
                'value' => $category->id,
            ];
        });

        $settings = auth()->user()->meta;

        foreach (MarketEnum::cases() as $market) {
            $markets[] = [
                'name' => trans("market.name.{$market->value}"),
                'value' => $market,
            ];
        }

        return Inertia::render('Settings/Trader/Index', compact('settings', 'markets', 'categories'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'allowed_markets' => ['nullable', 'array'],
            'allowed_markets.*' => ['required', 'string', Rule::enum(MarketEnum::class)],
            'allowed_categories' => ['nullable', 'array'],
            'allowed_categories.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        auth()->user()->meta->update([
            'allowed_markets' => $request->allowed_markets,
            'allowed_categories' => $request->allowed_categories,
        ]);
    }
}
