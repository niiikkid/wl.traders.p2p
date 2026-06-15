# Legacy Feature Removal Technical Specification

> Sources: User conversation, 2026-06-15; repository exploration, 2026-06-15; focused repository exploration for Rapira/API/roles/trader features, 2026-06-15; existing wiki article [Merchant Traffic Categories Architecture](../traffic-categories/merchant-traffic-categories-architecture.md)
> Raw: [Legacy Feature Removal Requirements](../../raw/feature-removal/2026-06-15-legacy-feature-removal-requirements.md); [Payment Demo And Domain Split Removal](../../raw/feature-removal/2026-06-15-payment-demo-and-domain-split-removal.md); [Additional Legacy Feature Removal Requirements](../../raw/feature-removal/2026-06-15-additional-legacy-feature-removal-requirements.md); [Feature Removal Specification Depth Feedback](../../raw/feature-removal/2026-06-15-feature-removal-spec-depth-feedback.md)
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

Steps 1–18 are already implemented and should be treated as removed legacy functionality. Steps 19–27 are the new removal backlog and must be implemented separately after this specification. Each new step below is mapped to concrete frontend, backend, data, and verification surfaces found in the repository.

### Step 1 — Remove Analyst Account And Analyst Functionality (**Shipped**)

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

### Step 2 — Remove Automatic Temporary VIP, Keep Permanent VIP (**Shipped**)

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

### Step 3 — Remove User Teams From Admin And User Pages (**Shipped**)

#### Frontend Removal

- Remove team management and assignment UI from `resources/js/Pages/User/Index.vue`: `UserTeamManageModal`, `UserChangeTeamModal`, team badges/columns, and actions that open team modals.
- Remove `resources/js/Modals/User/UserTeamManageModal.vue`, `resources/js/Modals/User/UserChangeTeamModal.vue`, and modal store entries related to `userTeamManage` / `userTeamChange`.
- Remove team display from user summary popovers/modals and role-specific user pages such as Analyst/Support pages if those pages still exist after Step 1.

#### Backend Removal

- Remove admin routes `admin.user-teams.*` and `admin.users.team.update`.
- Delete `Admin\UserTeamController`, `Admin\User\UpdateTeamRequest`, `UserTeam` model, and user-team resources/relationships if no other feature uses them.
- Remove `user_team_id` from `UserResource`, `Admin\UserController::show()` loads, user create/update DTO paths, and any eager-loads of `userTeam`.
- Add a migration to drop `users.user_team_id` and `user_teams` after a release where all code stops reading them.

### Step 4 — Remove Trader Economy Page And Admin Toggle (**Shipped**)

#### Frontend Removal

- Remove trader menu entry for route `trader.economy.index` and delete `resources/js/Pages/Economy/Trader/Index.vue`.
- Remove admin user edit/create controls for `trader_economy_enabled` from `UserEditModal.vue` and `UserCreateModal.vue`.
- Remove any Inertia props or badges that tell the frontend whether economy is enabled.

#### Backend Removal

- Remove trader economy routes `GET/POST/DELETE/PATCH /trader/economy*`.
- Delete `Trader\EconomyController`, `TraderEconomyMonth`, `TraderEconomyDay`, and related requests/resources if present.
- Remove `trader_economy_enabled` from `User`, `UserResource`, `UserCreateDTO`, `UserUpdateDTO`, admin store/update requests, and `UserService`.
- Add a migration to drop `users.trader_economy_enabled`, `trader_economy_months`, and `trader_economy_days` after confirming no reports rely on historical economy data.

### Step 5 — Remove Rapira As A Market Source (**Shipped**)

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

### Step 6 — Remove Payment Detail Statistics Page And Modal (**Shipped**)

#### Frontend Removal

- Delete the statistics page `resources/js/Pages/PaymentDetail/Statistics.vue` and remove admin menu links to `admin.payment-details.statistics`.
- Remove the volume-statistics modal entry from `PaymentDetail/Index.vue`, `PaymentDetailVolumeStatisticsModal.vue`, and modal store `openPaymentDetailVolumeStatisticsModal`.
- Remove frontend table buttons/links that open bank/payment-detail volume statistics.

#### Backend Removal

- Remove admin route `admin.payment-details.statistics` and volume-statistics routes if the modal is fully removed.
- Delete `PaymentDetailVolumeStatisticsController`, `PaymentDetailVolumeStatisticsService`, `VolumeStatisticsRequest`, and related request constants reused only by statistics.
- Remove service binding from `AppServiceProvider` and related statistics calls from `MerchantApiStatisticsService` if they become dead code.
- Keep core payment details, enabled cards, and order assignment untouched.

### Step 7 — Remove Trader Analytics Page (**Shipped**)

#### Frontend Removal

- Remove trader analytics menu entries from Admin, Support, and Analyst menus.
- Delete `resources/js/Pages/Admin/TraderAnalytics/**`, plus Support/Analyst wrapper pages that only re-export the admin page.
- Remove trader search/select components used only by trader analytics.

#### Backend Removal

- Remove routes `*.traders-analytics.*` for Admin, Support, and Analyst.
- Delete `Admin\TraderAnalyticsController`, Support/Analyst subclasses, related requests, and threshold update endpoints.
- Remove `trader_analytics_operation_thresholds` from `SettingsService` and `SettingsServiceContract` if no other feature uses it.
- Delete stale settings rows only after verifying they are not referenced by dashboards or jobs.

### Step 8 — Simplify Merchant API Integration Pages To Documentation Only (**Shipped**)

#### Frontend Removal

- In `resources/js/Pages/Integration/Index.vue`, remove tabs/components that execute API requests from the browser. Keep only documentation, API token regeneration, and documentation download.
- Delete interactive request components that are no longer allowed: `MerchantApi.vue`, `PayoutApi.vue`, `StatementApi.vue`, `WalletApi.vue`, `CommonApi.vue`, and shared `ApiResponse.vue` if it becomes unused.
- Keep `ApiDocumentation.vue`, H2H documentation, token display/regeneration, and receipt-template download if still part of documentation. Demo payment page link removed in Step 18.

#### Backend Removal

- Keep merchant integration page routes `integration.index`, `integration.v2`, `integration.regenerate-token`, and `integration.receipt-template` only if they serve the remaining docs/token/download surface.
- Remove backend endpoints used solely by browser-based API request playgrounds. Do not remove actual external API endpoints yet unless covered by Step 9 or Step 10.
- Update generated/downloaded API documentation so it no longer advertises browser playground functionality.
- Re-check API logging pages and merchant API log analytics; they are separate admin/merchant observability surfaces and should not be removed by this step unless explicitly tied to the deleted playground.
- Keep the distinction between legacy merchant UI, legacy v1 API, API v2 code, and Integration Infrastructure API. The v2 code exists, but current `/api/v2/*` routes are not the active replacement unless they are explicitly enabled in routing.

### Step 9 — Remove Payment Form And Merchant API, Keep Host-To-Host API (**Shipped**)

#### Frontend Removal

- Remove public payment form UI and any merchant API documentation sections from the integration pages. Keep H2H API docs only.
- Remove merchant API examples from downloadable documentation and UI tabs.
- Public route `/payment/{order:uuid}` and `PaymentLinkController` were removed earlier. Demo page `/payment/demo` removed in Step 18.

#### Backend Removal

- Remove `api/merchant/order*` routes and `API\Merchant\OrderController` only after confirming all active merchants have moved to H2H or v2 payin. Keep `api/h2h/order*` routes and behavior.
- Remove `PaymentLinkController` routes for real public payment form if product no longer supports form-based payment. `PaymentDemoController` removed in Step 18.
- Do not remove H2H backend while `InternalCascadeProvider` still simulates H2H through `H2HStoreRequest` and `OrderPoolingService`. Move cascade/internal creation to a direct domain service first if H2H API is ever targeted for deletion.
- Update order creation services so internal/cascade flows that simulate H2H are not broken. Per project rule, API v2 cascade payin remains under `/api/v2/payin`.
- Remove merchant API docs, request validation, resources, and callbacks only where they belong to the deprecated merchant API, not shared H2H resources.

### Step 10 — Remove Auto-Withdraw API Creation And Finance Columns (**Shipped**)

#### Frontend Removal

- On merchant/admin finance pages, remove `external ID` and `transaction hash` columns where they refer to auto-withdrawal or withdrawn deposit identifiers no longer exposed to users.
- On deposits pages, remove transaction and ID columns requested for deletion. Keep internal UUIDs in hidden routing/links only if required for row actions.
- Remove integration page examples for wallet withdraw creation.

#### Backend Removal

- Remove API route/controller path that creates withdrawals through API, currently `POST /api/wallet/withdraw`, after confirming webhook ingestion for external withdrawal providers remains separate.
- Remove validation/request classes for API auto-withdraw creation and any jobs/services only used by that endpoint.
- Keep payout APIs, deposit webhooks, withdrawal webhooks, and Integration Infrastructure wallet-transaction endpoints separate unless the business explicitly includes them. Auto-withdraw creation is not the same surface as webhook completion.
- Remove `external_id`, `tx_hash`, and `transaction_id` from API resources/UI payloads only where they belong to the deleted flow. For payouts, `external_id` may still be part of merchant payout idempotency and must be audited before schema removal.

### Step 11 — Remove Merchant Traffic Categories (**Shipped**)

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

### Step 12 — Remove User Notes (**Shipped**)

#### Frontend Removal

- Remove notes actions from user lists and wallet page: `UserNotesModal.vue`, `openUserNotesModal`, and all buttons labeled as user notes.
- Remove notes modal registration from `resources/js/store/modal.js`.
- Remove any admin-only note badges or summary displays.

#### Backend Removal

- Remove `Admin\UserNoteController`, generic `UserNoteController` if unused, `UserNoteResource`, `UserNote` model, and routes `admin.users.notes.*`.
- Remove `User::notes()` relationship and any eager-load/counts.
- Add a migration to drop the user notes table after confirming notes do not need to be exported for audit retention.

### Step 13 — Remove Dispute Requisite-Hide Eye (**Shipped**)

#### Frontend Removal

- On dispute pages/modals, remove the eye button/toggle that hides all requisites from display.
- Remove local state, computed values, and CSS branches that mask payment detail fields when the toggle is active.
- Keep normal sensitive-data formatting rules that are unrelated to this manual eye toggle.

#### Backend Removal

- Search for backend fields/resources that expose a `hidden`, `hide`, or `requisites visibility` flag specifically for this dispute UI behavior. Remove only if it is not used by API consumers or audit flows.
- Keep dispute receipt, bank statement, and payment detail authorization gates intact.
- Verify `DisputeResource` and `TableOrderResource` still expose requisites needed for valid roles after frontend toggle removal.

### Step 14 — Remove Priority Access To Payouts (**Shipped**)

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

### Step 15 — Remove Agent Role And Agent Functionality (**Shipped**)

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

### Step 16 — Remove Bank Icons From Payouts (**Shipped**)

#### Frontend Removal

- Remove bank/payment gateway icons from payout rows/cards. In `Payout/Trader/Index.vue`, keep text names only where useful and remove icon-related props/usages.
- Remove `GatewayLogo` usage in payout-specific UI when it represents banks that no longer exist.
- Keep generic `GatewayLogo` component if payment gateways elsewhere still use it.

#### Backend Removal

- Stop eager-loading or exposing logo paths for payout banks if those fields become payout-only dead payload.
- Do not remove `bank_name` from payout API/resources without a separate audit: current payout creation can still receive bank names and callbacks/resources may expose requisites.
- Verify trader payout cards remain readable with requisites text only.

### Step 17 — Restrict News To Trader And Team Leader Only (**Shipped**)

#### Frontend Removal

- Remove news menu links from Admin, Support, Analyst, Agent, Merchant, and Provider Liquidity layouts. Keep only Trader and Team Leader news entry points.
- Update shared `News/Index.vue` so admin-only create/delete controls are not visible unless the route remains intentionally admin-only for content management. If admins should no longer see news at all, move content management elsewhere before deleting admin access.
- Remove notification badges or dashboard blocks that surface news to removed roles.

#### Backend Removal

- Restrict news index routes to Trader and Team Leader only: keep `/news` for Trader and `leader.news.index` for Team Leader.
- Remove `agent.news.index`, `support.news.index`, `analyst.news.index`, and admin/merchant/provider news access if product confirms admins should not manage news.
- Narrow news view/reaction routes from `role:Trader|Support|Analyst|Team Leader|Agent|Super Admin` to `Trader|Team Leader|Super Admin` only if Super Admin is still needed for administration; otherwise `Trader|Team Leader`.
- Ensure news view tracking and reactions cannot be posted by roles that no longer see news.

### Step 18 — Remove Demo Payment Page And Payment Domain Split (**Shipped**)

#### Context

The real public hosted payment form (`/payment/{order:uuid}`, `PaymentLinkController`) was already gone. What remained was an isolated demo at `/payment/demo` (`PaymentDemoController` → `PaymentLink/Index.vue`) and split-domain config (`PAYMENT_FORM_URL`, `config/domains.php`, `EnsurePaymentDomain`, `EnsureBackofficeDomain`). Product accepts payins via H2H API only; merchants render requisites on their own sites.

#### Frontend Removal (**done**)

- Deleted `resources/js/Pages/PaymentLink/**` (stages, `DemoSwitcher`, headers, modals).
- Deleted `resources/js/Layouts/PaymentLayout.vue`.
- Removed demo card «Демонстрационная платежная форма» and link to `payment.demo.show` from `resources/js/Pages/Integration/Index.vue`.

#### Backend Removal (**done**)

- Deleted `app/Http/Controllers/PaymentDemoController.php`.
- Removed routes `GET /payment/demo`, `POST /payment/demo/dispute`, `POST /payment/demo/payment-detail/{paymentGateway}` and names `payment.demo.*`.
- Deleted `app/Http/Middleware/EnsurePaymentDomain.php` and `app/Http/Middleware/EnsureBackofficeDomain.php`.
- Deleted `config/domains.php`.
- Removed middleware aliases `payment.domain` and `backoffice.domain` from `bootstrap/app.php`.
- Stripped `backoffice.domain` from all routes in `routes/web.php` and `routes/auth.php` (safe: middleware only restricted backoffice when payment host differed from `APP_URL`).

#### Configuration Removal (**done**)

- Removed `PAYMENT_FORM_URL` and `PAYMENT_LEGACY_REDIRECT_STATUS` from `.env.example` and `.env`.

#### Post-deploy (**done**)

- `php artisan optimize`
- `php artisan ziggy:generate resources/js/ziggy-routes.js`
- `vendor/bin/pint --dirty --format agent`

#### Not affected

- H2H API (`api/h2h/order*`) and cascade payin `/api/v2/payin`.
- `payment_url` in trader/leader deposit invoices (external provider redirect).
- Backoffice `Payment/Index` (trader payments list).
- `POST /payment/{order}/callback/resend` (merchant callback resend; not a public payment page).

#### Safety

- No DB migration required.
- No runtime code referenced `env('PAYMENT_FORM_URL')` after `config/domains.php` removal.
- `backoffice.domain` was effectively disabled when payment host matched `APP_URL` (default).

### Step 19 — Remove Per-Bank Payout Availability Toggle

#### Context

Admin bank/payment-method settings currently contain a separate per-gateway payout availability flag. The runtime field is `payment_gateways.is_payouts_enabled`; it appears in the single gateway modal, bulk gateway settings, resources, validation, and payout pooling. This step removes the ability to configure payout availability per bank/payment gateway, not the entire payout product.

#### Frontend Removal

- Remove `is_payouts_enabled` from `resources/js/Modals/PaymentGateway/PaymentGatewayModal.vue`:
  - initial `form` defaults, `resetFormForCreate()`, `resetFormForEdit()`;
  - `loadEditData()` hydration from `paymentGateway.is_payouts_enabled`;
  - `FormData` submit append;
  - the UI toggle near the bottom of the modal.
- Remove `is_payouts_enabled` from `resources/js/Modals/PaymentGateway/PaymentGatewayBulkSettingsModal.vue`:
  - form defaults and `apply.is_payouts_enabled`;
  - bulk checkbox/toggle section;
  - submit payload and validation error display.
- Search payment gateway index/table UI for payout-enabled badges, filters, or columns and remove any display that claims payouts can be enabled/disabled per bank.
- Keep `is_active` and order/payment detail availability controls. Do not conflate general bank activity with payout-specific availability.

#### Backend Removal

- Remove `is_payouts_enabled` from:
  - `app/Http/Requests/Admin/PaymentGateway/StoreRequest.php` and `UpdateRequest.php` validation and attributes;
  - `app/Http/Requests/Admin/PaymentGateway/BulkSettingsRequest.php` validation;
  - `app/Http/Controllers/Admin/PaymentGatewayController.php::bulkUpdate()` allowed fields;
  - `app/Http/Resources/PaymentGatewayResource.php`;
  - `app/Models/PaymentGateway.php` PHPDoc, `$fillable`, and casts.
- Audit payout runtime before removing the DB column. Known read: `app/Jobs/PayoutPoolingJob.php` filters by `where('is_payouts_enabled', true)`. Replace it with the approved post-removal rule for payout method selection. Safe options are:
  - use active payment gateways without a payout-specific toggle;
  - or use explicit payout request `payment_gateway_code`/currency constraints if already present;
  - do not silently drop the filter until business confirms payouts should use every active bank.
- Re-check API payout validation in `app/Http/Requests/API/Payout/StoreRequest.php` and `app/Http/Requests/API/V2/Payout/StoreRequest.php` if they validate gateway codes or expose payment methods.
- Add a later migration to drop `payment_gateways.is_payouts_enabled` only after all code stops reading it. Keep historical payouts untouched.

#### Verification

- Admin can create/edit/bulk edit payment gateways without payout availability fields.
- Payout pooling still finds valid payout payment methods according to the new rule.
- Payout creation, trader payout taking, payout callbacks, and payout admin list still work.
- `php artisan route:list` and Ziggy contain no stale bulk/settings routes or props for the removed UI.

### Step 20 — Remove Bank Notification Sender Settings

#### Context

Bank settings also store notification sender aliases in `payment_gateways.sms_senders`. They are edited in the admin payment gateway modal and used outside that modal by sender stop-list helpers and test-data generation. This step removes bank-level sender configuration; it does not remove the SMS/push automation pipeline itself.

#### Frontend Removal

- Remove the “Отправители уведомлений” block from `resources/js/Modals/PaymentGateway/PaymentGatewayModal.vue`:
  - `sms_sender` state;
  - `form.sms_senders` defaults and edit hydration;
  - `addSender()` / `removeSender()`;
  - `FormData` `sms_senders[]` append;
  - sender input, helper text, chips, and errors.
- Search admin payment gateway index/detail/bulk pages for sender display. Remove any bank-level sender management entry point.

#### Backend Removal

- Remove `sms_senders` from:
  - `StoreRequest`, `UpdateRequest`, attributes and validation;
  - `PaymentGatewayController::store()` / `update()` defaults;
  - `PaymentGatewayResource`;
  - `PaymentGateway` PHPDoc, `$fillable`, casts.
- Audit and update `app/Http/Controllers/Admin/SenderStopListController.php`. It currently reads `$paymentGateway->sms_senders` to offer sender values. After removal, it must either:
  - accept manually entered sender strings;
  - use senders discovered from SMS logs;
  - or be removed if sender stop-list management itself becomes invalid.
- Update `app/Console/Commands/GenerateTestDataCommand.php` paths that choose random `sms_senders` from payment gateways. Replace with fixed synthetic sender names or remove that test-data branch if no longer meaningful.
- Search SMS/push parsing services for `sms_senders` and bank matching. If any production parser depends on gateway sender aliases, migrate that logic before dropping the column.
- Drop `payment_gateways.sms_senders` later, after sender stop-list/test-data/runtime parsing references are removed.

#### Verification

- Admin payment gateway create/edit works without `sms_senders`.
- Sender stop-list page/API still works or is explicitly removed by a separate step.
- SMS ingestion, shadow SMS logs, order matching, and push/SMS automation do not throw missing attribute errors.

### Step 21 — Remove NSPK Payment Detail Type

#### Context

`nspk` is a `DetailType` enum case and link-style requisite type. It shares rendering/validation patterns with `e-com`, but only NSPK must be removed. Existing `e-com` behavior must stay intact.

#### Frontend Removal

- Remove NSPK from `resources/js/Modals/PaymentDetail/PaymentDetailCreateModal.vue`:
  - `details` default keys;
  - detail type labels map;
  - reset/default payload;
  - NSPK-only input block (`selectedDetailType === 'nspk'`);
  - label text “Ссылка NSPK/SBP”.
- Remove NSPK from shared display helpers:
  - `resources/js/Components/PaymentDetail.vue`: stop treating `nspk` as a rendered link and keep `e-com` link display;
  - `resources/js/utils/paymentDetail.js`;
  - `resources/js/utils/paymentDetailHints.js`.
- Remove NSPK from integration docs/examples:
  - `resources/js/Pages/Integration/Components/ApiDocumentation.vue` examples and detail type descriptions;
  - `resources/js/Pages/Integration/V2.vue` only if it still documents NSPK or payin methods that map to NSPK.
- Remove NSPK labels from filters/dropdowns wherever `DetailType::values()` output is manually overridden, especially admin filter text.

#### Backend Removal

- Remove `case NSPK = 'nspk'` from `app/Enums/DetailType.php` only after data cleanup confirms no active or historical runtime row will be cast through the enum.
- Remove `nspk` translation from `lang/ru/detail-type.php` and admin filter override in `app/Http/Controllers/Admin/FilterController.php`.
- Update `app/Http/Requests/PaymentDetail/StoreRequest.php` and any update request that treats `[DetailType::NSPK, DetailType::E_COM]` as URL-like. Preserve `DetailType::E_COM` URL validation.
- Audit H2H/API order validation, payment gateway `detail_types` validation, resources, seeders, factories, and docs for `nspk`.
- Data plan before enum removal:
  - find `payment_details.detail_type = 'nspk'`;
  - find `payment_gateways.detail_types` JSON arrays containing `nspk`;
  - disable/migrate/archive active NSPK requisites before deploying code that cannot cast the enum;
  - keep historical migrations such as `nspk_schema` unchanged unless active code reads the column name.

#### Verification

- Payment detail create/edit lists no NSPK option.
- E-COM link requisites still render and validate.
- API docs no longer advertise `nspk`.
- No runtime enum cast fails on existing DB rows.

### Step 22 — Remove Editable Payout Commission Settings

#### Context

There are two payout commission surfaces:

- Global/fallback payout currency settings in `PayoutSettingsModal.vue` and `SettingsService::normalizePayoutCurrencySettings()` (`total_commission_rate`, `trader_commission_rate`, `reservation_time_for_payouts`).
- Payment-gateway-specific payout commissions in `PaymentGatewayModal.vue`, `PaymentGatewayBulkSettingsModal.vue`, `payment_gateways.trader_commission_rate_for_payouts`, and `payment_gateways.total_service_commission_rate_for_payouts`.

The user request targets “настройки выплат комиссии” and mentions unified wording “если платёжный метод не выбран”. Safe interpretation: remove editable global fallback commission fields first, keep method-specific payout commissions unless business confirms they must also be deleted.

#### Frontend Removal

- In `resources/js/Modals/Payout/PayoutSettingsModal.vue`:
  - remove `total_commission_rate` and `trader_commission_rate` from `setDefaults()` form state;
  - remove the two NumberInput fields from each currency card;
  - keep `reservation_time_for_payouts` if payout time remains configurable;
  - keep or replace helper text with unified text: `Комиссии применяются только если платёжный метод не выбран.` only where fallback commission information remains useful.
- Do not remove `PaymentGatewayModal.vue` payout commission fields in this step unless product explicitly decides method-specific payout rates are also obsolete. If removed, that becomes a second-stage change touching `payment_gateways` columns and `PayoutService` rate resolution.
- Audit `PaymentGatewayBulkSettingsModal.vue` for payout commission bulk fields. If method-specific payout commissions are retained, leave them; if not, remove both single and bulk gateway surfaces together.

#### Backend Removal

- Remove global fallback commission settings from:
  - `app/Http/Requests/Admin/Payout/UpdateCurrencySettingsRequest.php`;
  - `app/Services/Settings/SettingsService.php::normalizePayoutCurrencySettings()`;
  - `SettingsService::defaultPayoutCurrencySettings()`;
  - settings service contract methods if any expose payout currency settings shape;
  - installation/default settings if `app:install-settings` persists them.
- Audit `app/Services/Payout/PayoutService.php`. Known behavior uses payment gateway payout rates when a payment method is selected and falls back to settings when not selected. Preserve this invariant explicitly:
  - selected payment method → use gateway payout commission rates;
  - no payment method → use approved fallback rule or a fixed default defined in code/config;
  - do not create payouts with null commission rates.
- Do not alter financial history columns on `payouts` (`total_commission_rate`, `trader_commission_rate`, `total_fee`, `trader_fee`, `teamlead_fee`, `service_fee`). Those are historical accounting fields, not settings UI.
- If second-stage deletion of gateway-specific payout commissions is approved, separately remove:
  - `payment_gateways.trader_commission_rate_for_payouts`;
  - `payment_gateways.total_service_commission_rate_for_payouts`;
  - `PaymentGatewayResource`, requests, model fields, bulk settings, and migrations.

#### Verification

- Admin payout settings modal saves without commission fields.
- Creating payouts with and without payment method produces deterministic commission values.
- Existing payout lists, exports, wallet movements, and callback payloads still show historical fees.

### Step 23 — Remove Merchant Callback Resend Actions

#### Context

Manual callback resend exists in three paths:

- Admin mass resend by merchant/date range: `Admin\MerchantResendCallbackController::resendByDateRange()` and `admin.merchants.resend-callback`.
- Merchant/order manual resend: `Merchant\ResendCallbackController::resend()` and route `payment.callback.resend`.
- Merchant payout manual resend: `Merchant\PayoutCallbackController::resend()` and route `merchant.payouts.callback.resend`.

There is also `Merchant\ResendCallbackController::resendCascade()`, which is cascade-specific and should disappear with Step 27 or be removed here if reachable.

#### Frontend Removal

- Remove “Callback resend” from `resources/js/Pages/Merchant/Tabs/Settings.vue`:
  - `formResendCallback` reactive object;
  - `submitResendCallback()`;
  - tabs entry `{id: 'resend', title: 'Callback resend'}`;
  - date fields, submit button, validation errors, and success state.
- Remove per-order callback resend actions from `resources/js/Pages/Payment/Index.vue` for desktop and mobile buttons using `route('payment.callback.resend', order.id)`.
- Remove per-payout callback resend action from `resources/js/Pages/Payout/Merchant/Index.vue`:
  - `resendPayoutCallback()`;
  - desktop/mobile table actions calling `merchant.payouts.callback.resend`.
- Search `TableAction` labels and route names for `callback.resend` to remove stale buttons and dropdown entries.

#### Backend Removal

- Remove web routes and imports from `routes/web.php`:
  - `POST /merchant/payouts/{payout:uuid}/callback/resend` → `merchant.payouts.callback.resend`;
  - `POST /payment/{order}/callback/resend` → `payment.callback.resend`;
  - `POST /merchants/{merchant}/resend-callback` → `admin.merchants.resend-callback`.
- Delete controllers/methods that exist only for manual resend:
  - `app/Http/Controllers/Admin/MerchantResendCallbackController.php`;
  - `app/Http/Controllers/Merchant/ResendCallbackController.php` after confirming `resendCascade()` has no route or is removed with Cascade;
  - `Merchant\PayoutCallbackController::resend()` or the full controller if no other methods remain.
- Keep automatic delivery jobs and services:
  - `SendOrderCallbackJob`;
  - `SendPayoutCallbackJob`;
  - `OrderCallback\CallbackService`.
- Remove only manual retry entry points. Do not remove callback logs, merchant callback URLs, payout callback URLs, or normal lifecycle callback sending.
- Remove validation/messages and permissions used only by mass resend. `MerchantResendCallbackController` currently has explicit Super Admin check; no replacement endpoint should keep the same capability accidentally.

#### Verification

- No route named `*.callback.resend` or `admin.merchants.resend-callback` remains.
- Merchant settings page has no resend tab.
- Merchant order/payout pages have no resend actions.
- Normal callbacks are still sent on order/payout status changes and logged.

### Step 24 — Move Callback Log URL Out Of The Main Table Column

#### Context

`resources/js/Pages/CallbackLogs/Index.vue` shows URL as a desktop table column and already shows it inside mobile expanded details. The requested change is UI layout only: keep URL data in logs, but remove the main table column.

#### Frontend Removal

- In `CallbackLogs/Index.vue` desktop table:
  - remove the `URL` `<th>`;
  - remove the row `<td class="max-w-64 truncate">{{ log.url }}</td>`;
  - change expanded details `colspan` from `7` to `6`.
- Add URL to the desktop expanded details block, before request/response payloads, matching mobile expanded card behavior:
  - show only when `log.url` exists;
  - use `break-all`/`break-words` styling so long URLs do not break the layout.
- Keep mobile URL display in the expanded card.
- Keep current filters (`uuid`, `merchant`) as-is; `CallbackLogQueriesEloquent` does not currently search URL, so no filter behavior needs to change.

#### Backend Removal

- Keep `app/Http/Resources/CallbackLogResource.php::url` because the expanded details still use it.
- Keep `callback_logs.url` and log writes in `OrderCallback\CallbackService`, `SendCascadeDealCallbackJob` until Cascade is removed, and payout callback jobs. This is audit data.
- No migration required.

#### Verification

- Desktop callback logs table has no URL column.
- Expanding a row shows URL, request data, and response data.
- Mobile callback log cards still show URL in expanded details.

### Step 25 — Remove Project Support Link Setting

#### Context

The support link setting is a global project setting key `support_link` managed from admin settings. It is rendered by `SupportLink.vue`, passed as an Inertia prop, and installed through `SettingsService` defaults.

#### Frontend Removal

- Remove `import SupportLink from '@/Pages/Settings/Partials/SupportLink.vue';` and `<SupportLink />` from `resources/js/Pages/Settings/Index.vue`.
- Delete `resources/js/Pages/Settings/Partials/SupportLink.vue` after confirming it is unused.
- Remove UI validation references and any Russian text “Ссылка на техподдержку”.

#### Backend Removal

- Remove from `app/Http/Controllers/Admin/SettingsController.php`:
  - `$supportLink = services()->settings()->getSupportLink();`;
  - `supportLink` from `compact()`;
  - `updateSupportLink()` method.
- Remove route from `routes/web.php`: `admin.settings.update.support-link`.
- Remove from `app/Services/Settings/SettingsService.php`:
  - `const SUPPORT_LINK`;
  - `getSupportLink()`;
  - `updateSupportLink()`;
  - default settings entry for `support_link`.
- Remove from `app/Contracts/SettingsServiceContract.php`:
  - `getSupportLink()`;
  - `updateSupportLink()`.
- Remove validation attribute `support_link` from `lang/ru/validation.php` if unused elsewhere.
- If `php artisan app:install-settings` reads `SettingsService` defaults, updating the defaults is enough; otherwise audit `InstallSettings`/`InstallAppCommand` for hard-coded `support_link`.
- Leave existing `settings` table row inert on first deploy; delete with a data cleanup migration only after code no longer reads it.

#### Verification

- Admin settings page renders without `supportLink` prop.
- Route list has no `admin.settings.update.support-link`.
- `app:install-settings` does not recreate the removed key.

### Step 26 — Remove Order Details Eye Button / Full Requisite Reveal

#### Context

There are two related “eye/reveal” surfaces on deal pages:

- Eye button component `resources/js/Components/Order/OrderDetailsOpenButton.vue` opens `OrderModal`, exposing full order details/requisites.
- `displayShortDetail` toggle/cookie on `resources/js/Pages/Order/Index.vue` changes masked/short vs fuller requisite display in the table.

The user specifically wants to remove the eye on the deals page that opens/shows full requisites. To avoid leaving the behavior half-alive, remove both direct reveal controls where they expose full details from order tables.

#### Frontend Removal

- Remove `OrderDetailsOpenButton` import and usage from:
  - `resources/js/Pages/Order/Index.vue` desktop row;
  - `resources/js/Pages/Order/Index.vue` mobile/tablet row variants;
  - `resources/js/Pages/Support/Order/Index.vue` desktop/mobile variants.
- Remove `openOrderModal(order)` functions from those pages if they are only used by the eye action.
- Remove `OrderModal` import/render from those pages if no other action opens it.
- Delete `resources/js/Components/Order/OrderDetailsOpenButton.vue` only after repository search confirms no remaining imports.
- Remove the `has_order_sms` badge that exists only on `OrderDetailsOpenButton`. If SMS presence still needs to be visible, move it to a non-reveal badge in the row as a separate product decision.
- Remove `displayShortDetail` toggle from `Order/Index.vue` if it allows switching table requisites from short/masked to full. That includes:
  - `displayShortDetail` ref;
  - cookie read/write helpers for `displayShortDetail_*`;
  - checkbox/toggle UI;
  - passing `:short="displayShortDetail"` when the safe post-removal state should always be short/masked.
- Keep normal row actions: dispute open, accept/paid, status display, amount display, and `PaymentDetailInfoDropdown` if it does not reveal full requisites beyond allowed trader-owned details.

#### Backend Removal

- Audit `OrderResource`, table order resources, and modal endpoints used by `OrderModal`. If a backend endpoint exists solely for full order reveal from table, remove the route/controller method or restrict it to remaining legitimate flows.
- Keep backend data needed for processing orders, disputes, receipts, callbacks, and audit. Do not delete `payment_detail` storage or order relationships.
- Re-check gates/policies: removing the frontend eye is incomplete if the same full-detail modal endpoint remains broadly callable by roles that should not reveal requisites.

#### Verification

- Admin/support deal tables have no eye button and no full-detail reveal toggle.
- Requisites in deal tables remain masked/short according to the safe default.
- Dispute and payment processing flows still have the requisite data they legitimately need.
- Repository search has no import of `OrderDetailsOpenButton` unless intentionally retained outside deal pages.

### Step 27 — Remove Cascade Functionality, Keep Legacy Orders/Payouts/API

#### Context

Cascade is not a single page. It is an orchestration subsystem for payin involving API v2 payin docs/controllers, `CascadeDeal` domain models, provider attempts, callbacks, merchant logs, collateral/holds, admin pages, merchant settings, and shared callback logs. Current `routes/api.php` has a `/api/v2` catch-all returning 404, but cascade code and admin routes still exist. The safe goal is to remove Cascade while preserving old `Order`, `Payout`, H2H API, merchant payout API, deposits/withdraw webhooks, wallet history, and legacy callback delivery.

#### Stage 0 — Pre-Removal Inventory And Freeze

- Confirm production has no active merchants relying on cascade payin/API v2 payin. Check DB tables:
  - `cascade_deals` by status/sub_status/merchant/date;
  - `cascade_transactions` by status/provider;
  - `merchant_cascade_settings` enabled merchants;
  - `cascade_provider_logs` and `cascade_merchant_logs` recent activity;
  - unsettled `FundsOnHold` records whose holdable is `CascadeDeal` if such polymorphic holds exist.
- Disable new cascade creation before deleting code:
  - ensure `/api/v2/payin*` remains 404 or remove route definitions if they exist elsewhere;
  - set merchant cascade settings disabled if they can still be reached internally;
  - stop/enqueue no new cascade provider attempt jobs.
- Drain or delete pending queue jobs only after confirming they are cascade-only:
  - `cascade-provider-attempts`;
  - `cascade-internal-cleanup`;
  - jobs `CascadeProviderAttemptJob`, `CascadeProviderOperationJob`, `CascadeInternalProviderCallbackJob`, `CascadeInternalTimeoutCleanupJob`, `SendCascadeDealCallbackJob`.

#### Frontend Removal

- Remove admin Cascade pages:
  - `resources/js/Pages/Admin/CascadeProviders/Index.vue`;
  - `resources/js/Pages/Admin/CascadeDeals/Index.vue`;
  - `resources/js/Pages/Admin/CascadeMerchantSettings/Index.vue`;
  - `resources/js/Pages/Admin/CascadeProviderLogs/Index.vue`;
  - `resources/js/Pages/Admin/CascadeMerchantLogs/Index.vue`.
- Remove shared Cascade admin navigation:
  - `resources/js/Components/Admin/CascadeSectionNav.vue`;
  - menu links in admin layout/menu files pointing to `admin.cascade-*` routes.
- Remove merchant cascade logs surface:
  - route usage `merchant.cascade-merchant-logs.index`;
  - any merchant menu item or page prop referencing cascade merchant logs.
- Remove or rewrite integration docs:
  - `resources/js/Pages/Integration/V2.vue` if it documents cascade payin;
  - payin/cascade callback sections in `resources/js/Pages/Integration/Components/ApiDocumentation.vue`.
- Remove cascade-only display from shared UI only after checking legacy usage:
  - `OrderStatus` cascade detailed status props;
  - cascade dispute modals in `CascadeDeals/Index.vue`;
  - cascade wallet transaction labels shown only in cascade pages.
- Keep old H2H and payout integration docs/pages that still describe retained APIs.

#### Web Routes And Controllers Removal

- Remove imports and route definitions from `routes/web.php`:
  - `Admin\CascadeProviderController` and `admin.cascade-providers.*`;
  - `Admin\CascadeDealController` and `admin.cascade-deals.*`;
  - `Admin\MerchantCascadeSettingController` and `admin.cascade-merchant-settings.*`;
  - `Admin\CascadeProviderLogController` and `admin.cascade-provider-logs.index`;
  - `Admin\CascadeMerchantLogController` and `admin.cascade-merchant-logs.index`;
  - `Merchant\CascadeMerchantLogController` and `merchant.cascade-merchant-logs.index`.
- Delete the corresponding controller classes and cascade-specific request classes:
  - `app/Http/Requests/Admin/CascadeDeal/**`;
  - `app/Http/Requests/Admin/CascadeProvider/**`;
  - `app/Http/Requests/Admin/MerchantCascadeSetting/**`.
- Remove `abortIfCascadeHidden()` helpers and any `config('features.cascade')`/hidden-cascade flags if they exist only for this subsystem.

#### API Removal

- Remove cascade-specific API v2 payin/controllers/resources/requests:
  - `app/Http/Controllers/API/V2/OrderController.php`;
  - `app/Http/Controllers/API/V2/DisputeController.php`;
  - `app/Http/Controllers/API/V2/ProviderCallbackController.php`;
  - `app/Http/Requests/API/V2/Order/**`;
  - `app/Http/Requests/API/V2/Dispute/**`;
  - cascade payin resources such as API V2 order/cascade payment resources.
- Keep non-cascade old APIs:
  - H2H order API (`api/h2h/order*`);
  - legacy merchant payout API if retained;
  - payout callbacks;
  - deposit/withdraw webhooks;
  - Integration Infrastructure API.
- If non-cascade API V2 payout/wallet/currency controllers are still unused and product wants only old API, remove them as a separate API cleanup step. Do not mix that with Cascade removal unless explicitly approved.

#### Domain Services, Bindings, Gates

- Remove Cascade service contracts and bindings:
  - `App\Contracts\CascadeServiceContract`;
  - `App\Contracts\CascadeProviderServiceContract`;
  - bindings in `App\Providers\AppServiceProvider`;
  - `ServiceBuilder::cascade()` and any `services()->cascade()` calls.
- Remove AppServiceProvider cascade gates/route bindings:
  - `Gate::define('access-to-cascade-deal', ...)`;
  - `Route::bind('cascadeProvider', ...)`;
  - imports for `CascadeDeal`, `CascadeProvider`, and cascade contracts/services.
- Delete services under `app/Services/Cascade/**` after references are gone:
  - `CascadeService`;
  - `CascadeProviderService`;
  - `CascadeProviderDiscoveryService`;
  - `CascadeDealEventRecorder`;
  - `CascadeProviderOperationLogger`;
  - `CascadeMerchantBalanceService`;
  - `CascadeProviderCollateralService`;
  - providers `InternalCascadeProvider`, `SelfTestCascadeProvider`, `AbstractCascadeProvider`, `CascadeProviderInterface`.
- Remove cascade DTO/value object/cast:
  - `app/DTO/Cascade/CreateCascadeDealDTO.php`;
  - `app/Models/ValueObjects/CascadeManualControl.php`;
  - `app/Casts/CascadeManualControlCast.php`.

#### Jobs, Observers, Callback Integration

- Delete cascade-only jobs:
  - `CascadeProviderAttemptJob`;
  - `CascadeProviderOperationJob`;
  - `CascadeInternalProviderCallbackJob`;
  - `CascadeInternalTimeoutCleanupJob`;
  - `SendCascadeDealCallbackJob`;
  - `RecordCascadeMerchantLogJob` only if it is not needed after removing cascade merchant logs. Note: it currently also records cascade-style payout callback logs; preserve normal payout callback logs in `CallbackLog`.
- Remove scheduler/import from `routes/console.php` for `CascadeInternalTimeoutCleanupJob`.
- Audit `app/Observers/OrderObserver.php`: remove logic that dispatches `CascadeInternalProviderCallbackJob` when an `Order` is linked to a cascade deal. Normal order callback behavior must remain.
- Audit `app/Services/OrderCallback/CallbackService.php`:
  - remove cascade merchant payout log writes to `cascade_merchant_logs`;
  - keep normal `CallbackLog` creation for orders and payouts;
  - keep `SendOrderCallbackJob` and `SendPayoutCallbackJob` behavior.
- Remove `CallbackLog::TYPE_CASCADE_PAYIN` only after `SendCascadeDealCallbackJob` and cascade callback logs are gone. Keep existing callback log table unless historical log retention policy says otherwise.
- Remove `Merchant\ResendCallbackController::resendCascade()` if not already removed by Step 23.

#### Models, Enums, Resources

- Delete cascade models after code references are removed:
  - `CascadeDeal`;
  - `CascadeTransaction`;
  - `CascadeProvider`;
  - `CascadeDealEvent`;
  - `CascadeProviderLog`;
  - `CascadeMerchantLog`;
  - `MerchantCascadeSetting`.
- Remove relationships from shared models:
  - `Merchant::cascadeSetting()`;
  - `Payout::cascadeMerchantLogs()` or equivalent `hasMany(CascadeMerchantLog::class)`;
  - any `Order`/`CallbackLog` morph relation assumptions for `CascadeDeal`.
- Delete cascade resources:
  - `TableCascadeDealResource`;
  - `TableCascadeProviderResource`;
  - `TableCascadeProviderLogResource`;
  - `TableCascadeMerchantLogResource`;
  - `TableMerchantCascadeSettingResource`;
  - `MerchantCascadePaymentResource`.
- Remove cascade enums and translation strings:
  - `CascadeDealStatus`, `CascadeDealSubStatus`, `CascadeDisputeStatus`, `CascadeTransactionStatus`, `CascadeDealEventType`, `CascadePaymentMethod`;
  - cascade transaction/wallet labels if no longer used by historical UI.
- Be careful with generic terms such as `payin` in statement resources or manual control pages. Not every `payin` string is Cascade; old order/pay-in concepts may remain.

#### Finance, Wallet, And Data Retention

- Cascade has financial side effects:
  - merchant balance credit/rollback via `CascadeMerchantBalanceService`;
  - provider collateral/hold via `CascadeProviderCollateralService`;
  - transaction types like `income_from_a_successful_cascade_deal`, `rollback_income_from_a_successful_cascade_deal`, `cascade_provider_collateral_hold`, `cascade_provider_collateral_release`.
- Before removing schema, produce a finance decision for:
  - successful cascade deals already credited to merchants;
  - rolled back or disputed cascade deals;
  - unsettled collateral/holds;
  - wallet history rows that reference cascade transaction types.
- Keep wallet history readable. Do not delete transaction type enum values if historical wallet rows still cast through them. Prefer marking them legacy/read-only if the enum is used for old rows.
- Export/archive historical tables if audit requires them.

#### Database Removal Plan

Use staged migrations, not one destructive migration:

1. **Code stop-write deployment:** remove UI/routes/jobs/services that create or mutate cascade data; keep tables for reads/audit if needed.
2. **Reference cleanup deployment:** remove shared references and morph assumptions after queue drain and monitoring.
3. **Schema drop deployment:** drop cascade tables and columns only after data retention is approved.

Tables/columns to inventory before drop:

- `cascade_deals`;
- `cascade_transactions`;
- `cascade_deal_events`;
- `cascade_providers`;
- `cascade_provider_logs`;
- `cascade_merchant_logs`;
- `merchant_cascade_settings`;
- indexes/foreign keys such as `cascade_deals_order_id_index`, selected transaction/provider FKs, merchant/external ID unique indexes;
- any shared columns referencing cascade IDs or callback revisions if they are cascade-only.

#### Verification

- Route list contains no `admin.cascade-*`, `merchant.cascade-*`, or provider callback routes.
- Admin menu has no Cascade entries.
- Merchant menu has no Cascade logs.
- Integration docs no longer describe cascade/API v2 payin if removed.
- H2H order API still creates old orders.
- Old payout API and payout callbacks still work.
- Deposit/withdraw webhooks still work.
- Wallet history pages do not crash on historical cascade transaction types.
- Queue workers have no pending cascade jobs and Horizon config has no cascade-only supervisor/queue references.


## Cross-Feature Verification Checklist

- Fresh route list contains no removed route names and Ziggy output has no stale references, including shipped legacy steps 1–18 and new steps 19–27 when each is implemented.
- Login redirect for every remaining role lands on a valid page.
- Admin user create/edit works without removed role fields, team fields, temp VIP fields, economy toggle, priority payout toggle, or agent fields.
- Support flows still work after Analyst removal: users, orders, disputes, enabled cards, manual control acquiring, payouts, deposits, and trader analytics where retained.
- Trader order acceptance, payment detail management, payout taking, finances, disputes, and H2H API order creation still work.
- Merchant integration page still shows only retained docs/token/download surfaces; no browser API playground, no demo payment page, and no cascade/V2 payin docs after Step 27 if cascade is removed.
- H2H API, payout callbacks, deposit/withdraw webhooks, and merchant finance history still work where explicitly retained; cascade payin is removed only when Step 27 is implemented.
- No removed setting keys are read by runtime code.
- No removed enum value can be selected in UI or accepted in API validation.
- Database migrations for dropping columns/tables are separated from code removal when production data retention is uncertain.
- Payment gateway create/edit/bulk settings, payout pooling, callback delivery, and deal tables are verified after their respective new removal steps.

## Open Decisions Before Implementation

- Whether Step 22 removes only global fallback payout commission settings or also payment-gateway-specific payout commission columns.
- Replacement source for bank notification sender aliases after removing `payment_gateways.sms_senders` (manual input, SMS-log discovery, or removing sender stop-list helpers).
- Whether SMS presence from `has_order_sms` should remain visible after deleting the order eye button, and where it should be displayed if retained.
- Data policy for existing NSPK payment details and payment gateway `detail_types` JSON values before enum removal.
- Data-retention/export policy for historical cascade deals, transactions, events, provider logs, merchant logs, and unsettled holds before Step 27 schema removal.
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
