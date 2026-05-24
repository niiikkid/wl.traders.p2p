# Step 7 — FindAvailablePaymentDetail Traffic Filter

> Source: repository implementation session
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Implemented merchant traffic category filtering in the payment-detail assignment path. Step 7 of the implementation plan is complete; target admin/trader UI (steps 8–9) remain pending.

## Integration point

`App\Services\Order\Features\OrderDetailProvider\Classes\FindAvailablePaymentDetail::queryPaymentDetails()`

After base trader eligibility (`is_online`, `stop_traffic`, not banned/archived), before wallet/dispute filters:

```php
->tap(fn (Builder $query) => services()->merchantTrafficCategory()->constrainEligibleTradersForMerchant($query, $this->merchant))
```

Chain unchanged elsewhere: wallet, disputes, payment detail limits, gateways, `->active()`, `->availableBySchedule()`, etc.

## New service API

`MerchantTrafficCategoryService::constrainEligibleTradersForMerchant(Builder $userQuery, Merchant $merchant): void`

Declared on `MerchantTrafficCategoryServiceContract`.

### Algorithm

1. Load merchant category IDs from `category_merchant` (`merchant->categories()->pluck('categories.id')`).
2. Read `services()->settings()->isMerchantTrafficCategoriesEnabled()` inside try/catch.
3. **On setting read failure (`Throwable`):**
   - `Log::error('Failed to read merchant_traffic_categories_enabled setting', …)` with `merchant_id`, `merchant_category_ids`, exception.
   - If merchant has categories → **fail closed:** `$userQuery->whereRaw('0 = 1')` (no traders eligible).
   - If merchant has no categories → no constraint (uncategorized merchants still work).
4. **Global flag off** → return (no category filter).
5. **Merchant has no categories** → return (unrestricted merchant).
6. **Global on + merchant has categories** →

```php
$userQuery->whereHas('trafficCategories', function (Builder $query) use ($merchantCategoryIds) {
    $query->whereIn('categories.id', $merchantCategoryIds)
        ->where('category_user.enabled', true);
});
```

Uses **`category_user` pivot only**, not `user_metas.allowed_categories` JSON.

## Product rules enforced

- Global off → categories ignored in traffic selection.
- Merchant without categories → all eligible traders (subject to other filters).
- Merchant with categories → trader must have at least one matching category with `enabled = true`.
- Trader with all categories disabled → excluded from categorized merchants; still eligible for uncategorized merchants.
- Setting read failure on categorized merchant → traffic does not assign (fail closed + log).

## Assignment failure UX

When no payment detail matches, existing `OrderDetailProvider::provide()` throws `OrderException::make('Подходящие платежные реквизиты не найдены.')` — unchanged.

## Still pending

- Admin UI (step 8): category modal, global toggle, merchant badges, assignment modal
- Trader UI (step 9): category row on `Order/Index.vue`
- Copy pass and manual verification (steps 10–11)
