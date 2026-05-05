# Cascade Feature Map (PayIn)

## 1) Цель фичи

Cascade — верхнеуровневый orchestration-слой для PayIn, который:
- принимает сделку от мерчанта через единый API;
- запускает race между внутренним и внешними провайдерами ликвидности;
- атомарно выбирает единственного победителя;
- отменяет проигравшие попытки;
- поддерживает dispute/manual-acquiring/amount changes;
- ведёт полную историю и отправляет callbacks мерчанту.

Ключевой принцип: **не ломать существующий H2H/Merchant Order-контур**, а строить Cascade поверх него через адаптеры и sync/pseudo-callback.

---

## 2) Главные доменные сущности

## `CascadeDeal` (главный агрегат)
- Файл: `app/Models/CascadeDeal.php`
- Хранит:
  - идентификаторы (`uuid`, `external_id`, связи с merchant/client/order);
  - деньги (`amount`, `initial_amount`, `debit`, `credit`, `service_profit`, `usdt_amount`, `fee`, `fee_rate`);
  - статусный контур каскада (собственные enum);
  - выбранного провайдера/транзакцию;
  - dispute/manual-control состояние;
  - callback URL;
  - историю через relation на events.

## `CascadeTransaction` (попытка провайдера)
- Каждая попытка `createDeal` у конкретного провайдера.
- Статусы: `opened`, `failed_to_open`, `accepted`, `cancelled`.

## `CascadeProvider`
- Файл: `app/Models/CascadeProvider.php`
- Глобальные настройки провайдера:
  - `is_active`, `priority` (weight убран из runtime),
  - `timeout` (ограничен до 10),
  - `min_profit_percent`,
  - `provider_type` (`internal`/`external`),
  - интеграционные поля (`base_url`, `access_token`, и т.п.),
  - `user_id` для роли Provider Liquidity.

## `CascadeDealEvent`
- Файл: `app/Models/CascadeDealEvent.php`
- Универсальный event-log по сделке:
  - status/dispute/amount/manual-control/provider-operation/callback/collateral/timeout/error.

## `MerchantCascadeSetting`
- Файл: `app/Models/MerchantCascadeSetting.php`
- Merchant-specific политика каскада:
  - включён ли каскад;
  - можно ли internal/external;
  - whitelist provider IDs.

---

## 3) Enum-контур каскада

Новые enum:
- `app/Enums/CascadeDealStatus.php`
- `app/Enums/CascadeDealSubStatus.php`
- `app/Enums/CascadeDisputeStatus.php`
- `app/Enums/CascadeDealEventType.php`

Зачем:
- отвязать `CascadeDeal` от `OrderStatus` / `OrderSubStatus`;
- держать отдельную, стабильную семантику каскада;
- избежать утечек внутренних sub-status в публичный каскадный API.

---

## 4) DB-эволюция (миграции)

Добавлены миграции:
- `database/migrations/2026_04_28_000001_extend_cascade_deals_for_full_state.php`
  - dispute-поля + история + cancel timestamp.
- `database/migrations/2026_04_28_000002_create_cascade_deal_events_table.php`
  - полная event-хронология.
- `database/migrations/2026_04_28_000003_update_cascade_provider_settings.php`
  - `user_id`, `min_profit_percent`, clamp timeout, remove weight.
- `database/migrations/2026_04_28_000004_create_merchant_cascade_settings_table.php`
  - merchant-specific маршрутизация каскада.
- `database/migrations/2026_04_28_000005_create_provider_liquidity_role.php`
  - роль `Provider Liquidity`.

---

## 5) Runtime-оркестрация

## Точка входа
- `app/Services/Cascade/CascadeService.php::createDeal()`

Что делает:
1. Создаёт `CascadeDeal`.
2. Выбирает активных провайдеров с учётом:
   - глобального `is_active`,
   - merchant-specific flags/whitelist,
   - сортировки по `priority`.
3. Диспатчит `CascadeProviderAttemptJob` для каждого провайдера.
4. Ждёт итог в коротком контуре до `X-Max-Wait-Ms`, но не больше 30 секунд.

## Race / atomic winner
- `app/Jobs/CascadeProviderAttemptJob.php`
- winner selection под `lockForUpdate()` на `cascade_deals`.
- Победитель один; если уже выбран — попытка считается проигравшей.
- Проигравшие сделки отменяются через provider adapter.

## Economics gate
- Для внешнего провайдера:
  - вычисляется merchant obligation;
  - проверка: provider payout >= required amount (`credit + min_profit_percent`).

## Timeout policy
- Merchant wait window: заголовок `X-Max-Wait-Ms` в миллисекундах, максимум и default — 30 000 мс.
- При истечении merchant wait window API возвращает timeout-ошибку, а оркестратор:
  - помечает cascade deal как `provisioning_failed`, если победитель ещё не выбран;
  - ставит cache-флаги `cancel-create` для ещё не стартовавших provider attempts;
  - для уже созданных provider attempts ставит отдельную `CascadeProviderOperationJob` на `cancelDeal` по конкретной `CascadeTransaction`.
- Job timeout: 10 секунд.
- Horizon supervisor для cascade attempts: 8 процессов.
- Отдельная cleanup-линия:
  - `app/Jobs/CascadeInternalTimeoutCleanupJob.php`
  - scheduler в `routes/console.php` (каждые 10 секунд, single queue).

---

## 6) Provider collateral (trust balance)

Сервис:
- `app/Services/Cascade/CascadeProviderCollateralService.php`

Поведение:
1. Только для **external** победителя.
2. После победы удерживает залог с `provider_balance` provider wallet:
   - сумма = `CascadeDeal.usdt_amount` (обязательство перед мерчантом).
3. Удержание хранится как `FundsOnHold` (holdable = `CascadeDeal`).
4. Есть 2 admin-операции:
   - release (вернуть залог),
   - reconcile (пометить как сверенный после ручной сверки).

Транзакционные типы расширены в `app/Enums/TransactionType.php`:
- hold / release / admin deposit / admin withdrawal для provider collateral.

---

## 7) Provider callbacks и callbacks мерчанту

## Internal provider callback
- `app/Observers/OrderObserver.php`
- Если `Order` связан с `CascadeDeal`, вместо legacy H2H callback запускается:
  - `app/Jobs/CascadeInternalProviderCallbackJob.php`.
- Job вызывает `handleCallback()` внутренней интеграции напрямую, без HTTP.
- После нормализации данные проходят через общий cascade provider callback handler.
- Отдельной прямой синхронизации `Order -> CascadeDeal` больше нет.

## Callback мерчанту
- `app/Jobs/SendCascadeDealCallbackJob.php`
- payload: `app/Http/Resources/API/V2/OrderResource.php`.
- Логирование в `CallbackLog` (`TYPE_CASCADE_PAYIN`) + event-log.

---

## 8) API v2 (PayIn / Dispute)

Маршруты:
- `routes/api.php` (`/api/v2/payin...`)
- Удалён merchant `dispute/cancel` маршрут (по новой логике).

Контроллер:
- `app/Http/Controllers/API/V2/DisputeController.php`
  - `store` + `show`, без cancel.

Контракт спора нормализован в `CascadeService`:
- `payin_id`
- `status`
- `reason`
- `canceled_at`

`OrderResource` v2 расширен:
- dispute block;
- manual acquiring block;
- безопасная работа с `gateway/details`;
- единый формат для API/callback.

---

## 9) External operations queue-first

Новый job:
- `app/Jobs/CascadeProviderOperationJob.php`

Перенесены асинхронно для external:
- `cancelDeal`
- `storeConfirmationCode`
- `openDispute`

Идея: API-слой не должен делать прямой блокирующий внешний HTTP-вызов.

---

## 10) Provider Liquidity web-зона

Главная (`/provider-liquidity/main`), как у остальных ролей:
- `App\Http\Controllers\MainPageController::providerLiquidity()` → `resources/js/Pages/MainPage/ProviderLiquidity/Index.vue`
- данные главной: `App\Services\ProviderLiquidity\ProviderLiquidityDashboardService`

Остальные экраны зоны:
- `app/Http/Controllers/ProviderLiquidity/DashboardController.php`
- `resources/js/Pages/ProviderLiquidity/`
  - `Services.vue`
  - `Deals.vue`
  - `Wallet.vue`
  - `Logs.vue`

Навигация и режим:
- `resources/js/Layouts/Partials/ProviderLiquidityMenu.vue`
- `resources/js/Layouts/Partials/ViewModeSwitcher.vue`
- `resources/js/Layouts/AuthenticatedLayout.vue`
- `resources/js/store/view.js`

Маршруты:
- `routes/web.php` (`provider-liquidity.*`)

Назначение:
- безопасный self-service контур провайдера ликвидности
  (свои сделки/баланс/логи, без чувствительной внутренней экономики платформы).

---

## 11) Admin-изменения по каскаду

Обновлены:
- `app/Http/Controllers/Admin/CascadeProviderController.php`
- `app/Http/Controllers/Admin/CascadeDealController.php`
- `app/Http/Resources/TableCascadeProviderResource.php`
- `app/Http/Resources/TableCascadeDealResource.php`
- `resources/js/Pages/Admin/CascadeProviders/Index.vue`

Что добавлено:
- привязка `Provider Liquidity` пользователя к провайдеру (в форме создания/редактирования);
- список провайдеров на `Index.vue` без колонок «тип»/«залог» и без элементов кошелька (таблица только про настройки каскад-провайдера);
- отображение инвойсов/транзакций провайдера ликвидности — через общий `Wallet/Index` у пользователя (как у других ролей), пополнение/вывод админом — через `UserWalletController`;
- min profit %, timeout <= 10, priority-only.

---

## 12) Архитектурные принципы (база)

1. **CascadeDeal — source of truth** для каскадного API.
2. **Atomic winner selection** через DB lock.
3. **At-most-one active provider deal** на один `CascadeDeal`.
4. **Loser cleanup is mandatory** (никаких «висячих» сделок).
5. **Queue-first для внешних интеграций**.
6. **10-second timeout discipline** для каскадных job/worker.
7. **Internal compatibility first**: H2H/Merchant Order flow не ломается.
8. **Event-sourcing-lite**: важные изменения пишутся в `cascade_deal_events`.
9. **Financial safety**:
   - economics gate,
   - collateral hold на `usdt_amount`,
   - ручная сверка залога админом.
10. **Separation of concerns**:
    - provider adapters,
    - orchestration service,
    - sync service,
    - callback jobs,
    - admin/provider UI разделены.

---

## 13) Потоки (кратко)

## Create PayIn
`API v2` -> `CascadeService::createDeal` -> dispatch provider attempts -> atomic winner -> collateral hold (external) -> `CascadeDeal` updated -> callback queued.

## Internal callback
`Order` changed -> `OrderObserver` detects cascade link -> `CascadeInternalProviderCallbackJob` -> `InternalCascadeProvider::handleCallback` -> `CascadeService::handleProviderCallbackPayload` -> update + events + callback queued.

## External callback
`/api/v2/providers/{code}/callback` -> provider adapter normalize -> `CascadeService::handleProviderCallback` -> update deal/transaction + events -> callback queued.

## Dispute
`v2/payin/{uuid}/dispute` -> provider-specific operation (sync internal / queued external) -> normalize to cascade contract -> persist to `CascadeDeal`.

---

## 14) Что важно помнить при дальнейших изменениях

- Если добавляется новая provider integration — она обязана реализовать `CascadeProviderInterface`.
- Между приложением и внешней provider integration должен быть чёткий мост:
  - приложение и orchestration-слой каскада работают только через `CascadeProviderInterface`;
  - вся логика общения с конкретным внешним провайдером через API капсулируется в файле интеграции провайдера;
  - создание/получение/отмена сделки, dispute-операции, confirmation code и обработка provider callback должны нормализоваться внутри provider adapter;
  - внутренняя логика каскада не должна знать HTTP endpoints, payload-форматы, auth-схемы и provider-specific детали конкретной интеграции.
- Любая новая money-операция в каскаде должна:
  - быть атомарной,
  - иметь понятный `TransactionType`,
  - логироваться в deal events.
- Любой новый статусный переход должен проходить через каскадные enum, не через `OrderSubStatus` напрямую.
- Любая внешняя операция должна быть queue-safe и timeout-safe.
- Любая UI-страница провайдера должна оставаться в safe-data режиме.

## 15) Отложенные архитектурные нюансы по границам провайдеров

Эти пункты сейчас не исправляются, но их важно помнить при следующих итерациях, чтобы граница между каскадом и внешними интеграциями оставалась чистой:

- `CascadeService::handleProviderCallback()` пока содержит provider-specific ветку для `SelfTestCascadeProvider` и сам выбирает схему проверки callback token. В идеале callback auth/validation должен быть частью provider adapter или отдельного provider capability.
- `CascadeService` и `CascadeProviderAttemptJob` ветвятся по `ProviderType::INTERNAL/EXTERNAL`. Часть этих веток относится к доменной экономике и collateral, но provider-operation поведение стоит по возможности переносить за интерфейс/capabilities.
- `CascadeProviderInterface` возвращает обычные `array` с нормализованными ключами (`provider_deal_id`, `status`, `settlement`, `dispute` и т.п.). Это рабочий контракт, но со временем лучше заменить его на DTO/Value Objects, чтобы мост был типизированным.
- Внутренние jobs иногда используют интеграционные поля модели провайдера вроде `base_url` как fallback для логирования. Логические URL внешнего API лучше получать только через provider adapter.
- `SelfTestCascadeProvider` завязан на доменную структуру мерчанта для auth (`merchant->user->api_access_token`). Для тестовой интеграции это допустимо, но реальные внешние провайдеры должны держать auth-схему внутри своего adapter/config.

