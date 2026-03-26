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
            'geos.*.reference_rate' => ['nullable', 'numeric', 'gt:0'],
            'geos.*.max_deviation_percent' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
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

                try {
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

                    if ($marketEnum->equals(MarketEnum::MERCHANT_API)) {
                        $referenceRate = $geo['reference_rate'] ?? null;
                        $maxDeviationPercent = $geo['max_deviation_percent'] ?? null;

                        if ($referenceRate === null || $referenceRate === '') {
                            $validator->errors()->add(
                                'geos',
                                "Для валюты " . strtoupper($currencyCode) . " в маркете merchant_api нужно указать опорный курс."
                            );
                        } elseif (! $this->isDecimalWithinPrecision((string) $referenceRate, $currency->getPrecision())) {
                            $validator->errors()->add(
                                'geos',
                                "Опорный курс для " . strtoupper($currencyCode) . " может содержать не более {$currency->getPrecision()} знаков после запятой."
                            );
                        }

                        if ($maxDeviationPercent === null || $maxDeviationPercent === '') {
                            $validator->errors()->add(
                                'geos',
                                "Для валюты " . strtoupper($currencyCode) . " в маркете merchant_api нужно указать допустимое расхождение в процентах."
                            );
                        } elseif (! $this->isDecimalWithinPrecision((string) $maxDeviationPercent, 2)) {
                            $validator->errors()->add(
                                'geos',
                                "Допустимое расхождение для " . strtoupper($currencyCode) . " может содержать не более 2 знаков после запятой."
                            );
                        }
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('geos', "Валюта {$currencyCode} не поддерживается.");
                }

                $geoMap[$currencyCode] = $marketEnum->value;
            }
        });

        $validator->validate();

        $geoMap = collect($request->input('geos', []))
            ->mapWithKeys(fn (array $geo) => [strtolower($geo['currency']) => $geo['market']])
            ->toArray();

        $merchantApiRateSettings = collect($request->input('geos', []))
            ->filter(function (array $geo) {
                return ($geo['market'] ?? null) === MarketEnum::MERCHANT_API->value;
            })
            ->mapWithKeys(function (array $geo) {
                $currencyCode = strtolower($geo['currency']);

                return [
                    $currencyCode => [
                        'reference_rate' => (float) $geo['reference_rate'],
                        'max_deviation_percent' => round((float) $geo['max_deviation_percent'], 2),
                    ],
                ];
            })
            ->toArray();

        $settings = $merchant->settings ?? [];
        $settings['geos'] = $geoMap;
        $settings['merchant_api_rates'] = $merchantApiRateSettings;

        $merchant->settings = $settings;
        if (! empty($geoMap)) {
            $merchant->market = MarketEnum::from(reset($geoMap));
        }
        $merchant->save();

        return response()->json([
            'merchant' => MerchantResource::make($merchant->fresh()->load('categories'))->resolve(),
        ]);
    }

    protected function isDecimalWithinPrecision(string $value, int $maxScale): bool
    {
        $normalized = trim($value);
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(?:\.(\d+))?$/', $normalized, $matches)) {
            return false;
        }

        $scale = isset($matches[1]) ? strlen($matches[1]) : 0;

        return $scale <= $maxScale;
    }
}
