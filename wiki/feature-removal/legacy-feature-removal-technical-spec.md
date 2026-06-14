# Legacy Feature Removal Technical Specification

> Sources: User conversation, 2026-06-15; repository exploration, 2026-06-15; focused repository exploration for Rapira/API/roles/trader features, 2026-06-15; existing wiki article [Merchant Traffic Categories Architecture](../traffic-categories/merchant-traffic-categories-architecture.md)
> Raw: [Legacy Feature Removal Requirements](../../raw/feature-removal/2026-06-15-legacy-feature-removal-requirements.md)
> Updated: 2026-06-15

## Overview

This specification describes a safe, phased removal of legacy product surfaces from the Laravel 11 + Vue 3 / Inertia application. The goal is not to hide UI only, but to remove each feature end-to-end: menus, pages, modals, routes, controllers, requests, resources, services, jobs/listeners, settings, enum cases, role checks, permissions, data columns, pivots, and stale API documentation.

Every removal step must be implemented as a small isolated change. Before deleting code in each step, run a targeted repository search for route names, component names, model fields, enum values, translations, settings keys, jobs, listeners, resources, factories, seeders, migrations, and API documentation snippets. When a database column/table still contains production data, prefer a two-stage removal: first stop all reads/writes and deploy; then drop schema in a later migration after backup and monitoring.

## Global Safety Rules

- Do not remove shared infrastructure just because one feature uses it. For example, Team Leader flows, permanent VIP, H2H API, merchant API tokens, wallet history, payout core flow, and trader payout taking must remain functional unless a step explicitly says otherwise.
- Treat financial features as data migrations, not UI cleanup. Agent commissions/balances, VIP limits, payout priority windows, Rapira market history, and API idempotency fields need production inventory before code deletion.
- Remove frontend entry points and backend authorization/routes together. A hidden button with a live endpoint is incomplete; a deleted endpoint with an active menu link is a regression.
- After route changes, run `php artisan optimize` and regenerate Ziggy routes with `php artisan ziggy:generate resources/js/ziggy-routes.js`.
- After PHP changes, run Pint on dirty PHP files. Automated tests should be added or run only when explicitly requested by the user.
- For settings removed from `SettingsService`, delete getter/updater methods, interface declarations, UI controls, and install defaults together. Existing rows in the settings table may be left inert in the first deployment if deleting them is risky.
- For roles removed from the product, delete role-based redirects, menus, route groups, view modes, role creation options, and all role checks. Existing users with removed roles must be migrated or blocked by an explicit data plan before route removal reaches production.

## Implementation Steps

### Step 1 — Remove Analyst Account And Analyst Functionality

#### Frontend Removal

- Delete the Analyst menu and view-mode surfaces: `resources/js/Layouts/Partials/AnalystMenu.vue`, Analyst branch in `resources/js/store/view.js`, Analyst option in `resources/js/Layouts/Partials/ViewModeSwitcher.vue`, and Analyst-specific redirect assumptions in common components such as `resources/js/composables/useConfirmAcceptOrder.js`.
- Remove Analyst pages under `resources/js/Pages/Analyst/**`, including user list, enabled cards, trader analytics, orders, deposits, disputes, payouts, and inherited wrappers.
- Remove Analyst news/menu links and any `viewStore.isAnalystViewMode` conditions in shared modals/tables.
- Remove Analyst from role selection UI and user management labels if it appears in admin create/edit flows.

#### Backend Removal

- Remove Analyst redirect from `App\Http\Controllers\AppHomeController`.
- Delete the `analyst.*` route group in `routes/web.php`, including analyst users, enabled cards, trader analytics, orders, manual control acquiring, deposits, disputes, payouts, and filters.
- Delete `app/Http/Controllers/Analyst/**` and any Analyst-only resources/requests if they are not shared.
- Remove `Analyst` from shared route middleware, gates, `HandleInertiaRequests`, filter helpers, manual-control checks, and receipt/bank-statement access where it only exists for Analyst access. Re-check gates/policies that allow Support/Admin/Trader access so dispute files remain accessible for valid roles.
- Prepare a production data decision for existing users with role `Analyst`: remove role assignment, archive users, or migrate them to Support/Super Admin by explicit business approval.

### Step 2 — Remove Automatic Temporary VIP, Keep Permanent VIP

#### Frontend Removal

- Remove temporary VIP progress and activation UI from trader main page props and components. Keep permanent VIP display/editing based on `is_vip`.
- Remove `/trader/temp-vip/activate` calls and any UI that references `temp_vip_active_until`, `temp_vip_can_activate`, `temp_vip_progress_start_at`, or `is_temp_vip_active`.
- In payment detail edit/create UI, keep VIP-limit behavior for permanent VIP only. Remove conditions that grant VIP behavior through temporary VIP, for example checks of `owner_is_temp_vip_active` and `currentUser.is_temp_vip_active`.
- Remove admin settings UI for temporary VIP required deals, duration, and enable/disable switch. Keep the user edit field for permanent `is_vip`.

#### Backend Removal

- Before code deletion, set `temp_vip_enabled = 0`, stop progress accumulation, and let active `temp_vip_active_until` windows expire or explicitly reset them.
- Remove `Trader\TempVipController`, route `trader.temp-vip.activate`, `TempVipExpireJob`, `UpdateTempVipProgressListener`, `UserTempVipActivation`, and the `OrderSucceeded` listener registration for temporary VIP progress.
- Remove temporary VIP methods and settings from `SettingsService` and `SettingsServiceContract`: required deals, duration minutes, enabled flag, and reset behavior.
- Remove `User::getTempVipProgressData()` and temporary VIP fields from `UserResource` and `PaymentDetailResource`. Keep `is_vip` and permanent VIP observer behavior.
- Keep the permanent VIP path: `users.is_vip` must remain editable and order/payment-detail limit logic must treat only `users.is_vip` as VIP.
- Before dropping temporary VIP columns/table, decide what to do with `payment_details.vip_min_order_amount_backup` and `vip_max_order_amount_backup`; these backups may need to be restored into visible min/max limits for permanent VIP users.

### Step 3 — Remove User Teams From Admin And User Pages

#### Frontend Removal

- Remove team management and assignment UI from `resources/js/Pages/User/Index.vue`: `UserTeamManageModal`, `UserChangeTeamModal`, team badges/columns, and actions that open team modals.
- Remove `resources/js/Modals/User/UserTeamManageModal.vue`, `resources/js/Modals/User/UserChangeTeamModal.vue`, and modal store entries related to `userTeamManage` / `userTeamChange`.
- Remove team display from user summary popovers/modals and role-specific user pages such as Analyst/Support pages if those pages still exist after Step 1.

#### Backend Removal

- Remove admin routes `admin.user-teams.*` and `admin.users.team.update`.
- Delete `Admin\UserTeamController`, `Admin\User\UpdateTeamRequest`, `UserTeam` model, and user-team resources/relationships if no other feature uses them.
- Remove `user_team_id` from `UserResource`, `Admin\UserController::show()` loads, user create/update DTO paths, and any eager-loads of `userTeam`.
- Add a migration to drop `users.user_team_id` and `user_teams` after a release where all code stops reading them.

### Step 4 — Remove Trader Economy Page And Admin Toggle

#### Frontend Removal

- Remove trader menu entry for route `trader.economy.index` and delete `resources/js/Pages/Economy/Trader/Index.vue`.
- Remove admin user edit/create controls for `trader_economy_enabled` from `UserEditModal.vue` and `UserCreateModal.vue`.
- Remove any Inertia props or badges that tell the frontend whether economy is enabled.

#### Backend Removal

- Remove trader economy routes `GET/POST/DELETE/PATCH /trader/economy*`.
- Delete `Trader\EconomyController`, `TraderEconomyMonth`, `TraderEconomyDay`, and related requests/resources if present.
- Remove `trader_economy_enabled` from `User`, `UserResource`, `UserCreateDTO`, `UserUpdateDTO`, admin store/update requests, and `UserService`.
- Add a migration to drop `users.trader_economy_enabled`, `trader_economy_months`, and `trader_economy_days` after confirming no reports rely on historical economy data.

### Step 5 — Remove Rapira As A Market Source

#### Frontend Removal

- Remove Rapira labels/help links from `resources/js/Pages/Currency/Index.vue` and merchant settings defaults such as `resources/js/Pages/Merchant/Tabs/Settings.vue`.
- Remove Rapira from any market dropdowns, badges, docs, or examples. Existing merchant settings UI must default RUB to the approved replacement market, not to `rapira`.

#### Backend Removal

- Remove `MarketEnum::RAPIRA`, `RapiraParser`, and the Rapira branch from `Market\Utils\Parser\Parser`.
- Update market support logic in `MarketService::supportedCurrenciesForMarket()` and any default geo map in `MerchantService` / migrations that currently point RUB to Rapira. ByBit is the safest existing automatic replacement for RUB; Manual is the controlled-rate alternative; Binance does not support RUB in the current parser.
- Add a data migration to rewrite active configuration values from `rapira` to the chosen replacement: merchant GEO JSON, `merchants.market`, and trader `user_metas.allowed_markets`.
- Do not blindly rewrite historical `orders.market`, `payouts.rate_market`, or `cascade_deals.market`. Either keep `rapira` as a deprecated readable enum/display value for history, or introduce a safe raw-string fallback such as `Rapira (архив)`.
- Re-check API request validation for merchant/order/payout geo markets so old `rapira` payloads fail with a clear validation error or are explicitly mapped during a compatibility window.
- After deployment, invalidate `conversion-price-for-rub-rapira` cache and warm the replacement RUB market through `app:update-p2p-prices`.

### Step 6 — Remove Payment Detail Statistics Page And Modal

#### Frontend Removal

- Delete the statistics page `resources/js/Pages/PaymentDetail/Statistics.vue` and remove admin menu links to `admin.payment-details.statistics`.
- Remove the volume-statistics modal entry from `PaymentDetail/Index.vue`, `PaymentDetailVolumeStatisticsModal.vue`, and modal store `openPaymentDetailVolumeStatisticsModal`.
- Remove frontend table buttons/links that open bank/payment-detail volume statistics.

#### Backend Removal

- Remove admin route `admin.payment-details.statistics` and volume-statistics routes if the modal is fully removed.
- Delete `PaymentDetailVolumeStatisticsController`, `PaymentDetailVolumeStatisticsService`, `VolumeStatisticsRequest`, and related request constants reused only by statistics.
- Remove service binding from `AppServiceProvider` and related statistics calls from `MerchantApiStatisticsService` if they become dead code.
- Keep core payment details, enabled cards, and order assignment untouched.

### Step 7 — Remove Trader Analytics Page

#### Frontend Removal

- Remove trader analytics menu entries from Admin, Support, and Analyst menus.
- Delete `resources/js/Pages/Admin/TraderAnalytics/**`, plus Support/Analyst wrapper pages that only re-export the admin page.
- Remove trader search/select components used only by trader analytics.

#### Backend Removal

- Remove routes `*.traders-analytics.*` for Admin, Support, and Analyst.
- Delete `Admin\TraderAnalyticsController`, Support/Analyst subclasses, related requests, and threshold update endpoints.
- Remove `trader_analytics_operation_thresholds` from `SettingsService` and `SettingsServiceContract` if no other feature uses it.
- Delete stale settings rows only after verifying they are not referenced by dashboards or jobs.

### Step 8 — Simplify Merchant API Integration Pages To Documentation Only

#### Frontend Removal

- In `resources/js/Pages/Integration/Index.vue`, remove tabs/components that execute API requests from the browser. Keep only documentation, API token regeneration, documentation download, and the demo payment page link.
- Delete interactive request components that are no longer allowed: `MerchantApi.vue`, `PayoutApi.vue`, `StatementApi.vue`, `WalletApi.vue`, `CommonApi.vue`, and shared `ApiResponse.vue` if it becomes unused.
- Keep `ApiDocumentation.vue`, H2H documentation, token display/regeneration, receipt-template download if still part of documentation, and demo payment page access.

#### Backend Removal

- Keep merchant integration page routes `integration.index`, `integration.v2`, `integration.regenerate-token`, and `integration.receipt-template` only if they serve the remaining docs/token/download/demo surface.
- Remove backend endpoints used solely by browser-based API request playgrounds. Do not remove actual external API endpoints yet unless covered by Step 9 or Step 10.
- Update generated/downloaded API documentation so it no longer advertises browser playground functionality.
- Re-check API logging pages and merchant API log analytics; they are separate admin/merchant observability surfaces and should not be removed by this step unless explicitly tied to the deleted playground.
- Keep the distinction between legacy merchant UI, legacy v1 API, API v2 code, and Integration Infrastructure API. The v2 code exists, but current `/api/v2/*` routes are not the active replacement unless they are explicitly enabled in routing.

### Step 9 — Remove Payment Form And Merchant API, Keep Host-To-Host API

#### Frontend Removal

- Remove public payment form UI and any merchant API documentation sections from the integration pages. Keep H2H API docs and demo payment page only if the demo is still required by product.
- Remove merchant API examples from downloadable documentation and UI tabs.
- Check public payment routes `/payment/{order:uuid}` and `/payment/demo` before deletion: if demo payment page must remain, keep demo routes/components isolated from real payment form flow.

#### Backend Removal

- Remove `api/merchant/order*` routes and `API\Merchant\OrderController` only after confirming all active merchants have moved to H2H or v2 payin. Keep `api/h2h/order*` routes and behavior.
- Remove `PaymentLinkController` routes for real public payment form if product no longer supports form-based payment. Keep `PaymentDemoController` only if demo page remains.
- Do not remove H2H backend while `InternalCascadeProvider` still simulates H2H through `H2HStoreRequest` and `OrderPoolingService`. Move cascade/internal creation to a direct domain service first if H2H API is ever targeted for deletion.
- Update order creation services so internal/cascade flows that simulate H2H are not broken. Per project rule, API v2 cascade payin remains under `/api/v2/payin`.
- Remove merchant API docs, request validation, resources, and callbacks only where they belong to the deprecated merchant API, not shared H2H resources.

### Step 10 — Remove Auto-Withdraw API Creation And Finance Columns

#### Frontend Removal

- On merchant/admin finance pages, remove `external ID` and `transaction hash` columns where they refer to auto-withdrawal or withdrawn deposit identifiers no longer exposed to users.
- On deposits pages, remove transaction and ID columns requested for deletion. Keep internal UUIDs in hidden routing/links only if required for row actions.
- Remove integration page examples for wallet withdraw creation.

#### Backend Removal

- Remove API route/controller path that creates withdrawals through API, currently `POST /api/wallet/withdraw`, after confirming webhook ingestion for external withdrawal providers remains separate.
- Remove validation/request classes for API auto-withdraw creation and any jobs/services only used by that endpoint.
- Keep payout APIs, deposit webhooks, withdrawal webhooks, and Integration Infrastructure wallet-transaction endpoints separate unless the business explicitly includes them. Auto-withdraw creation is not the same surface as webhook completion.
- Remove `external_id`, `tx_hash`, and `transaction_id` from API resources/UI payloads only where they belong to the deleted flow. For payouts, `external_id` may still be part of merchant payout idempotency and must be audited before schema removal.

### Step 11 — Remove Merchant Traffic Categories

#### Frontend Removal

- Remove admin category manager/assignment UI from merchant pages: `MerchantTrafficCategoryManagerModal.vue`, `MerchantTrafficCategoriesAssignModal.vue`, `useMerchantTrafficCategories.js`, merchant category badges/actions, and modal store entries.
- Remove trader category row from orders: `TraderTrafficCategoriesRow.vue`, `useTraderTrafficCategories.js`, and the `Order/Index.vue` header insertion.
- Remove legacy category settings in merchant/user forms if they no longer serve another business concept.

#### Backend Removal

- First set `merchant_traffic_categories_enabled = 0` and unassign categories from merchants so `FindAvailablePaymentDetail` returns to the old unrestricted behavior before code removal.
- Remove routes `admin.traffic-categories.*`, `admin.merchants.categories.update`, and trader `traffic-categories.*`.
- Delete `MerchantTrafficCategoryService`, its contract, DTO, request classes, controllers, resources, and service bindings.
- Remove category filtering from `FindAvailablePaymentDetail` so traffic assignment returns to non-category behavior.
- Remove `merchant_traffic_categories_enabled` setting and `category_user`, `category_merchant`, `categories`, and `user_metas.allowed_categories` only after confirming no other feature uses generic categories.
- Treat the existing wiki article [Merchant Traffic Categories Architecture](../traffic-categories/merchant-traffic-categories-architecture.md) as historical context for what must be unwound.

### Step 12 — Remove User Notes

#### Frontend Removal

- Remove notes actions from user lists and wallet page: `UserNotesModal.vue`, `openUserNotesModal`, and all buttons labeled as user notes.
- Remove notes modal registration from `resources/js/store/modal.js`.
- Remove any admin-only note badges or summary displays.

#### Backend Removal

- Remove `Admin\UserNoteController`, generic `UserNoteController` if unused, `UserNoteResource`, `UserNote` model, and routes `admin.users.notes.*`.
- Remove `User::notes()` relationship and any eager-load/counts.
- Add a migration to drop the user notes table after confirming notes do not need to be exported for audit retention.

### Step 13 — Remove Dispute Requisite-Hide Eye

#### Frontend Removal

- On dispute pages/modals, remove the eye button/toggle that hides all requisites from display.
- Remove local state, computed values, and CSS branches that mask payment detail fields when the toggle is active.
- Keep normal sensitive-data formatting rules that are unrelated to this manual eye toggle.

#### Backend Removal

- Search for backend fields/resources that expose a `hidden`, `hide`, or `requisites visibility` flag specifically for this dispute UI behavior. Remove only if it is not used by API consumers or audit flows.
- Keep dispute receipt, bank statement, and payment detail authorization gates intact.
- Verify `DisputeResource` and `TableOrderResource` still expose requisites needed for valid roles after frontend toggle removal.

### Step 14 — Remove Priority Access To Payouts

#### Frontend Removal

- Remove admin payout settings for priority access from `Payout/Admin/Index.vue` and any settings modal/section that controls delay/release behavior.
- Remove trader payout priority-access badges, timers, disabled states, countdowns, and release timers from `Payout/Trader/Index.vue`.
- Remove user edit/create toggle `priority_payout_access_enabled` from `UserEditModal.vue`, `UserCreateModal.vue`, and user table badges.

#### Backend Removal

- Before code deletion, turn off payout priority access and release all currently active windows so open payouts do not remain artificially restricted.
- Remove `priority_access_until` behavior from payout selection/taking logic, admin release route `admin.payouts.priority-access.release`, and settings methods `get/updatePayoutPriorityAccessSettings`.
- Remove `priority_payout_access_enabled` from `User`, `UserResource`, admin requests, DTOs, and services.
- Add a migration to drop `payouts.priority_access_until` and `users.priority_payout_access_enabled` only after all assignment logic stops reading them.
- Verify normal payout taking remains fair and functional without priority gating.

### Step 15 — Remove Agent Role And Agent Functionality

#### Frontend Removal

- Remove Agent from view-mode switcher, `viewStore.isAgentViewMode`, Agent menu, Agent finances surfaces, and Agent balance card display in `Wallet/Index.vue` / `NavBar.vue`.
- Remove agent fields from user create/edit modals: `agent_id`, `agent_commission_percentage`, agent selectors, validation messages, and displayed commission info.
- Remove agent financial fields from order modals and wallet summaries if they become dead after backend removal.

#### Backend Removal

- Remove Agent redirect from `AppHomeController` and delete the `agent.*` route group.
- Remove `Agent` from middleware role lists such as news reactions/views once news is limited to Trader/Team Leader in Step 17.
- Remove agent relations and fields from `User`, `Order`, `WalletResource`, `UserResource`, DTOs, requests, `AgentCommission`, `BalanceType::AGENT`, agent wallet handlers, order listeners, and `OrderOperator` profit calculation.
- Create a data migration plan for existing `orders.agent_id`, agent balances, agent commissions, and users with Agent role. Financial history may need to stay readable even after future agent accrual is disabled.
- Treat this as a high-risk financial removal: first disable new agent assignment and new commission accrual, then settle/freeze existing `wallets.agent_balance`, then remove UI/routes, and only later drop schema.
- Do not remove third-party user-agent parsing (`jenssegers/agent`) or HTTP User-Agent logging; those are unrelated to the business Agent role.

### Step 16 — Remove Bank Icons From Payouts

#### Frontend Removal

- Remove bank/payment gateway icons from payout rows/cards. In `Payout/Trader/Index.vue`, keep text names only where useful and remove icon-related props/usages.
- Remove `GatewayLogo` usage in payout-specific UI when it represents banks that no longer exist.
- Keep generic `GatewayLogo` component if payment gateways elsewhere still use it.

#### Backend Removal

- Stop eager-loading or exposing logo paths for payout banks if those fields become payout-only dead payload.
- Do not remove `bank_name` from payout API/resources without a separate audit: current payout creation can still receive bank names and callbacks/resources may expose requisites.
- Verify trader payout cards remain readable with requisites text only.

### Step 17 — Restrict News To Trader And Team Leader Only

#### Frontend Removal

- Remove news menu links from Admin, Support, Analyst, Agent, Merchant, and Provider Liquidity layouts. Keep only Trader and Team Leader news entry points.
- Update shared `News/Index.vue` so admin-only create/delete controls are not visible unless the route remains intentionally admin-only for content management. If admins should no longer see news at all, move content management elsewhere before deleting admin access.
- Remove notification badges or dashboard blocks that surface news to removed roles.

#### Backend Removal

- Restrict news index routes to Trader and Team Leader only: keep `/news` for Trader and `leader.news.index` for Team Leader.
- Remove `agent.news.index`, `support.news.index`, `analyst.news.index`, and admin/merchant/provider news access if product confirms admins should not manage news.
- Narrow news view/reaction routes from `role:Trader|Support|Analyst|Team Leader|Agent|Super Admin` to `Trader|Team Leader|Super Admin` only if Super Admin is still needed for administration; otherwise `Trader|Team Leader`.
- Ensure news view tracking and reactions cannot be posted by roles that no longer see news.

## Cross-Feature Verification Checklist

- Fresh route list contains no removed route names and Ziggy output has no stale references.
- Login redirect for every remaining role lands on a valid page.
- Admin user create/edit works without removed role fields, team fields, temp VIP fields, economy toggle, priority payout toggle, or agent fields.
- Support flows still work after Analyst removal: users, orders, disputes, enabled cards, manual control acquiring, payouts, deposits, and trader analytics where retained.
- Trader order acceptance, payment detail management, payout taking, finances, disputes, and H2H API order creation still work.
- Merchant integration page still shows docs, token regeneration, documentation download, and demo payment page, with no browser API playground.
- H2H API, payout callbacks, deposit/withdraw webhooks, cascade payin, and merchant finance history still work where explicitly retained.
- No removed setting keys are read by runtime code.
- No removed enum value can be selected in UI or accepted in API validation.
- Database migrations for dropping columns/tables are separated from code removal when production data retention is uncertain.

## Open Decisions Before Implementation

- Replacement market for existing Rapira RUB merchants and historical rows.
- Data policy for existing Analyst and Agent users.
- Financial settlement policy for existing Agent balances and historical agent commissions.
- Whether admin should retain a hidden news management surface while news display is limited to Trader and Team Leader.
- Whether merchant API removal includes only `/api/merchant/order*` or also old documentation/logging/statistics tied to merchant API requests.
- Whether payout `external_id` remains required for merchant payout idempotency after auto-withdraw removal.
- Retention/export requirements for user notes, economy records, agent commissions, and temporary VIP activation history.

## See Also

- [Merchant Traffic Categories Architecture](../traffic-categories/merchant-traffic-categories-architecture.md)
- [Trader Balance Transfer Implementation Plan](../trader-balance-transfers/trader-balance-transfer-implementation-plan.md)
- [Team Leader Shared Insurance Mode Specification](../team-leader-insurance-mode/team-leader-shared-insurance-mode-spec.md)
