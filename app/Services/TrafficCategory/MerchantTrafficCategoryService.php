<?php

declare(strict_types=1);

namespace App\Services\TrafficCategory;

use App\Contracts\MerchantTrafficCategoryServiceContract;
use App\DTO\TrafficCategory\TrafficCategoryUpsertDTO;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MerchantTrafficCategoryService implements MerchantTrafficCategoryServiceContract
{
    public function create(TrafficCategoryUpsertDTO $data): Category
    {
        return Transaction::run(function () use ($data) {
            $category = Category::query()->create([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->name),
                'description' => $data->description,
                'enabled_by_default' => $data->enabled_by_default,
            ]);

            if ($category->enabled_by_default) {
                $this->setCategoryEnabledForAllTraders($category, true);
            }

            return $category;
        });
    }

    public function update(Category $category, TrafficCategoryUpsertDTO $data): Category
    {
        return Transaction::run(function () use ($category, $data) {
            $category->update([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->name, $category->id),
                'description' => $data->description,
                'enabled_by_default' => $data->enabled_by_default,
            ]);

            return $category->refresh();
        });
    }

    public function delete(Category $category): void
    {
        Transaction::run(function () use ($category) {
            $category->delete();
        });
    }

    public function syncMerchantCategories(Merchant $merchant, array $categoryIds): void
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);

        Transaction::run(function () use ($merchant, $categoryIds) {
            $merchant->categories()->sync($categoryIds);
        });
    }

    public function setTraderCategoryEnabled(User $trader, Category $category, bool $enabled): void
    {
        Transaction::run(function () use ($trader, $category, $enabled) {
            $this->upsertTraderCategoryPivot($trader, $category->id, $enabled);
            $this->syncLegacyAllowedCategoriesJson($trader);
        });
    }

    public function syncTraderAllowedCategoryIds(User $trader, array $categoryIds): void
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);

        Transaction::run(function () use ($trader, $categoryIds) {
            $allCategoryIds = Category::query()->orderBy('id')->pluck('id');

            if ($categoryIds === []) {
                foreach ($allCategoryIds as $categoryId) {
                    $this->upsertTraderCategoryPivot($trader, (int) $categoryId, true);
                }

                $trader->meta?->update(['allowed_categories' => []]);

                return;
            }

            foreach ($allCategoryIds as $categoryId) {
                $enabled = in_array((int) $categoryId, $categoryIds, true);
                $this->upsertTraderCategoryPivot($trader, (int) $categoryId, $enabled);
            }

            $trader->meta?->update(['allowed_categories' => $categoryIds]);
        });
    }

    public function applyToAllTraders(Category $category): void
    {
        $enabled = (bool) $category->enabled_by_default;

        $this->setCategoryEnabledForAllTraders($category, $enabled);
    }

    public function initializeDefaultsForTrader(User $trader): void
    {
        if (! $trader->hasRole('Trader')) {
            return;
        }

        Transaction::run(function () use ($trader) {
            $defaultCategoryIds = Category::query()
                ->where('enabled_by_default', true)
                ->pluck('id');

            if ($defaultCategoryIds->isEmpty()) {
                return;
            }

            foreach ($defaultCategoryIds as $categoryId) {
                $this->upsertTraderCategoryPivot($trader, (int) $categoryId, true);
            }

            $this->syncLegacyAllowedCategoriesJson($trader);
        });
    }

    public function constrainEligibleTradersForMerchant(Builder $userQuery, Merchant $merchant): void
    {
        $merchantCategoryIds = $this->merchantCategoryIds($merchant);

        $globallyEnabled = $this->resolveMerchantTrafficCategoriesEnabled($merchant, $merchantCategoryIds);

        if ($globallyEnabled === null) {
            $userQuery->whereRaw('0 = 1');

            return;
        }

        if (! $globallyEnabled || $merchantCategoryIds === []) {
            return;
        }

        $userQuery->whereHas('trafficCategories', function (Builder $query) use ($merchantCategoryIds) {
            $query->whereIn('categories.id', $merchantCategoryIds)
                ->where('category_user.enabled', true);
        });
    }

    /**
     * @return list<int>
     */
    private function merchantCategoryIds(Merchant $merchant): array
    {
        return $merchant->categories()
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $merchantCategoryIds
     */
    private function resolveMerchantTrafficCategoriesEnabled(Merchant $merchant, array $merchantCategoryIds): ?bool
    {
        try {
            return services()->settings()->isMerchantTrafficCategoriesEnabled();
        } catch (Throwable $exception) {
            Log::error('Failed to read merchant_traffic_categories_enabled setting', [
                'merchant_id' => $merchant->id,
                'merchant_category_ids' => $merchantCategoryIds,
                'exception' => $exception,
            ]);

            if ($merchantCategoryIds !== []) {
                return null;
            }

            return false;
        }
    }

    private function setCategoryEnabledForAllTraders(Category $category, bool $enabled): void
    {
        $now = now();

        User::role('Trader')
            ->select('id')
            ->chunkById(200, function (Collection $traders) use ($category, $enabled, $now) {
                $rows = $traders->map(fn (User $trader) => [
                    'category_id' => $category->id,
                    'user_id' => $trader->id,
                    'enabled' => $enabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                DB::table('category_user')->upsert(
                    $rows,
                    ['category_id', 'user_id'],
                    ['enabled', 'updated_at'],
                );
            });
    }

    private function upsertTraderCategoryPivot(User $trader, int $categoryId, bool $enabled): void
    {
        $trader->trafficCategories()->syncWithoutDetaching([
            $categoryId => ['enabled' => $enabled],
        ]);
    }

    private function syncLegacyAllowedCategoriesJson(User $trader): void
    {
        $enabledCategoryIds = $trader->trafficCategories()
            ->wherePivot('enabled', true)
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $trader->meta?->update([
            'allowed_categories' => $enabledCategoryIds,
        ]);
    }

    /**
     * @param  array<int, int|string>  $categoryIds
     * @return array<int, int>
     */
    private function normalizeCategoryIds(array $categoryIds): array
    {
        return array_values(array_unique(array_map('intval', $categoryIds)));
    }

    private function uniqueSlug(string $name, ?int $exceptCategoryId = null): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $suffix = 1;

        while (
            Category::query()
                ->where('slug', $candidate)
                ->when($exceptCategoryId !== null, fn ($query) => $query->where('id', '!=', $exceptCategoryId))
                ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
