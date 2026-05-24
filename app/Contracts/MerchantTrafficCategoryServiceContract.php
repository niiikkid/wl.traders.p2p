<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\TrafficCategory\TrafficCategoryUpsertDTO;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface MerchantTrafficCategoryServiceContract
{
    public function create(TrafficCategoryUpsertDTO $data): Category;

    public function update(Category $category, TrafficCategoryUpsertDTO $data): Category;

    public function delete(Category $category): void;

    public function syncMerchantCategories(Merchant $merchant, array $categoryIds): void;

    public function setTraderCategoryEnabled(User $trader, Category $category, bool $enabled): void;

    /**
     * @param  array<int, int|string>  $categoryIds
     */
    public function syncTraderAllowedCategoryIds(User $trader, array $categoryIds): void;

    public function applyToAllTraders(Category $category): void;

    public function initializeDefaultsForTrader(User $trader): void;

    /**
     * Restricts an online-trader query for payment-detail assignment when merchant traffic categories apply.
     */
    public function constrainEligibleTradersForMerchant(Builder $userQuery, Merchant $merchant): void;
}
