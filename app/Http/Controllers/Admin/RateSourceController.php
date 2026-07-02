<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RateSourceDirection;
use App\Enums\RateSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RateSource\SaveRequest;
use App\Http\Resources\RateSourceResource;
use App\Jobs\RefreshRateSourceJob;
use App\Models\RateSource;
use App\Models\ValueObjects\Settings\BinancePriceParserSideSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSideSettings;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RateSourceController extends Controller
{
    public function index(): Response
    {
        $sources = RateSourceResource::collection(
            RateSource::query()->orderByDesc('id')->get()
        )->resolve();

        return Inertia::render('RateSource/Index', [
            'sources' => $sources,
            'types' => RateSourceType::values(),
            'directions' => RateSourceDirection::values(),
            'currencies' => Currency::getAllCodes(),
        ]);
    }

    public function store(SaveRequest $request): JsonResponse
    {
        $type = RateSourceType::from($request->validated('type'));

        $source = RateSource::query()->create([
            'name' => $request->validated('name'),
            'type' => $type,
            'direction' => RateSourceDirection::from($request->validated('direction')),
            'base_currency' => 'usdt',
            'quote_currency' => strtolower($request->validated('quote_currency')),
            'is_active' => $request->boolean('is_active', true),
            'settings' => $this->normalizeSettings($type, (array) $request->validated('settings', [])),
        ]);

        $this->primeRate($source);

        return response()->successWithMessage('Источник курса создан', [
            'source' => RateSourceResource::make($source->fresh())->resolve(),
        ]);
    }

    public function update(SaveRequest $request, RateSource $rateSource): JsonResponse
    {
        $type = RateSourceType::from($request->validated('type'));

        $rateSource->update([
            'name' => $request->validated('name'),
            'type' => $type,
            'direction' => RateSourceDirection::from($request->validated('direction')),
            'quote_currency' => strtolower($request->validated('quote_currency')),
            'is_active' => $request->boolean('is_active', true),
            'settings' => $this->normalizeSettings($type, (array) $request->validated('settings', [])),
        ]);

        $this->primeRate($rateSource);

        return response()->successWithMessage('Источник курса обновлён', [
            'source' => RateSourceResource::make($rateSource->fresh())->resolve(),
        ]);
    }

    public function destroy(RateSource $rateSource): JsonResponse
    {
        $rateSource->delete();

        return response()->successWithMessage('Источник курса удалён');
    }

    /**
     * Queue a manual refresh for an automatic source.
     */
    public function refresh(RateSource $rateSource): JsonResponse
    {
        if (! $rateSource->isAutomatic()) {
            return response()->failWithMessage('Ручной источник не обновляется парсером.');
        }

        RefreshRateSourceJob::dispatch($rateSource->id);

        return response()->successWithMessage('Обновление курса поставлено в очередь.');
    }

    /**
     * Synchronously parse a rate for the current form settings without saving,
     * so the admin can validate a source configuration before persisting it.
     */
    public function preview(SaveRequest $request): JsonResponse
    {
        $type = RateSourceType::from($request->validated('type'));

        $source = new RateSource([
            'name' => $request->validated('name'),
            'type' => $type,
            'direction' => RateSourceDirection::from($request->validated('direction')),
            'base_currency' => 'usdt',
            'quote_currency' => strtolower($request->validated('quote_currency')),
            'is_active' => true,
            'settings' => $this->normalizeSettings($type, (array) $request->validated('settings', [])),
        ]);

        $result = services()->market()->previewSource($source);

        return response()->success($result);
    }

    /**
     * Parser filter options (payment methods / countries) for the source form.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $typeValue = $request->query('type');
        $currencyValue = $request->query('currency');

        $type = $typeValue ? RateSourceType::tryFrom($typeValue) : null;

        if (! $type || ! $type->isAutomatic() || ! $currencyValue || ! Currency::isCurrency($currencyValue)) {
            return response()->success(['filter_conditions' => []]);
        }

        $conditions = services()->market()->getFilterConditions(
            Currency::make($currencyValue),
            $type->toMarketEnum()
        );

        return response()->success(['filter_conditions' => $conditions]);
    }

    /**
     * Active sources for merchant binding, grouped by currency and direction.
     */
    public function options(): JsonResponse
    {
        $sources = RateSource::query()
            ->active()
            ->orderBy('quote_currency')
            ->orderBy('direction')
            ->orderByDesc('id')
            ->get();

        return response()->success([
            'sources' => RateSourceResource::collection($sources)->resolve(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(RateSourceType $type, array $settings): array
    {
        $side = in_array($settings['side'] ?? null, ['buy', 'sell'], true) ? $settings['side'] : null;

        return match ($type) {
            RateSourceType::MANUAL => array_filter([
                'rate' => isset($settings['rate']) ? (float) $settings['rate'] : 0.0,
                'side' => $side,
            ], fn ($value) => $value !== null),
            RateSourceType::BYBIT => array_merge(
                CurrencyPriceParserSideSettings::fromArray($settings)->toArray(),
                array_filter(['side' => $side], fn ($value) => $value !== null),
            ),
            RateSourceType::BINANCE => array_merge(
                BinancePriceParserSideSettings::fromArray($settings)->toArray(),
                array_filter(['side' => $side], fn ($value) => $value !== null),
            ),
        };
    }

    /**
     * Populate the ready rate immediately: manual sources synchronously, automatic via queue.
     */
    private function primeRate(RateSource $source): void
    {
        if ($source->isAutomatic()) {
            RefreshRateSourceJob::dispatch($source->id);

            return;
        }

        services()->market()->refreshSource($source);
    }
}
