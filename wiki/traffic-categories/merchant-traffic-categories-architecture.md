# Merchant Traffic Categories Architecture

> Sources: User conversation, 2026-05-22; repository exploration, 2026-05-22
> Raw: [Merchant Traffic Categories Requirements](../../raw/traffic-categories/2026-05-22-merchant-traffic-categories-requirements.md)
> Updated: 2026-05-22

## Overview

Merchant traffic categories let administrators mark merchants with one or more traffic groups, and let traders choose which groups they want to receive. The feature is optional at the system level: when disabled, traders do not see category controls and payment detail selection behaves exactly like the old system. Category choices are preserved while disabled, so admin can prepare or pause the feature without losing data.

## Product Rules

- Categories filter access to traffic only. They do not calculate trader commission and do not change payout math.
- A merchant can have zero, one, or many categories.
- A trader can enable zero, one, or many categories.
- If the global feature is disabled, categories are ignored everywhere in traffic selection.
- If a merchant has no categories, its traffic is available to all eligible traders.
- If a merchant has categories, a trader is eligible when at least one merchant category is enabled for that trader.
- If a trader disables every category, they can still receive traffic from merchants without categories.
- Category descriptions are required because traders see them in tooltips.
- The system toggle is disabled by default.

## Existing Code Anchors

The repository already has part of the foundation:

- `app/Models/Category.php` exists as the category model.
- `app/Models/Merchant.php` has a `categories()` many-to-many relation.
- `category_merchant` exists as the merchant/category pivot table.
- `app/Models/UserMeta.php` already has an `allowed_categories` JSON-like field concept.
- `app/Http/Controllers/Admin/CategoryController.php` and `resources/js/Pages/Category/*` exist but admin routes are currently disabled.
- `app/Http/Controllers/Admin/MerchantController.php` already syncs `categories` in merchant settings.
- `resources/js/Pages/Merchant/Index.vue` is the admin merchant list surface.
- `resources/js/Modals/Merchant/MerchantSettingsModal.vue` and `resources/js/Pages/Merchant/Tabs/Settings.vue` are the existing merchant settings flow.
- `resources/js/Pages/Order/Index.vue` is the trader orders page where category toggles should appear above the table.
- `app/Services/Order/Features/OrderDetailProvider/Classes/FindAvailablePaymentDetail.php` is the critical backend filtering point for available payment details.

## Data Model

### Categories

Use the existing `categories` concept, extending it if needed:

- `id`
- `name`, required, short, unique enough for admin clarity
- `description`, required text, shown to traders in a tooltip
- `enabled_by_default`, boolean, default `false`
- timestamps

Do not store commission percentage as a required field in the first version. Percent-based categories are only one use case.

### Merchant Category Assignments

Use the existing many-to-many pivot:

- `category_merchant`
- `merchant_id`
- `category_id`

When a category is deleted, detach it from merchants. If a merchant has no remaining categories, it becomes unrestricted by categories.

### Trader Category Choices

Prefer a normalized pivot for reliable filtering:

- `category_user`
- `user_id`
- `category_id`
- `enabled`
- timestamps

This is safer than relying only on JSON in `user_metas.allowed_categories`, because the payment-detail query needs to filter traders efficiently and reliably. If the project chooses to keep `allowed_categories`, the backend query must still be designed carefully to avoid slow JSON filtering. A migration path can preserve existing `allowed_categories` data and later remove or ignore it.

### Global Feature Setting

Use `SettingService` for a boolean setting such as:

- `merchant_traffic_categories_enabled`, default `false`

If the setting cannot be read because of an unexpected system problem, fail closed for categorized traffic: do not silently ignore category restrictions. Log the exception clearly. This avoids accidentally sending restricted merchant traffic to traders who did not opt in.

## Admin UX

### Merchant Page Entry Point

On the admin merchant list, add a compact button in the upper-right area:

- Label: `Категории`
- Opens the category management modal.
- The modal should show a clear status badge:
  - `Категории выключены` when global filtering is off.
  - `Категории включены` when global filtering is on.

Plain-language explanation:

> Когда категории выключены, они не влияют на раздачу трафика. Трейдеры их не видят. Можно спокойно настроить категории заранее и включить позже.

### Category Management Modal

The modal should support:

- Create category.
- Edit category.
- Delete category.
- Toggle global feature state.
- Apply a category default to all traders.

Fields:

- `Название` — short visible name.
- `Описание` — required explanation shown to traders on hover.
- `Включать новым трейдерам по умолчанию` — default state for future traders.

Important copy near the default toggle:

> Это влияет только на новых трейдеров. Уже выбранные настройки трейдеров не меняются.

Explicit mass action:

> Применить ко всем трейдерам

Confirmation text should explain:

> Если категория включена по умолчанию, она включится всем трейдерам. Если выключена — выключится всем трейдерам.

### Merchant Category Assignment

Add a merchant action in the existing settings/dropdown area:

- Label: `Категории`
- Opens a small modal with multi-select category assignment.
- Admin can assign one or more categories.
- Admin can clear all categories.

Show this explanation in the modal:

> Если у мерчанта нет категорий, его заявки доступны всем трейдерам без фильтрации по категориям.

### Merchant Table Badges

Add a `Категории` column to the admin merchant table:

- Show assigned categories as compact badges.
- If no categories: show a muted badge like `Без категорий`.
- If global feature is disabled, still show badges, but make the disabled system state visible near the page action or modal entry.

## Trader UX

Show the category row only when `merchant_traffic_categories_enabled` is true.

Place it above the trader orders table in `resources/js/Pages/Order/Index.vue`, likely in or near the existing `MainTableSection` toolbar area.

The row should be full width but compact:

- Small title: `Категории трафика`
- Short explanation:

> Включите категории, с которыми хотите работать. Если выключить категорию, заявки от таких мерчантов не будут приходить.

- Category buttons:
  - Enabled state should look active and confident.
  - Disabled state should look muted.
  - Every button should indicate hover help, for example with a small info icon or dotted underline.
  - Tooltip appears after a short delay and shows the category description.

Interaction:

- Clicking a category toggles the trader's enabled state.
- Save immediately through a small endpoint, or save as a batch if the UI already has a suitable pattern.
- Optimistic UI is acceptable only if rollback on failure is clear.

If the feature is disabled, render nothing for traders. This keeps the product behaving as if the feature does not exist.

## Backend Filtering

The filtering belongs in the payment detail search path, not only in UI:

`OrderPoolingService` -> `OrderPoolingJob` -> `OrderService::create` -> `OrderDetailAssigner::assign` -> `OrderDetailProvider::provide` -> `FindAvailablePaymentDetail::queryPaymentDetails()`

Filtering algorithm:

1. Resolve the order merchant.
2. Read the global feature flag from `SettingService`.
3. If disabled, do not add category filters.
4. Load merchant category IDs.
5. If the merchant has no categories, do not add category filters.
6. If the merchant has categories, restrict eligible payment detail owners to traders who have at least one matching enabled category.
7. Continue applying the existing filters: online state, traffic pause, gateway, limits, method, and other eligibility rules.

The logic must be applied before a payment detail is selected. UI controls are not security or business enforcement.

## Default Application

### Creating A Category

When admin creates a category:

- Store the category.
- If `enabled_by_default` is true, attach or enable the category for all existing traders.
- If false, do not enable it for existing traders.

### Creating A Trader

When a new trader/user meta is created:

- Enable all categories where `enabled_by_default = true`.
- Leave other categories disabled.

This can live in the user/trader creation flow, a service, or an observer if the project already uses that style.

### Editing Default Flag

Editing `enabled_by_default` affects only future traders. Existing trader choices are not changed automatically.

The admin UI must say this clearly and provide the separate `Применить ко всем трейдерам` button for mass changes.

### Applying To All Traders

The explicit mass action uses the current category default flag:

- If `enabled_by_default = true`, enable this category for all traders.
- If `enabled_by_default = false`, disable this category for all traders.

Use a transaction or chunked update strategy so the operation is reliable on a large trader base.

## Deletion Behavior

Deleting a category should:

- Detach it from merchants.
- Remove or disable it for traders.
- Leave all other category assignments intact.
- Make any merchant with no remaining categories unrestricted by category filtering.

Use a confirmation modal with simple copy:

> Категория удалится у мерчантов и трейдеров. Если у мерчанта не останется категорий, его заявки снова будут доступны всем трейдерам.

## Failure And Safety

The feature has two different safety modes:

- Feature disabled intentionally: ignore categories and behave like the old system.
- Feature broken unexpectedly: do not silently bypass restrictions for categorized merchants; log the error and fail the assignment path rather than sending restricted traffic incorrectly.

This distinction is important. "Disabled" is a normal business state. "Broken" is an operational incident.

## Implementation Plan

1. Confirm the current category schema and whether `description` and `enabled_by_default` already exist.
2. Add or adjust migrations for required category fields and optional normalized trader/category pivot.
3. Add the `merchant_traffic_categories_enabled` setting via `SettingService`; install it with the project settings command.
4. Build backend category services:
   - category CRUD
   - merchant assignment
   - trader toggle update
   - apply-to-all-traders action
   - new-trader default initialization
5. Expose admin endpoints for the category modal and merchant assignment modal.
6. Expose trader endpoints for reading available categories and toggling enabled categories.
7. Add backend filtering in `FindAvailablePaymentDetail::queryPaymentDetails()`.
8. Add admin UI:
   - `Категории` button
   - category management modal
   - global toggle
   - category badges column
   - merchant category assignment modal
9. Add trader UI:
   - compact category row above the orders table
   - delayed tooltips
   - clear enabled/disabled states
10. Add clear explanatory copy in every risky place:
    - global disabled state
    - no categories on merchant
    - default flag only affects new traders
    - apply-to-all behavior
11. Manually verify main flows in browser.
12. Add and run tests only if explicitly requested later, according to project rules.

## Open Technical Decisions

- Whether to replace `user_metas.allowed_categories` with a normalized pivot immediately or keep both during transition.
- Exact permission gate for the admin category UI if there are multiple admin levels.
- Whether category creation/update should reuse existing `Admin\CategoryController` pages or build a new modal-first API for the merchants page.
- Whether trader toggles save immediately per click or through an explicit save button.

## See Also

- [Telegram Chat Dispute Automation Plan](../telegram/telegram-chat-dispute-automation-plan.md)
