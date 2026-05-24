# Step 8 — Admin UI (Merchant Traffic Categories)

> Source: repository implementation (session), Merchant/Index.vue + modals
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Admin UI for merchant traffic categories wired to `admin.traffic-categories.*` JSON API and `admin.merchants.categories.update`. Entry point: admin merchants list (`/admin/merchants`).

## Frontend files

| File | Role |
|------|------|
| `resources/js/composables/useMerchantTrafficCategories.js` | Shared fetch/cache for `admin.traffic-categories.index` |
| `resources/js/Modals/MerchantTrafficCategory/MerchantTrafficCategoryManagerModal.vue` | Category CRUD, global toggle, apply-to-all, delete confirm |
| `resources/js/Modals/MerchantTrafficCategory/MerchantTrafficCategoriesAssignModal.vue` | Per-merchant multiselect sync |
| `resources/js/Pages/Merchant/Index.vue` | `Категории` header button, table column, row actions |
| `resources/js/store/modal.js` | `merchantTrafficCategoryManager`, `merchantTrafficCategoriesAssign` |

Modals and `ConfirmModal` render only in admin view on `Merchant/Index.vue` (not global layout).

## Merchant list (`Merchant/Index.vue`)

- Header button **Категории** → `openMerchantTrafficCategoryManagerModal`.
- Table column **Категории**: badges from `merchant.traffic_categories`; muted **Без категорий** when empty.
- Row dropdown: **Категории** (assign modal) + **Настройки** (existing).
- After assign/save: `fetchMerchants()` refreshes paginated data.

## Manager modal

- Loads `GET admin.traffic-categories.index` on open.
- Global block: badge `Категории включены` / `Категории выключены`, toggle → `PATCH admin.traffic-categories.settings.enabled.update`.
- Left list + right editor (pattern similar to payment-detail schedules modal).
- Create → `POST admin.traffic-categories.store`; edit → `PATCH admin.traffic-categories.update`.
- Delete → `DELETE admin.traffic-categories.destroy` via `ConfirmModal`.
- Apply to all → `POST admin.traffic-categories.apply-to-all-traders` via `ConfirmModal`.
- Copy: spec texts for global disabled state, default flag (new traders only), apply-to-all, delete.

## Assign modal

- `Multiselect` with `value-key="id"`, `label-key="name"`.
- Save → `PATCH admin.merchants.categories.update` body `{ category_ids: [...] }`.
- Helper text: unrestricted merchant when no categories.

## Backend touch (step 8)

| Area | Change |
|------|--------|
| `MerchantResource` | `traffic_categories` — `[{ id, name }]` when `categories` relation loaded; `categories` remains ID array |
| `Admin\MerchantController` | `index` / `indexData` eager-load `categories` |

## Still pending

- Trader UI step 9 (`Order/Index.vue` category row).
- Manual verification steps 10–11.
- Automated tests step 12 (deferred).
