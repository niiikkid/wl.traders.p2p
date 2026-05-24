<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trader\TrafficCategory\UpdateCategoryEnabledRequest;
use App\Http\Resources\TraderTrafficCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrafficCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureTrader();

        $merchantTrafficCategoriesEnabled = services()->settings()->isMerchantTrafficCategoriesEnabled();

        if (! $merchantTrafficCategoriesEnabled) {
            return response()->json([
                'success' => true,
                'data' => [
                    'merchant_traffic_categories_enabled' => false,
                    'categories' => [],
                ],
            ]);
        }

        $enabledByCategoryId = $request->user()
            ->trafficCategories()
            ->get()
            ->mapWithKeys(fn (Category $category) => [
                $category->id => (bool) $category->pivot->enabled,
            ]);

        $categories = Category::query()
            ->orderBy('name')
            ->get()
            ->each(function (Category $category) use ($enabledByCategoryId) {
                $category->trader_enabled = (bool) ($enabledByCategoryId[$category->id] ?? false);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'merchant_traffic_categories_enabled' => true,
                'categories' => TraderTrafficCategoryResource::collection($categories)->resolve(),
            ],
        ]);
    }

    public function updateEnabled(UpdateCategoryEnabledRequest $request, Category $category): JsonResponse
    {
        $this->ensureTrader();
        $this->ensureFeatureEnabled();

        $enabled = (bool) $request->validated('enabled');

        services()->merchantTrafficCategory()->setTraderCategoryEnabled(
            $request->user(),
            $category,
            $enabled,
        );

        $category->trader_enabled = $enabled;

        return response()->json([
            'success' => true,
            'data' => [
                'category' => TraderTrafficCategoryResource::make($category)->resolve(),
            ],
        ]);
    }

    private function ensureTrader(): void
    {
        if (! isRouteFor('Trader')) {
            abort(403);
        }
    }

    private function ensureFeatureEnabled(): void
    {
        if (! services()->settings()->isMerchantTrafficCategoriesEnabled()) {
            abort(403, 'Категории трафика отключены.');
        }
    }
}
