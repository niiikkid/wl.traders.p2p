<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\RateSource;
use App\Services\Money\Currency;
use App\Services\Rates\ResolvedRateBinding;
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
            ->with(['user', 'wallet'])
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $merchants = MerchantResource::collection($merchants);

        return Inertia::render('Merchant/Index', compact('merchants'));
    }

    public function indexData(Request $request): JsonResponse
    {
        $merchants = Merchant::query()
            ->with(['user', 'wallet'])
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
                'merchant' => MerchantResource::make($merchant->fresh())->resolve(),
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
                'merchant' => MerchantResource::make($merchant->fresh())->resolve(),
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
                'merchant' => MerchantResource::make($merchant->fresh())->resolve(),
            ]);
        }

        return back();
    }

    public function updateSettings(Request $request, Merchant $merchant)
    {
        $request->validate([
            'max_order_wait_time' => 'nullable|integer|min:1000',
            'max_payout_wait_time' => 'nullable|integer|min:1000',
            'min_order_amounts' => 'nullable|array',
            'min_order_amounts.*' => 'numeric|min:0',
        ]);

        $merchant->update([
            'max_order_wait_time' => $request->max_order_wait_time,
            'max_payout_wait_time' => $request->max_payout_wait_time,
            'min_order_amounts' => $request->min_order_amounts,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'merchant' => MerchantResource::make($merchant->fresh())->resolve(),
            ]);
        }

        return back()->with([
            'merchant' => new MerchantResource($merchant->fresh()),
        ]);
    }

    /**
     * Обновить GEO мерчанта: для каждой валюты выбирается источник курса
     * (конкретный RateSource) либо режим «курс от мерчанта» (merchant_api).
     *
     * @throws ValidationException
     */
    public function updateGeo(Request $request, Merchant $merchant): JsonResponse
    {
        $request->validate([
            'geos' => ['required', 'array', 'min:1'],
            'geos.*.currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'geos.*.source' => ['required'],
            'geos.*.order_reference_rate' => ['nullable', 'numeric', 'gt:0'],
            'geos.*.payout_reference_rate' => ['nullable', 'numeric', 'gt:0'],
            'geos.*.max_deviation_percent' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $validator = validator([], []);

        $geoMap = [];
        $rateSourcesMap = [];
        $merchantApiRates = [];

        foreach ($request->input('geos', []) as $geo) {
            $currencyCode = strtolower($geo['currency'] ?? '');
            $sourceValue = (string) ($geo['source'] ?? '');

            if ($currencyCode === '' || ! Currency::isCurrency($currencyCode)) {
                $validator->errors()->add('geos', 'Указана неподдерживаемая валюта.');

                continue;
            }

            if (isset($geoMap[$currencyCode])) {
                $validator->errors()->add('geos', "Валюта {$currencyCode} уже добавлена в GEO.");

                continue;
            }

            $currency = Currency::make($currencyCode);

            if ($sourceValue === ResolvedRateBinding::MODE_MERCHANT_API) {
                $this->validateMerchantApiReferenceRates($validator, $currency, $geo);

                $geoMap[$currencyCode] = MarketEnum::MERCHANT_API->value;
                $rateSourcesMap[$currencyCode] = ['mode' => ResolvedRateBinding::MODE_MERCHANT_API];
                $merchantApiRates[$currencyCode] = [
                    'order_reference_rate' => (float) ($geo['order_reference_rate'] ?? 0),
                    'payout_reference_rate' => (float) ($geo['payout_reference_rate'] ?? 0),
                    'max_deviation_percent' => round((float) ($geo['max_deviation_percent'] ?? 0), 2),
                ];

                continue;
            }

            $source = ctype_digit($sourceValue)
                ? RateSource::query()
                    ->active()
                    ->whereKey((int) $sourceValue)
                    ->where('quote_currency', $currencyCode)
                    ->first()
                : null;

            if (! $source) {
                $validator->errors()->add('geos', 'Не найден активный источник курса для '.strtoupper($currencyCode).'.');

                continue;
            }

            $geoMap[$currencyCode] = $source->type->toMarketEnum()->value;
            $rateSourcesMap[$currencyCode] = [
                'mode' => ResolvedRateBinding::MODE_SOURCE,
                'source_id' => $source->id,
            ];
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        $settings = $merchant->settings ?? [];
        $settings['geos'] = $geoMap;
        $settings['rate_sources'] = $rateSourcesMap;
        $settings['merchant_api_rates'] = $merchantApiRates;

        $merchant->settings = $settings;
        if (! empty($geoMap)) {
            $merchant->market = MarketEnum::from(reset($geoMap));
        }
        $merchant->save();

        return response()->json([
            'merchant' => MerchantResource::make($merchant->fresh())->resolve(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $geo
     */
    private function validateMerchantApiReferenceRates($validator, Currency $currency, array $geo): void
    {
        $currencyCode = strtoupper($currency->getCode());
        $orderReferenceRate = $geo['order_reference_rate'] ?? null;
        $payoutReferenceRate = $geo['payout_reference_rate'] ?? null;
        $maxDeviationPercent = $geo['max_deviation_percent'] ?? null;

        if ($orderReferenceRate === null || $orderReferenceRate === '') {
            $validator->errors()->add('geos', "Для {$currencyCode} (курс от мерчанта) укажите опорный курс для сделок.");
        } elseif (! $this->isDecimalWithinPrecision((string) $orderReferenceRate, $currency->getPrecision())) {
            $validator->errors()->add('geos', "Опорный курс сделок для {$currencyCode} может содержать не более {$currency->getPrecision()} знаков после запятой.");
        }

        if ($payoutReferenceRate === null || $payoutReferenceRate === '') {
            $validator->errors()->add('geos', "Для {$currencyCode} (курс от мерчанта) укажите опорный курс для выплат.");
        } elseif (! $this->isDecimalWithinPrecision((string) $payoutReferenceRate, $currency->getPrecision())) {
            $validator->errors()->add('geos', "Опорный курс выплат для {$currencyCode} может содержать не более {$currency->getPrecision()} знаков после запятой.");
        }

        if ($maxDeviationPercent === null || $maxDeviationPercent === '') {
            $validator->errors()->add('geos', "Для {$currencyCode} (курс от мерчанта) укажите допустимое расхождение в процентах.");
        } elseif (! $this->isDecimalWithinPrecision((string) $maxDeviationPercent, 2)) {
            $validator->errors()->add('geos', "Допустимое расхождение для {$currencyCode} может содержать не более 2 знаков после запятой.");
        }
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
