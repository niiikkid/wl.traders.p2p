# Currency Rates Feature

## Purpose

The currency rates feature gives Cortex a controlled way to obtain the ready `USDT/{fiat}` rate used during pay-in pricing.

The central rule is simple:

```text
USDT/RUB = 90.1234 means 1 USDT costs 90.1234 RUB.
```

Pay-in pricing treats the rate as fiat units per one USDT. If a customer pays `15,000 RUB` and the fixed rate is `100.0000`, the gross USDT amount is:

```text
15,000 / 100.0000 = 150 USDT
```

The feature deliberately separates two things:

- Reading a ready rate for payment decisions.
- Refreshing or parsing rates from external P2P providers.

Payment flows must stay fast and deterministic. They do not call Bybit or Binance. They read a previously prepared rate through `App\Services\Rates\ExchangeRateService`, then snapshot that value on the pay-in.

## Knowledge Sources

This document was compiled from the project wiki and the current Laravel code.

Wiki sources:

- `wiki/project-rates/rate-source-service.md`
- `wiki/project-rates/automatic-p2p-rate-parsing.md`
- `wiki/project-architecture/pricing-policies.md`
- `wiki/project-providers/ctipay-provider-integration.md`
- `wiki/project-architecture/spa-api-platform.md`

Raw wiki sources:

- `raw/project-rates/2026-05-10-rate-source-service-implementation.md`
- `raw/project-rates/2026-05-30-automatic-p2p-rate-parsing-requirements.md`
- `raw/project-rates/2026-05-30-automatic-p2p-rate-parsing-implementation.md`

Frontend docs:

- `docs/frontend/spa-api-docs.md`
- `docs/frontend/spa-admin-rate-sources-page.md`
- `docs/frontend/merchant-project-settings-form.md`
- `docs/frontend/provider-project-settings-form.md`

Core implementation:

- `app/Models/RateSource.php`
- `app/Domain/Rates/ExchangeRate.php`
- `app/Domain/Rates/RateParseResult.php`
- `app/Services/Rates/ExchangeRateService.php`
- `app/Services/Rates/RateStore.php`
- `app/Services/Rates/RateRefreshService.php`
- `app/Services/Rates/RateParserFactory.php`
- `app/Services/Rates/Parsers/*`
- `app/Http/Controllers/Api/V1/RateSourceController.php`
- `app/Http/Controllers/Api/V1/RateProviderFilterOptionsController.php`
- `app/Jobs/RefreshRateSourceJob.php`
- `app/Jobs/RefreshRateProviderFilterOptionsJob.php`
- `app/Console/Commands/DispatchRateSourceRefreshes.php`
- `app/Console/Commands/RefreshRateProviderFilterOptions.php`

Pay-in usage:

- `app/Actions/Payments/ResolvePayInRate.php`
- `app/Actions/Payments/CreatePayIn.php`
- `app/Actions/Payments/Testing/CreateTestPayIn.php`
- `app/Domain/Pricing/*`
- `app/Http/Requests/Api/V1/StorePayInRequest.php`
- `app/Services/Providers/Adapters/CtiPayProviderAdapter.php`
- `app/Jobs/RunProviderAttemptJob.php`

Tests:

- `tests/Feature/RateSourceServiceTest.php`
- `tests/Feature/RateSourceApiTest.php`
- `tests/Feature/PayInApiTest.php`

## High-Level Architecture

The rates module has four layers.

First, `rate_sources` stores configured sources and the last ready rate. It is the durable source of truth for prepared rates.

Second, `ExchangeRateService` is the read API. Other modules pass a `RateSource` and receive an `ExchangeRate` immediately. The read path uses cache first and falls back to the database.

Third, `RateRefreshService` and parsers update a source. This path can call external providers, retry, fail, and update diagnostics.

Fourth, admin SPA endpoints manage sources, preview parser settings, queue refresh jobs, and expose provider filter options.

The normal stored-source flow is:

```text
Admin creates RateSource
  -> manual source writes rate immediately
  -> automatic source queues initial refresh

Scheduler
  -> rates:dispatch-refreshes every minute
  -> active automatic sources only
  -> RefreshRateSourceJob on rate-parsing queue
  -> RateRefreshService
  -> Bybit/Binance parser
  -> update rate_sources.rate + last_parse_attempt + cache

Pay-in create
  -> MerchantPricingSettingsResolver finds settings.pricing.rate_sources.{currency}
  -> ResolvePayInRate loads active matching RateSource
  -> ExchangeRateService returns ready cached/database rate
  -> CreatePayIn snapshots rate, rate_source_id, rate_fixed_at
  -> pricing and provider attempts use the pay-in snapshot
```

There is also a merchant API rate mode:

```text
Merchant project settings use "api" for a currency
  -> merchant must send rate in POST /api/v1/pay-ins
  -> Cortex validates the rate
  -> rate_source_id stays null
  -> pay-in snapshots the provided rate
```

## Database Model

### `rate_sources`

Created by:

- `database/migrations/2026_05_10_132910_create_rate_sources_table.php`
- `database/migrations/2026_05_30_072918_add_automatic_parsing_fields_to_rate_sources_table.php`

Fields:

- `id`: integer primary key.
- `name`: nullable human-readable source name.
- `type`: string, default `manual`.
- `base_currency`: source base currency, usually `USDT`.
- `quote_currency`: fiat currency.
- `rate`: nullable `decimal(24, 4)`, the last ready price.
- `settings`: nullable JSON, provider-specific parser settings or manual metadata.
- `is_active`: boolean, default `true`.
- `last_refreshed_at`: nullable timestamp of the last refresh attempt.
- `last_parse_attempt`: nullable JSON diagnostic payload.
- timestamps.

Indexes:

- `type, is_active`
- `base_currency, quote_currency`

Current model:

- `App\Models\RateSource`
- Casts `type` to `App\Enums\RateSourceType`.
- Casts `rate` as `decimal:4`.
- Casts `settings` and `last_parse_attempt` as arrays.
- `pair()` returns `BASE/QUOTE`.
- `exchangeRate()` returns `ExchangeRate|null`.
- `isAutomatic()` delegates to `RateSourceType::isAutomatic()`.

Current source types:

- `manual`
- `bybit`
- `binance`

The early wiki and older frontend page mention a future `composite` type, but the current backend enum does not accept it.

### `pay_ins` Rate Snapshot

Pay-ins store rate facts separately from the source:

- `rate_source_id`: nullable FK to `rate_sources`, set only for stored source mode.
- `rate`: nullable `decimal(24, 4)`, fixed rate used for this pay-in.
- `rate_fixed_at`: nullable timestamp added later as a public amount field.

This is important: a later source refresh must not reinterpret an existing pay-in. Payment, pricing, provider attempts, and API resources read the pay-in snapshot.

## Domain Objects

### `ExchangeRate`

`App\Domain\Rates\ExchangeRate` is the value object for one rate.

It contains:

- `baseCurrency`
- `quoteCurrency`
- `price`

It validates:

- price must match a decimal string with up to 4 fractional digits;
- price must be greater than zero;
- currencies are resolved through `App\Domain\Money\Currency`.

It normalizes by `bcadd($price, '0', 4)`, so `100` becomes `100.0000`.

`toArray()` returns:

```json
{
  "base_currency": "USDT",
  "quote_currency": "RUB",
  "pair": "USDT/RUB",
  "price": "100.0000"
}
```

### `RateParseResult`

`App\Domain\Rates\RateParseResult` describes one parser attempt. It is used both for preview responses and persisted `last_parse_attempt`.

Fields:

- `status`: `success`, `empty`, or `failed`.
- `attempted_at`: generated by `toArray()`.
- `provider`: `manual`, `bybit`, or `binance`.
- `side`: `buy` or `sell`.
- `rate`: decimal string when available.
- `ads_found`: ads returned before Cortex filters, when known.
- `ads_used`: ads included in the average.
- `prices_used`: list of decimal strings used in the average.
- `filters`: normalized filters used by the parser.
- `error`: safe short error string or `null`.

`empty()` localizes the default empty message with `messages.rates.parse_empty`.

### Exceptions

Rate exceptions are localized:

- `RateNotAvailable`: no ready rate exists for the source.
- `InvalidExchangeRate`: bad decimal/precision/non-positive price.
- `UnsupportedRateSourceType`: parser factory cannot support a type.

Messages live under `messages.rates.*` in `lang/ru`, `lang/en`, and `lang/uk`.

## Ready Rate Read Path

The read path is `App\Services\Rates\ExchangeRateService`.

`getRate(RateSource $source): ExchangeRate`:

1. Rejects unsaved models with `RateNotAvailable`.
2. Tries `RateStore::get($source)`.
3. If cache has a valid payload, returns it.
4. Refreshes the Eloquent model from DB.
5. If `rate` is still `null`, throws `RateNotAvailable`.
6. Builds `ExchangeRate` from DB fields.
7. Writes the value back to cache.
8. Returns the rate.

`getPrice(RateSource $source): string` returns the normalized price string.

The cache is handled by `App\Services\Rates\RateStore`.

Cache key:

```text
rate-sources.{id}.latest-rate
```

TTL:

```text
60 seconds
```

Payload:

```json
{
  "base_currency": "USDT",
  "quote_currency": "RUB",
  "price": "100.0000"
}
```

If the cached payload is malformed, `RateStore::get()` returns `null`, so `ExchangeRateService` falls back to the database.

## Refresh Path

The refresh path is `App\Services\Rates\RateRefreshService`.

`refresh(RateSource $source): ExchangeRate`:

1. Reloads the source from DB by ID.
2. Gets a parser from `RateParserFactory`.
3. Calls `parse($source)`.
4. If the parser throws, records a failed attempt and rethrows.
5. Opens a DB transaction.
6. Locks the row with `lockForUpdate()`.
7. Updates `last_refreshed_at` and `last_parse_attempt`.
8. If the parser returned a rate, also updates `rate`.
9. After commit, writes successful rates to `RateStore`.
10. Returns the new rate.
11. If the parser returned empty and an old DB rate exists, returns the old DB rate.
12. If the parser returned empty and no old DB rate exists, throws `RateNotAvailable`.

This preserves the previous ready rate on empty parse results. Empty means “no suitable current ads”, not “rate is zero”.

Failed attempts:

- `recordFailedAttempt()` tries to extract normalized P2P settings.
- If settings cannot be parsed, it records default side `buy` and empty filters.
- It stores only a short error string, not provider raw bodies or stack traces.

## Parser Factory And Parsers

`App\Services\Rates\RateParserFactory` maps source type to parser:

- `manual` -> `ManualRateParser`
- `bybit` -> `BybitRateParser`
- `binance` -> `BinanceRateParser`

The shared parser contract is `App\Contracts\Rates\RateParser`:

```php
public function parse(RateSource $source): RateParseResult;
```

### Manual Parser

`ManualRateParser` does not call external APIs. It reads `RateSource::rate`, validates it through `ExchangeRate`, and returns a success result.

If `rate` is null, it throws `RateNotAvailable`.

Manual parser diagnostics use:

- provider `manual`;
- side `buy`;
- `ads_used = 1`;
- `prices_used = [rate]`.

Scheduled refresh jobs skip manual sources, so this parser is mostly used by direct `RateRefreshService` calls and tests. Manual source create/update updates cache immediately in `RateSourceController::afterPersist()`.

### P2P Parser Settings

`App\Services\Rates\Parsers\P2pParserSettings` normalizes automatic source settings.

Common fields:

- `provider`: source type.
- `side`: `buy` or `sell`, default `buy` if missing at DTO normalization level.
- `paymentMethods`: normalized array of int/string IDs.
- `adQuantity`: clamped to provider maximum.

Bybit fields:

- `amount`
- `payment_methods`
- `ad_quantity`, max 200
- `min_recent_orders`

Binance fields:

- `country`
- `payment_methods`
- `ad_quantity`, max 100
- `min_month_orders`

`filters()` returns only non-null and non-empty values. This is what goes into `last_parse_attempt.filters`.

Validation requires `settings.side` for automatic sources, so the default side mostly protects lower-level code and failed diagnostics.

### Shared P2P Parser Logic

`App\Services\Rates\Parsers\P2pRateParser` provides:

- `emptyResult()`
- `successResult()`
- side mapping helpers;
- average calculation.

Average calculation:

```text
sum prices with scale 8
divide by count with ExchangeRate::PRECISION = 4
```

So the final parsed rate is truncated/normalized to 4 decimals through `ExchangeRate`.

Side mapping:

- Binance: `buy` -> `BUY`, `sell` -> `SELL`.
- Bybit: `buy` -> `1`, `sell` -> `0`.

### Bybit Parser

`BybitRateParser` calls:

```text
POST https://www.bybit.com/x-api/fiat/otc/item/online
```

Important request fields:

- `tokenId = USDT`
- `currencyId = quote_currency`
- `payment = settings.payment_methods`
- `side = 1|0`
- `size = 200`
- `page = 1`
- `amount = settings.amount` or empty string
- `sortType = TRADE_PRICE`

Timeouts:

- request timeout: 20 seconds;
- connect timeout: 5 seconds.

The parser uses browser-like headers because Bybit edge protection may reject a minimal request.

Filtering:

- counts raw `result.items` as `ads_found`;
- skips non-array items;
- skips merchants below `min_recent_orders`;
- skips invalid or non-positive price values;
- collects prices until `ad_quantity`, default 50;
- returns `empty` if no prices remain.

Errors:

- non-success HTTP -> `RuntimeException`;
- provider `ret_msg` not `SUCCESS` -> `RuntimeException`;
- unexpected payload shape -> `RuntimeException`.

### Binance Parser

`BinanceRateParser` calls:

```text
POST https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search
```

Important request fields:

- `rows = 20`
- `payTypes = settings.payment_methods`
- `countries = [settings.country]` or empty
- `asset = USDT`
- `tradeType = BUY|SELL`
- `fiat = quote_currency`
- `publisherType = null`

Timeouts:

- request timeout: 25 seconds;
- connect timeout: 5 seconds.

Pagination:

- up to 5 pages;
- stops earlier if provider `total` indicates no more pages;
- also stops if a batch returns fewer rows than requested.

Filtering:

- skips non-array offers;
- skips privileged/special ads when privilege fields are present;
- skips advertisers below `min_month_orders`;
- skips invalid or non-positive price values;
- collects prices until `ad_quantity`, default 50;
- returns `empty` if no prices remain.

Errors:

- non-success HTTP -> `RuntimeException`;
- unexpected payload shape -> `RuntimeException`.

## Admin API

Rate source endpoints live in the admin-only SPA route group:

```text
middleware: auth:sanctum, user-action.failed-spa, admin
base: /api/v1
```

Routes:

- `GET /api/v1/rate-sources`
- `GET /api/v1/rate-sources/{rate_source}`
- `POST /api/v1/rate-sources`
- `PUT/PATCH /api/v1/rate-sources/{rate_source}`
- `POST /api/v1/rate-sources/preview`
- `POST /api/v1/rate-sources/{rate_source}/refresh`
- `GET /api/v1/rate-sources/{rate_source}/parse-attempt`
- `GET /api/v1/rate-source-providers/{provider}/filter-options?quote_currency=RUB`

The early `Rate Source Service` wiki article says the first HTTP API was unauthenticated. That is historical. The current implementation is admin-only under Sanctum.

### `RateSourceResource`

Returned fields:

- `id`
- `name`
- `type`
- `base_currency`
- `quote_currency`
- `pair`
- `rate`
- `settings`
- `is_active`
- `last_refreshed_at`
- `created_at`
- `updated_at`

`last_parse_attempt` is intentionally not embedded. It is loaded through the separate parse-attempt endpoint.

### Create

Manual create requires `rate`:

```json
{
  "name": "Manual USDT/RUB",
  "type": "manual",
  "base_currency": "USDT",
  "quote_currency": "RUB",
  "rate": "90.1234",
  "settings": null,
  "is_active": true
}
```

Automatic create prohibits `rate`:

```json
{
  "name": "Bybit USDT/RUB buy",
  "type": "bybit",
  "base_currency": "USDT",
  "quote_currency": "RUB",
  "settings": {
    "side": "buy",
    "amount": 200000,
    "payment_methods": [14],
    "ad_quantity": 10,
    "min_recent_orders": 100
  },
  "is_active": true
}
```

Controller behavior:

- `normalizeAttributes()` removes `rate` for automatic sources and forces `base_currency = USDT`.
- Manual source with a valid `rate` writes to cache immediately.
- Automatic source dispatches `RefreshRateSourceJob` immediately.

Validation:

- `type`: required enum.
- `base_currency`: uppercase; automatic sources must be `USDT`.
- `quote_currency`: uppercase, non-base configured currency, different from base.
- `rate`: required for manual, prohibited for automatic, decimal `> 0`, up to 4 decimals.
- `settings.side`: required for automatic.
- Bybit payment methods must be integers.
- Binance payment methods must be strings.
- Bybit rejects `settings.country`.
- Binance rejects `settings.amount` and `settings.min_recent_orders`.
- `is_active`: optional boolean.

### Update

Update accepts the same fields with `sometimes` rules.

Side effects:

- Manual update refreshes the ready-rate cache if the source has a valid `rate`.
- Automatic update dispatches a refresh job.

Potential operator note: changing an automatic source's settings does not synchronously update `rate`; the returned source may still show the previous rate until the queued parser succeeds.

### Preview

Endpoint:

```text
POST /api/v1/rate-sources/preview
```

Preview:

- accepts only `bybit` or `binance`;
- uses the same parser path as queued refresh;
- calls the external provider synchronously;
- does not create a `RateSource`;
- does not write database;
- does not write ready-rate cache.

Use it for admin UI “Check rate” before saving. Provider API failures may surface as server errors, so the frontend should show a generic failure message for preview exceptions.

### Refresh Now

Endpoint:

```text
POST /api/v1/rate-sources/{rate_source}/refresh
```

Response:

```json
{
  "data": {
    "queued": true
  }
}
```

Manual sources are accepted but return `queued: false`.

Automatic sources dispatch `RefreshRateSourceJob`. The endpoint does not parse synchronously.

### Last Parse Attempt

Endpoint:

```text
GET /api/v1/rate-sources/{rate_source}/parse-attempt
```

Returns:

- `data: null` before any attempt;
- otherwise the `last_parse_attempt` JSON object.

This endpoint is for diagnostics and operator feedback. It is not used by pay-in pricing.

### List Filtering

`RateSourceController::index()` applies the universal `FilterRegistry` resource key:

```text
rate-sources
```

`RateSourceFilterSet` supports:

- `name`
- `currency` mapped to `quote_currency`
- `is_active`
- `type`
- `last_refreshed_at` date range

The universal filter-options endpoint can describe these filters:

```text
GET /api/v1/filter-options/rate-sources
```

## Provider Filter Options

The admin UI needs provider-specific options for parser settings, especially payment methods and Binance countries.

Endpoint:

```text
GET /api/v1/rate-source-providers/{provider}/filter-options?quote_currency=RUB
```

Rules:

- `provider` must be `bybit` or `binance`.
- `quote_currency` is required and must be a configured non-base currency.
- Invalid provider returns `404`.
- Invalid quote currency returns validation error.

Store:

- `App\Services\Rates\RateProviderFilterOptionsStore`
- cache key: `rate-sources.filter-options.{provider}.{QUOTE}`
- TTL: one day.

The endpoint calls `remember()`, so if the cache is empty it may fetch provider options synchronously.

### Bybit Filter Options

`RateProviderFilterOptionsService::fetchBybit()` calls:

```text
POST https://api2.bybit.com/fiat/otc/configuration/queryAllPaymentList
```

It normalizes:

```json
{
  "payment_methods": [
    { "id": 14, "name": "Sberbank" }
  ]
}
```

It reads Bybit's `currencyPaymentIdMap` and `paymentConfigVo`, then keeps payment methods available for the selected fiat currency.

### Binance Filter Options

`RateProviderFilterOptionsService::fetchBinance()` calls:

```text
POST https://p2p.binance.com/bapi/c2c/v2/public/c2c/adv/filter-conditions
```

It normalizes:

```json
{
  "countries": [
    { "id": "RU", "name": "Russia" }
  ],
  "payment_methods": [
    { "id": "Tinkoff", "name": "Tinkoff" }
  ]
}
```

The cached payload is currently per provider and fiat. Country-dependent payment methods are mentioned in docs as a future UI consideration, but the current store key does not include country.

## Queues, Scheduler, Horizon

### Refresh Source Jobs

`App\Jobs\RefreshRateSourceJob`:

- implements `ShouldQueue` and `ShouldBeUnique`;
- queue: `config('queue.names.rate_parsing')`, default `rate-parsing`;
- tries: 3;
- timeout: 30 seconds;
- uniqueFor: 60 seconds;
- backoff: `[10, 30, 60]`.

The job:

1. Loads the source by ID.
2. Requires `is_active = true`.
3. Skips if the source no longer exists.
4. Skips if the source is no longer automatic.
5. Calls `RateRefreshService::refresh()`.

On final failure it logs a warning with source ID, exception class, and message.

### Dispatch Rate Refreshes Command

`App\Console\Commands\DispatchRateSourceRefreshes`:

```text
rates:dispatch-refreshes
```

It selects:

- active sources only;
- type in `bybit`, `binance`;
- chunks by ID 100;
- dispatches one `RefreshRateSourceJob` per source.

Manual sources are skipped to avoid queue noise.

Schedule:

```text
every minute
without overlapping
```

### Refresh Filter Options Jobs

`App\Console\Commands\RefreshRateProviderFilterOptions`:

```text
rates:refresh-filter-options
```

It dispatches `RefreshRateProviderFilterOptionsJob` for:

- each provider: Bybit and Binance;
- each non-base configured currency from `config/money.php`.

The job:

- queue: `rate-parsing`;
- tries: 3;
- timeout: 30 seconds;
- backoff: `[10, 30, 60]`;
- fetches options from provider;
- writes them to the one-day cache.

Schedule:

```text
hourly
without overlapping
```

### Horizon And Queue Config

`config/queue.php` defines:

```php
'rate_parsing' => env('RATE_PARSING_QUEUE', 'rate-parsing')
```

`config/horizon.php` includes:

- wait threshold for `rate-parsing`;
- a `rate-parsing` supervisor;
- environment-specific process limits.

## Supported Currencies

Currency codes come from `config/money.php`.

Current non-base fiat currencies:

- `RUB`
- `KZT`
- `EUR`
- `TJS`
- `KGS`
- `UAH`
- `USD`
- `AZN`
- `TRY`
- `IDR`
- `PLN`

`USDT` is marked as base and excluded from fiat selectors.

Automatic P2P sources are constrained to:

```text
base_currency = USDT
quote_currency = one configured non-base currency
```

## Merchant Project Configuration

Merchant projects configure rate selection in:

```text
settings.pricing.rate_sources
```

Shape:

```json
{
  "pricing": {
    "rate_sources": {
      "RUB": 1,
      "UAH": "api"
    },
    "pairs": []
  }
}
```

Each key is a fiat currency. Each value is either:

- numeric `rate_sources.id`;
- string `"api"`.

`MerchantProjectRateSourceValue` validates project save values:

- `"api"` is accepted case-insensitively;
- numeric values must point to an existing `RateSource`;
- other values fail validation.

This save-time validation does not ensure:

- the source is active;
- the source pair is `USDT/{currency}`;
- the source has a ready `rate`;
- the source type is appropriate for the business context.

Those checks happen at pay-in creation time.

## Pay-In Creation Flow

The main resolver is `App\Actions\Payments\ResolvePayInRate`.

Input:

- resolved `MerchantPricingSettings`;
- pay-in currency;
- optional merchant-provided `rate`.

Output:

```php
array{0: string, 1: RateSource|null}
```

The first element is the fixed rate string. The second is the source model or null for merchant API mode.

### Stored Source Mode

When project settings contain a numeric source ID:

1. `ResolvePayInRate::storedRateSource()` loads the source by ID.
2. It requires `is_active = true`.
3. It requires `base_currency = USDT`.
4. It requires `quote_currency = pay-in currency`.
5. If any check fails, it throws validation error `messages.pay_in.no_rate_source`.
6. It calls `ExchangeRateService::getPrice($rateSource)`.
7. If no ready rate exists, `RateNotAvailable` is thrown.

`StorePayInRequest` prohibits the merchant from sending `rate` in this mode.

### Merchant API Rate Mode

When project settings contain `"api"`:

1. `StorePayInRequest` requires `rate`.
2. It validates `rate` as decimal `> 0`, up to 4 decimals.
3. `ResolvePayInRate::merchantApiRate()` returns the provided value.
4. `rate_source_id` is null.

If `rate` is missing, the resolver throws validation error `messages.pay_in.merchant_api_rate_required`.

### Snapshot In `CreatePayIn`

`CreatePayIn` resolves rate inside the DB transaction before creating the pay-in.

It stores:

- `rate_source_id`: selected source ID or null;
- `rate`: fixed rate string;
- `rate_fixed_at`: `now()`;
- `merchant_pricing`: result of pricing calculation;
- `pricing_settings`: snapshot of resolved merchant settings.

`CreateTestPayIn` uses the same `ResolvePayInRate`, so sandbox/test-mode pay-ins fix rates the same way as live pay-ins.

## Pricing Usage

Merchant pricing is recalculated by `App\Domain\Pricing\PayInPricingRecalculator`.

The rate feeds `MerchantPricingCalculator` policies.

Policies:

- `percent_fee`: `gross = fiat / rate`, `fee = gross * fee_percent`, `net = gross - fee`.
- `percent_plus_fixed_fee`: same gross, then percent fee plus fixed USDT fee.
- `rate_markup`: `merchantRate = marketRate * (1 + markup_percent)`, then `fiat / merchantRate`.
- `fixed_custom_rate`: `fiat / fixedRate`.
- `mixed`: markup first, then max of percent fee and minimum fee.

All fiat-to-USDT conversion goes through:

```php
PricingMath::divideFiatByRate()
```

It:

- validates rate as a positive decimal;
- divides the fiat `Money` decimal by rate using high calculation scale;
- creates USDT `Money`;
- truncates to configured USDT precision through `MoneyFormatter`.

The `PricingResult` stores the rate actually used in its `rate` field. For markup policies this can be the marked-up merchant rate, not necessarily the raw market source rate.

## Provider Usage

Provider attempts receive the pay-in snapshot, not the live source.

`RunProviderAttemptJob::createDealData()` creates `ProviderCreateDealData` with:

```php
rate: $payIn->rate
```

The provider DTO includes:

- `externalId`
- `amount`
- `currency`
- `payInMethod`
- `callbackUrl`
- `clientId`
- `rate`
- timing fields

### CTIPay

`CtiPayProviderAdapter::createDeal()` builds a provider payload and conditionally includes `rate`.

It sends:

```php
if ($this->shouldSendPayInRate($settings) && $data->rate !== null && $data->rate !== '') {
    $payload['rate'] = $this->ctiPayRateValue($data->rate);
}
```

Provider setting:

```text
settings.send_pay_in_rate
```

Meaning:

- `false` or omitted: do not send `rate`.
- `true`: send the pay-in fixed rate to CTIPay.

This is not a live source read. It is forwarding the already fixed pay-in rate.

Wiki note: CTIPay accepts `rate` only when the CTIPay-side merchant GEO uses `merchant_api` as the rate source for that currency. In that provider configuration, CTIPay expects the rate on create.

## API Output

Merchant-facing `PayInResource` includes:

- `rate`
- `rate_fixed_at`
- `rate_source_id`

Admin `AdminPayInResource` includes the same top-level fields and also exposes rate in the detailed breakdown:

```json
{
  "merchant": {
    "rate": {
      "value": "100.0000",
      "fixed_at": "2026-07-01T00:00:00.000000Z",
      "source_id": 1
    }
  }
}
```

When `rate_source_id` is null and `rate` is non-null, it usually means merchant API rate mode was used.

## User Action Audit Logging

`RateSource` is observed by `UserActionLogObserver` through `AppServiceProvider`.

Audited events:

- `rate_source.created`
- `rate_source.updated`

Translation keys exist in `lang/{locale}/user_actions.php`.

The observer records changed fields after commit. Tests verify create/update audit behavior for rate sources.

## Localization

Rate-related user-visible strings are localized in all supported locales:

- `lang/ru/messages.php`
- `lang/en/messages.php`
- `lang/uk/messages.php`
- `lang/ru/enums.php`
- `lang/en/enums.php`
- `lang/uk/enums.php`
- `lang/*/user_actions.php`

Relevant message groups:

- `merchant_project.invalid_rate_source`
- `merchant_project.rate_source_not_found`
- `pay_in.no_rate_source`
- `pay_in.merchant_api_rate_required`
- `pay_in.merchant_api_rate_prohibited`
- `rates.not_available`
- `rates.invalid_price`
- `rates.unsupported_type`
- `rates.parse_empty`

Enum labels:

- `enums.rate_source_type.manual`
- `enums.rate_source_type.bybit`
- `enums.rate_source_type.binance`

CTIPay settings labels/help include:

- `messages.provider_settings.send_pay_in_rate_label`
- `messages.provider_settings.send_pay_in_rate_help`

## Test Coverage

`tests/Feature/RateSourceServiceTest.php` covers:

- manual source read through `ExchangeRateService`;
- cache-first behavior before DB fallback;
- manual refresh through `RateRefreshService`;
- scheduled dispatcher only queues active automatic sources;
- Bybit refresh stores calculated average and parse attempt;
- empty parse keeps the previous DB rate;
- Bybit payment method filter option normalization;
- filter-option refresh command dispatches jobs for fiat currencies.

`tests/Feature/RateSourceApiTest.php` covers:

- admin create manual source;
- create automatic source and queue initial refresh;
- update source;
- list sources newest first;
- show source;
- validation failures;
- preview automatic parsing without DB persistence;
- separate parse-attempt endpoint;
- cached provider filter options endpoint.

`tests/Feature/PayInApiTest.php` covers:

- selecting the rate source by pay-in currency;
- creating pay-in with merchant-provided API rate;
- requiring `rate` when project uses `"api"`;
- rejecting `rate` when project uses stored source;
- validating API rate precision.

There is also integration-locale and audit-log coverage touching rate sources.

## Important Invariants

The feature relies on these invariants:

- A rate is fiat price for one USDT.
- Ready rate precision is four decimal places.
- Pay-in creation fixes the rate once.
- Past pay-ins are not recalculated from refreshed sources.
- Automatic parser failures do not change existing ready rates unless a new valid rate is parsed.
- Empty parser result keeps the previous DB rate.
- Payment code never calls Bybit or Binance directly.
- Merchant API rate mode is not represented by a `rate_sources` row.
- `rate_source_id = null` on pay-in is valid when the rate came from merchant API mode.
- Provider adapters receive pay-in snapshot rate, not current source rate.

## Edge Cases And Risks

### Automatic Source With `rate = null`

An automatic source can exist before the first successful parse. The API returns `rate: null`. If a merchant project points to that source, pay-in creation will fail because `ExchangeRateService` cannot return a ready rate.

Admin UI should treat this as “pending first parse”, not zero.

### Project Save Is Less Strict Than Pay-In Runtime

Project save validates that a numeric rate source ID exists. It does not verify active state, pair match, or ready rate.

Pay-in creation later rejects or fails if:

- source is inactive;
- source pair does not match pay-in currency;
- source base is not `USDT`;
- source has no ready rate.

Frontend should be stricter than backend save validation to avoid runtime failures.

### Cache Can Temporarily Mask Direct DB Changes

`ExchangeRateService` reads cache first. If a rate is already cached and the DB row changes directly, the old cached value is returned until TTL expires or code writes a new cache value.

Normal manual update through `RateSourceController` writes cache immediately, so this mainly matters for direct DB writes or unusual internal updates.

### Manual Source Refresh Is Not Scheduled

Manual sources are not refreshed by the scheduler. Updating a manual source through the admin API updates cache immediately. Direct service refresh can still work, but cron skips manual sources.

### Preview Can Hit Provider APIs Synchronously

Preview uses the same parser path and can take up to provider timeout. It does not persist diagnostics. UI should show loading and handle 500/timeouts generically.

### Failed Attempt Error Text

`RateRefreshService::safeError()` stores exception messages as diagnostics. Parser exceptions are currently short and hand-written, but if future parser code throws with provider details, this could leak more text than intended into `last_parse_attempt.error`.

### Provider Filter Options Endpoint Can Fetch On Cache Miss

Although a background job refreshes filter options hourly, the endpoint uses `Cache::remember()` and may call provider APIs on cache miss. This is useful for first use, but it means admin page load can depend on external provider availability if cache is cold.

### Bybit Header Fragility

Bybit parser relies on browser-like headers to avoid edge rejections. This may be brittle if Bybit changes its anti-bot behavior.

### Binance Fetches Up To Five Pages

Binance parser collects up to five pages of 20 rows. If valid ads are sparse after Cortex filters, it may still return empty even though more pages could contain valid ads.

### `composite` Is Historical/Future, Not Current

Some wiki/frontend text mentions `composite`. Current backend enum has no `Composite` case. API validation accepts only `manual`, `bybit`, and `binance`.

### Automatic Update Keeps Old Rate Until Refresh Succeeds

Updating automatic settings queues refresh, but the existing `rate` remains in DB until a successful parse writes a new one. If parsing fails or returns empty, old rate continues to be used. This is intentional for availability, but operators must understand that changing settings does not immediately invalidate the previous ready rate.

## Current State Compared To Wiki

The wiki is broadly accurate on the main architecture:

- database-backed rate sources;
- four-decimal ready rates;
- read path through `ExchangeRateService`;
- cache before DB fallback;
- refresh path separated from read path;
- Bybit/Binance automatic P2P sources;
- `last_parse_attempt`;
- `rate-parsing` queue;
- preview endpoint;
- provider filter options;
- pay-in code reading ready rates only.

Known differences or historical notes:

- `composite` is mentioned as future/older plan but is not currently implemented in `RateSourceType`.
- The early rate-source wiki says HTTP API was initially unauthenticated; current routes are admin-only Sanctum SPA routes.
- `docs/frontend/spa-admin-rate-sources-page.md` describes a first-release manual-only UI and lists `composite`; `docs/frontend/spa-api-docs.md` is more current for Bybit/Binance.
- The automatic source API currently forces `base_currency = USDT` in the controller after validation, but validation still complains if an automatic create/update sends a non-USDT base.

## Practical Operator Flow

For a manual stored source:

1. Admin creates `manual` source with `base_currency = USDT`, target fiat, and `rate`.
2. Backend stores DB rate and warms one-minute cache.
3. Admin links merchant project `settings.pricing.rate_sources.{CURRENCY}` to the source ID.
4. Merchant creates pay-in without `rate`.
5. Cortex reads ready source rate, snapshots it, calculates merchant pricing, and starts provider cascade.

For an automatic Bybit/Binance source:

1. Admin selects provider type, fiat, side, and filters.
2. Admin uses preview to check parser result.
3. Admin saves the source.
4. Backend queues initial refresh.
5. Scheduler continues refreshing active automatic sources every minute.
6. Admin links merchant project after `rate` is ready.
7. Pay-ins use the last ready DB/cache value, not live provider calls.

For merchant API rate:

1. Admin sets merchant project rate source for a currency to `"api"`.
2. Merchant must send `rate` in each pay-in create request for that currency.
3. Cortex validates and snapshots that rate.
4. `rate_source_id` remains null.
5. CTIPay can receive this same fixed rate if provider setting `send_pay_in_rate` is enabled.

## File Map

Core:

- `app/Models/RateSource.php`: Eloquent model and casts.
- `app/Enums/RateSourceType.php`: current source types.
- `app/Enums/RateSourceSide.php`: P2P side enum.
- `app/Domain/Rates/ExchangeRate.php`: validated normalized rate value.
- `app/Domain/Rates/RateParseResult.php`: parser attempt result.
- `app/Services/Rates/ExchangeRateService.php`: read API for other modules.
- `app/Services/Rates/RateStore.php`: one-minute ready-rate cache.
- `app/Services/Rates/RateRefreshService.php`: refresh orchestration, DB lock, diagnostics.
- `app/Services/Rates/RateParserFactory.php`: source type to parser mapping.
- `app/Contracts/Rates/RateParser.php`: parser contract.

Parsers:

- `app/Services/Rates/Parsers/ManualRateParser.php`
- `app/Services/Rates/Parsers/P2pParserSettings.php`
- `app/Services/Rates/Parsers/P2pRateParser.php`
- `app/Services/Rates/Parsers/BybitRateParser.php`
- `app/Services/Rates/Parsers/BinanceRateParser.php`

Admin API:

- `app/Http/Controllers/Api/V1/RateSourceController.php`
- `app/Http/Controllers/Api/V1/RateProviderFilterOptionsController.php`
- `app/Http/Requests/Api/V1/StoreRateSourceRequest.php`
- `app/Http/Requests/Api/V1/UpdateRateSourceRequest.php`
- `app/Http/Requests/Api/V1/PreviewRateSourceRequest.php`
- `app/Http/Resources/Api/V1/RateSourceResource.php`
- `app/Filtering/Sets/RateSourceFilterSet.php`

Jobs and commands:

- `app/Jobs/RefreshRateSourceJob.php`
- `app/Jobs/RefreshRateProviderFilterOptionsJob.php`
- `app/Console/Commands/DispatchRateSourceRefreshes.php`
- `app/Console/Commands/RefreshRateProviderFilterOptions.php`
- `routes/console.php`
- `config/queue.php`
- `config/horizon.php`

Pay-in and pricing:

- `app/Actions/Payments/ResolvePayInRate.php`
- `app/Actions/Payments/CreatePayIn.php`
- `app/Actions/Payments/Testing/CreateTestPayIn.php`
- `app/Domain/Pricing/MerchantPricingSettingsResolver.php`
- `app/Domain/Pricing/MerchantPricingSettings.php`
- `app/Domain/Pricing/MerchantProjectRateSources.php`
- `app/Rules/MerchantProjectRateSourceValue.php`
- `app/Http/Requests/Api/V1/StorePayInRequest.php`
- `app/Domain/Pricing/PayInPricingRecalculator.php`
- `app/Domain/Pricing/MerchantPricingCalculator.php`
- `app/Domain/Pricing/PricingMath.php`
- `app/Jobs/RunProviderAttemptJob.php`
- `app/Services/Providers/Adapters/CtiPayProviderAdapter.php`

Persistence:

- `database/migrations/2026_05_10_132910_create_rate_sources_table.php`
- `database/migrations/2026_05_30_072918_add_automatic_parsing_fields_to_rate_sources_table.php`
- `database/migrations/2026_05_11_025236_create_pay_ins_table.php`
- `database/migrations/2026_05_11_032511_add_public_amount_fields_to_pay_ins_table.php`

