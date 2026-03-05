<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = Merchant::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $merchants = MerchantResource::collection($merchants);

        return Inertia::render('Merchant/Index', compact('merchants'));
    }

    public function indexData(Request $request): JsonResponse
    {
        $merchants = Merchant::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 10));

        return response()->json(
            MerchantResource::collection($merchants)->response()->getData(true)
        );
    }

    public function ban(Request $request, Merchant $merchant)
    {
        $merchant->update([
            'banned_at' => now(),
            'validated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh('categories'))->resolve(),
            ]);
        }

        return back();
    }

    public function unban(Request $request, Merchant $merchant)
    {
        $merchant->update([
            'banned_at' => null,
            'validated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh('categories'))->resolve(),
            ]);
        }

        return back();
    }

    public function validated(Request $request, Merchant $merchant)
    {
        $merchant->update([
            'validated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh('categories'))->resolve(),
            ]);
        }

        return back();
    }

    public function updateSettings(Request $request, Merchant $merchant)
    {
        $request->validate([
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'max_order_wait_time' => 'nullable|integer|min:1000',
            'min_order_amounts' => 'nullable|array',
            'min_order_amounts.*' => 'numeric|min:0',
        ]);

        $merchant->update([
            'max_order_wait_time' => $request->max_order_wait_time,
            'min_order_amounts' => $request->min_order_amounts,
        ]);

        if ($request->has('categories')) {
            $merchant->categories()->sync($request->categories);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh()->load('categories'))->resolve(),
            ]);
        }

        return back()->with([
            'merchant' => new MerchantResource($merchant->fresh()->load('categories')),
        ]);
    }

    /**
     * Обновить GEO (валюта => маркет) для мерчанта.
     *
     * @throws ValidationException
     */
    public function updateGeo(Request $request, Merchant $merchant): JsonResponse
    {
        $validator = validator($request->all(), [
            'geos' => ['required', 'array', 'min:1'],
            'geos.*.currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'geos.*.market' => ['required', Rule::enum(MarketEnum::class)],
        ]);

        $validator->after(function () use ($validator, $request) {
            $geoMap = [];
            foreach ($request->input('geos', []) as $geo) {
                $currencyCode = strtolower($geo['currency'] ?? '');
                $marketValue = $geo['market'] ?? null;

                if (isset($geoMap[$currencyCode])) {
                    $validator->errors()->add('geos', "Валюта {$currencyCode} уже добавлена в GEO.");
                    continue;
                }

                $marketEnum = MarketEnum::tryFrom($marketValue);
                if (! $marketEnum) {
                    $validator->errors()->add('geos', "Маркет {$marketValue} не поддерживается.");
                    continue;
                }

                $currency = Currency::make($currencyCode);
                $supportsCurrency = services()->market()
                    ->getSupportedCurrencies($marketEnum)
                    ->contains(fn (Currency $supported) => $supported->getCode() === $currency->getCode());

                if (! $supportsCurrency) {
                    $validator->errors()->add(
                        'geos',
                        "Маркет {$marketEnum->value} не поддерживает валюту " . strtoupper($currencyCode)
                    );
                }

                $geoMap[$currencyCode] = $marketEnum->value;
            }
        });

        $validator->validate();

        $geoMap = collect($request->input('geos', []))
            ->mapWithKeys(fn (array $geo) => [strtolower($geo['currency']) => $geo['market']])
            ->toArray();

        $settings = $merchant->settings ?? [];
        $settings['geos'] = $geoMap;

        $merchant->settings = $settings;
        if (! empty($geoMap)) {
            $merchant->market = MarketEnum::from(reset($geoMap));
        }
        $merchant->save();

        return response()->json([
            'merchant' => MerchantResource::make($merchant->fresh()->load('categories'))->resolve(),
        ]);
    }
}
