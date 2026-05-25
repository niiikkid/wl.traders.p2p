# Step 1: Trader Finances Page Located

> Source: Agent implementation session (p2p.cti codebase)
> Collected: 2026-05-25
> Published: 2026-05-25

## Goal

Complete implementation plan step 1: locate the trader's own «Финансы» page and distinguish it from Team Leader read-only trader finances.

## Confirmed anchors

- Trader finances URL: `GET /finances`
- Route name: `wallet.index` (trader middleware group in `routes/web.php`, ~line 279)
- Controller: `App\Http\Controllers\WalletController@index`
- Inertia: `resources/js/Pages/Wallet/Index.vue`
- Trust card: `resources/js/Pages/Wallet/Partials/TrustBalance.vue`
- History: `resources/js/Pages/Wallet/Partials/OperationsHistory.vue`
- Menu: `TraderMenu.vue` → `route('wallet.index')`

## Not trader own finances

- `GET /leader/traders/{trader}/finances` → `leader.traders.finances.index`
- `TeamLeader\TraderFinanceController` → `Leader/Trader/Finances.vue`
- Provider: `provider-liquidity.wallet.index` (not `wallet.index`)

## Code changes (step 1)

- `WalletController::resolveBalanceType()` — match on route name instead of `action['as']`
- `WalletController::isTraderOwnFinancesPage()` — `routeIs('wallet.index')` + Trader role (for future transfer UI/props)
- `routes/web.php` — comment marker for planned `wallet.trader-transfer.*` routes after `wallet.index`

## Not in scope yet

- `TransactionType` transfer cases
- Transfer API routes/controller/service
- Vue modal / «Перевести средства» button
