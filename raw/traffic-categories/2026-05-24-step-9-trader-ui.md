# Step 9 — Trader UI (Orders Page)

> Source: repository implementation (Cursor session)
> Collected: 2026-05-24
> Published: 2026-05-24

## Summary

Trader-facing traffic category controls on the orders list page (`resources/js/Pages/Order/Index.vue`), wired to step 6 JSON API.

## Files added or changed

| File | Role |
|------|------|
| `resources/js/composables/useTraderTrafficCategories.js` | `GET traffic-categories.index`, `PATCH traffic-categories.enabled.update` |
| `resources/js/Components/Order/TraderTrafficCategoriesRow.vue` | Compact row above filters in `MainTableSection` `#header` slot |
| `resources/js/Pages/Order/Index.vue` | Renders row when `viewStore.isTraderViewMode` |
| `resources/js/Components/AppTooltip.vue` | Optional `showDelayMs` prop (400 ms on category buttons) |

## UX behavior

- Row mounts only for trader view on orders index.
- On mount: fetch categories; hide entire block when global flag off or category list empty.
- Title: «Категории трафика»; spec explanation text under title.
- Category chips: `btn-primary` when enabled, muted `btn-ghost` when disabled; info icon + dotted underline on name.
- Tooltip: category `description` via `AppTooltip`, 400 ms delay.
- Click toggles `enabled`; optimistic update with rollback on API error; `AlertError` for failure message.
- Per-category loading spinner while PATCH in flight.

## API routes used

- `traffic-categories.index`
- `traffic-categories.enabled.update` — body `{ "enabled": true|false }`

## Not in scope (still pending)

- Steps 10–11: copy polish across all flows, browser verification
- Step 12: automated tests (deferred)
