<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTO\TrafficCategory\TrafficCategoryUpsertDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrafficCategory\StoreRequest;
use App\Http\Requests\Admin\TrafficCategory\SyncMerchantCategoriesRequest;
use App\Http\Requests\Admin\TrafficCategory\UpdateEnabledRequest;
use App\Http\Requests\Admin\TrafficCategory\UpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MerchantResource;
use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;

class MerchantTrafficCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'merchant_traffic_categories_enabled' => services()->settings()->isMerchantTrafficCategoriesEnabled(),
                'categories' => CategoryResource::collection($categories)->resolve(),
            ],
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = services()->merchantTrafficCategory()->create(
            TrafficCategoryUpsertDTO::makeFromRequest($validated, $request->boolean('enabled_by_default')),
        );

        return response()->json([
            'success' => true,
            'data' => CategoryResource::make($category)->resolve(),
        ]);
    }

    public function update(UpdateRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();

        $category = services()->merchantTrafficCategory()->update(
            $category,
            TrafficCategoryUpsertDTO::makeFromRequest($validated, $request->boolean('enabled_by_default')),
        );

        return response()->json([
            'success' => true,
            'data' => CategoryResource::make($category)->resolve(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        services()->merchantTrafficCategory()->delete($category);

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateEnabled(UpdateEnabledRequest $request): JsonResponse
    {
        $enabled = (bool) $request->validated('enabled');

        services()->settings()->updateMerchantTrafficCategoriesEnabled($enabled);

        return response()->json([
            'success' => true,
            'data' => [
                'merchant_traffic_categories_enabled' => $enabled,
            ],
        ]);
    }

    public function applyToAllTraders(Category $category): JsonResponse
    {
        services()->merchantTrafficCategory()->applyToAllTraders($category);

        return response()->json([
            'success' => true,
            'data' => CategoryResource::make($category->refresh())->resolve(),
        ]);
    }

    public function syncMerchantCategories(SyncMerchantCategoriesRequest $request, Merchant $merchant): JsonResponse
    {
        $categoryIds = $request->validated('category_ids');

        services()->merchantTrafficCategory()->syncMerchantCategories(
            $merchant,
            $categoryIds,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'merchant' => MerchantResource::make($merchant->fresh()->load('categories'))->resolve(),
            ],
        ]);
    }
}
