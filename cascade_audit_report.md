# Аудит cascade-сервиса

Дата аудита: 2026-05-02

## 1. Область проверки

Проверена фактическая реализация каскада по цепочке:

- публичный API v2 PayIn;
- создание `CascadeDeal`;
- выбор и запуск провайдеров;
- provider attempt jobs и фоновые provider operation jobs;
- синхронизация внутренней `Order`-сделки;
- входящие callbacks от провайдеров;
- исходящие callbacks мерчанту;
- dispute/manual acquiring;
- хранение событий, логов, транзакций и залога провайдера;
- админский и provider-liquidity контуры;
- миграции и модели данных.

Основные документы для сравнения:

- `cascade_full_description.md`;
- `cascade_architecture_map.md`;
- `new_cascade_description.md`.

Важное замечание: `cascade_full_description.md` описывает более раннее состояние, где ядро каскада ещё было каркасом. Фактический код сейчас ближе к `cascade_architecture_map.md` и `new_cascade_description.md`: уже есть `/api/v2/payin`, `CascadeProviderAttemptJob`, `CascadeDealEvent`, merchant logs, collateral, provider-liquidity зона и обработка callbacks.

## 2. Фактическая цепочка работы

### 2.1. Создание PayIn

1. Мерчант вызывает `POST /api/v2/payin`.
2. `ApiV2AccessToken` проверяет `Access-Token`, логинит пользователя мерчанта и кладёт `merchant_api_credential` в request attributes.
3. `StoreRequest` валидирует `external_id`, `merchant_id`, сумму, валюту, `payin_method`, `callback_url`, `exchange_rate` и поля manual acquiring.
4. `OrderController::store()` авторизует доступ к мерчанту через `api-v2-access-to-merchant`.
5. Создаётся `CreateCascadeDealDTO`.
6. `CascadeService::createDeal()` сначала создаёт `CascadeDeal`, затем выбирает активных провайдеров.
7. Для каждого провайдера диспатчится `CascadeProviderAttemptJob` в очередь `cascade-provider-attempts`.
8. HTTP-запрос синхронно ждёт результат через cache polling до 10 секунд.
9. При успехе возвращается `OrderResource`.

Ключевые файлы:

- `routes/api.php:73-102`;
- `app/Http/Requests/API/V2/Order/StoreRequest.php`;
- `app/Http/Controllers/API/V2/OrderController.php:62-109`;
- `app/Services/Cascade/CascadeService.php:51-169`;
- `app/Jobs/CascadeProviderAttemptJob.php`.

### 2.2. Race провайдеров

1. `CascadeService::activeCascadeProviders()` создаёт/гарантирует internal-провайдера и выбирает активных провайдеров с учётом merchant settings, whitelist, валют и доступности adapter-класса.
2. `CascadeProviderAttemptJob` создаёт `CascadeTransaction`.
3. Adapter провайдера вызывает `createDeal`.
4. Для external-провайдера проверяется экономика: сумма от провайдера должна покрывать merchant credit плюс `min_profit_percent`.
5. Победитель выбирается атомарно через `lockForUpdate()` на `cascade_deals`.
6. Для external-победителя удерживается provider collateral через `CascadeProviderCollateralService`.
7. Проигравшие попытки отменяются через `cancelDeal`.

Ключевые файлы:

- `app/Services/Cascade/CascadeService.php:963-1007`;
- `app/Jobs/CascadeProviderAttemptJob.php:96-176`;
- `app/Jobs/CascadeProviderAttemptJob.php:184-229`;
- `app/Jobs/CascadeProviderAttemptJob.php:456-604`;
- `app/Services/Cascade/CascadeProviderCollateralService.php`.

### 2.3. Internal provider

Internal provider имитирует H2H-запрос через `H2HStoreRequest` и `services()->orderPooling()->processOrderPooling()`.

Обновления внутренней `Order` ловит `OrderObserver`. Если у `Order` есть связанный `CascadeDeal`, legacy `SendOrderCallbackJob` не запускается, а вызывается `CascadeDealSyncService::syncFromInternalOrder()`.

Ключевые файлы:

- `app/Services/Cascade/Providers/InternalCascadeProvider.php:37-105`;
- `app/Observers/OrderObserver.php:24-42`;
- `app/Services/Cascade/CascadeDealSyncService.php`.

### 2.4. External providers

Реализованы adapters:

- `internal`;
- `mock-processing`;
- `self-test`.

Provider discovery сканирует прямые файлы в `app/Services/Cascade/Providers` и строит map по `CODE`.

Ключевые файлы:

- `app/Services/Cascade/CascadeProviderDiscoveryService.php`;
- `app/Services/Cascade/CascadeProviderService.php`;
- `app/Services/Cascade/Providers/MockProcessingCascadeProvider.php`;
- `app/Services/Cascade/Providers/SelfTestCascadeProvider.php`.

### 2.5. Callbacks

Входящий callback провайдера:

1. `POST /api/v2/providers/{cascadeProvider}/callback` находится вне middleware `api-v2-access-token`.
2. Route binding принимает provider `id` для админки или `code` для API callback.
3. `CascadeService::handleProviderCallback()` нормализует payload через adapter, ищет сделку, проверяет provider token, обновляет `CascadeDeal`/`CascadeTransaction`, пишет `CascadeProviderLog` и запускает `SendCascadeDealCallbackJob`.

Исходящий callback мерчанту:

1. `SendCascadeDealCallbackJob` берёт `OrderResource::make($deal)->resolve()`.
2. Отправляет `POST` на `callback_url`.
3. Пишет `CallbackLog`, `CascadeMerchantLog` и `CascadeDealEvent`.

Ключевые файлы:

- `routes/api.php:102`;
- `app/Providers/AppServiceProvider.php:344-352`;
- `app/Services/Cascade/CascadeService.php:521-606`;
- `app/Jobs/SendCascadeDealCallbackJob.php`.

## 3. Критические проблемы

### 3.1. `external_id` может залипнуть в pending-cache после неуспешной валидации

`StoreRequest` выставляет ключ `pending_cascade_deal_external_id_*` прямо внутри правила `external_id`, до завершения всей валидации:

- `app/Http/Requests/API/V2/Order/StoreRequest.php:77-84`.

Если после этого падает другое правило, например `amount`, `exchange_rate`, `manual_acquiring` или `callback_url`, pending-key не удаляется. Поиск по проекту показывает, что `pendingCascadeDealCacheKey()` используется только в этом request-классе. В результате мерчант может получить ошибку "сделка уже в процессе создания" до часа, хотя сделка не создавалась.

Риск: ложная блокировка `external_id`, плохой UX API, ручные обращения в поддержку.

Рекомендация: не ставить pending-lock в validation rule. Использовать атомарный lock в сервисе создания, с `finally`/TTL и освобождением при ошибке до создания persistent-сделки.

### 3.2. `CascadeDeal` создаётся до проверки доступности провайдеров и может остаться вечной pending-записью

`CascadeService::createDeal()` сначала вызывает `createCascadeDeal()`, а уже потом выбирает провайдеров:

- `app/Services/Cascade/CascadeService.php:67-72`.

Если нет активных провайдеров, нет комиссии, не удалось рассчитать экономику, все attempts упали или произошёл timeout, persistent `CascadeDeal` уже существует. При этом:

- нет перевода сделки в `fail`;
- нет `finished_at`;
- нет cleanup;
- повтор с тем же `external_id` будет заблокирован проверкой существующей записи;
- клиент получает ошибку, но в БД остаётся `pending` сделка без победителя.

Риск: stuck deals, невозможность безопасного retry по тому же `external_id`, расхождение API-ошибки и состояния БД.

Рекомендация: либо создавать `CascadeDeal` внутри транзакционной orchestration-модели с явным fail state, либо при любом окончательном fail переводить сделку в `FAIL/CANCELED` и фиксировать событие. Дополнительно нужен уникальный idempotency contract: что возвращать при повторе на уже созданный, но не завершённый `external_id`.

### 3.3. Нет DB-уникальности для `merchant_id + external_id`

Валидация проверяет уникальность через cache + query, но в миграции `cascade_deals` есть только индекс по `external_id`, без уникального compound index:

- `database/migrations/2026_04_25_184020_create_cascade_deals_table.php:64-68`.

Pending-cache не является атомарной гарантией. При параллельных запросах или нестабильном cache-драйвере возможны дубли одного `external_id` для мерчанта.

Риск: нарушение idempotency API, неоднозначность `showByExternal`, ошибочные callbacks и merchant logs.

Рекомендация: добавить уникальный индекс `merchant_id, external_id` после очистки возможных дублей. На уровне API возвращать существующую сделку или понятный idempotent conflict.

### 3.4. Миграция `2026_04_29_000801_add_supported_currency_codes_to_cascade_providers_table.php` использует несуществующий `each()` на query builder

В миграции:

- `database/migrations/2026_04_29_000801_add_supported_currency_codes_to_cascade_providers_table.php:26-28`.

`DB::table(...)->orderBy(...)->each(...)` для query builder не является корректным API. На чистом прогоне миграций это с высокой вероятностью упадёт.

Риск: невозможность поднять проект с нуля или применить миграции на окружении.

Рекомендация: заменить на `cursor()->each(...)`, `lazyById()` или `get()->each(...)`.

### 3.5. Provider Liquidity получает `access_token` провайдера на фронт

В `ProviderLiquidity\DashboardController::services()` в Inertia payload отдаётся `access_token`:

- `app/Http/Controllers/ProviderLiquidity/DashboardController.php:31-43`.

Это противоречит требованиям `new_cascade_description.md:252-260`, где provider-liquidity пользователь должен видеть только нечувствительные данные.

Риск: утечка секрета интеграции в браузер провайдера, возможность несанкционированных API-запросов.

Рекомендация: убрать `access_token` из provider-liquidity payload. Если нужен индикатор настройки, отдавать boolean вроде `has_access_token`.

## 4. Высокие риски и логические ошибки

### 4.1. External cancel возвращает мерчанту старое состояние

Для external-провайдера `cancelDeal()` только ставит `CascadeProviderOperationJob` и сразу возвращает `$cascadeDeal->refresh()`:

- `app/Services/Cascade/CascadeService.php:197-205`.

`CascadeProviderOperationJob` логирует результат, но не синхронизирует `CascadeDeal` из ответа `cancelDeal`:

- `app/Jobs/CascadeProviderOperationJob.php:63-81`;
- `app/Jobs/CascadeProviderOperationJob.php:83-110`.

Риск: API отвечает `200`, но в теле остаётся прежний `status/sub_status`. Если job упадёт, мерчант не получает прямого сигнала, что отмена не выполнена.

Рекомендация: явно вернуть queued-статус операции или локально переводить сделку в промежуточный state. После выполнения job нужно обновлять `CascadeDeal` и отправлять callback.

### 4.2. Callback от провайдера парсится и ищет сделку до проверки токена

В `handleProviderCallback()` сначала вызываются:

- `$provider->handleCallback($payload)`;
- `resolveCallbackCascadeDeal(...)`;

и только потом проверяется `Access-Token`:

- `app/Services/Cascade/CascadeService.php:523-552`.

Риск: неавторизованный запрос может заставлять систему выполнять adapter parsing и lookup сделок. Это не прямой bypass, но плохой порядок для hardening и rate-abuse.

Рекомендация: если provider token не зависит от найденной сделки, проверять его до нормализации/lookup. Для `self-test`, где сейчас используется токен мерчанта найденной сделки, стоит пересмотреть схему или явно ограничить окружение.

### 4.3. Callback мерчанту отправляется даже когда callback провайдера не обновил сделку

Внутри транзакции обновление `CascadeDeal` происходит только если callback относится к выбранной транзакции или сделка ещё без выбранной транзакции:

- `app/Services/Cascade/CascadeService.php:560-577`.

Но `SendCascadeDealCallbackJob` диспатчится всегда:

- `app/Services/Cascade/CascadeService.php:603`.

Риск: лишние callbacks мерчанту на callback проигравшей/чужой транзакции или повторный callback без изменения состояния.

Рекомендация: диспатчить merchant callback только если сделка реально изменилась или событие должно быть публично доставлено. Добавить флаг `$dealWasUpdated`.

### 4.4. Callback после успешного create PayIn явно не отправляется

Документы описывают, что после создания/обновления `CascadeDeal` должен ставиться callback мерчанту. Фактически после `CascadeService::createDeal()` `OrderController::store()` только возвращает `OrderResource` и пишет incoming merchant log:

- `app/Http/Controllers/API/V2/OrderController.php:94-109`.

Callbacks появляются позже через:

- `OrderObserver` для internal;
- `handleProviderCallback()` для external.

Риск: если external provider сразу создал сделку и больше не присылает callback, мерчант может получить только синхронный ответ API, но не webhook о созданной сделке.

Рекомендация: явно определить контракт. Если callback на создание нужен, запускать `SendCascadeDealCallbackJob` после успешного winner selection.

### 4.5. `CascadeDealSyncService` отправляет callback при любом update внутренней `Order`

`OrderObserver` вызывает sync для любой update-событийной операции, если `Order` связана с `CascadeDeal`:

- `app/Observers/OrderObserver.php:30-37`.

`CascadeDealSyncService` после транзакции всегда диспатчит callback:

- `app/Services/Cascade/CascadeDealSyncService.php:101`.

При этом событие `STATUS_CHANGED` пишется только при смене статуса:

- `app/Services/Cascade/CascadeDealSyncService.php:86-96`.

Риск: callback storm на технические обновления `Order`, повторы одинакового payload без значимых изменений.

Рекомендация: вычислять changed public fields и отправлять callback только при изменении публичного контракта: status, sub_status, amount, dispute, manual_acquiring, finished_at, payin_details.

### 4.6. Несогласованность `usdt_amount` для internal provider

При первичном выборе internal-победителя `usdt_amount` пишется как `merchant_profit`:

- `app/Jobs/CascadeProviderAttemptJob.php:432-441`.

При последующей синхронизации из `Order` `usdt_amount` пишется как `total_profit`:

- `app/Services/Cascade/CascadeDealSyncService.php:67-73`.

Даже в мёртвом методе `cascadeDealWinnerAttributes()` есть та же семантика `merchant_profit`:

- `app/Services/Cascade/CascadeService.php:1029-1038`.

По описанию `new_cascade_description.md:45-48` и `new_cascade_description.md:97-105` `usdt_amount` должен быть общей суммой обязательства/конвертации, а `credit` - суммой к выплате мерчанту.

Риск: скачок `exchanged_amount` в API/callback после первого sync, неверный collateral/экономика при использовании поля downstream.

Рекомендация: унифицировать семантику:

- `usdt_amount` = total converted obligation;
- `credit` = merchant credit after fee;
- `fee` = merchant fee;
- `service_profit` = platform profit.

### 4.7. External amount callback меняет fiat amount, но не пересчитывает USDT/economics/collateral

`callbackCascadeDealAttributes()` при наличии `amount` и `currency` меняет только `amount` и пишет событие `AMOUNT_CHANGED`:

- `app/Services/Cascade/CascadeService.php:1140-1156`.

Не пересчитываются:

- `usdt_amount`;
- `credit`;
- `fee`;
- `service_profit`;
- provider collateral hold;
- expected provider receivable.

Это расходится с `new_cascade_description.md:459-469`.

Риск: при изменении суммы внешним провайдером финансовые поля и залог остаются от старой суммы, callbacks становятся финансово неверными.

Рекомендация: для amount callback запускать единый пересчёт экономики и отдельную процедуру adjustment/reconcile залога.

### 4.8. Не гарантируется отмена всех проигравших сделок

Требование: "гарантированно не должно быть ситуации, когда создано несколько сделок и часть из них не отменена" (`new_cascade_description.md:163-166`).

Фактически `cancelLoser()` ловит исключение отмены, записывает `error_code/error_message`, но не ретраит и не ставит отдельную cancel job:

- `app/Jobs/CascadeProviderAttemptJob.php:246-276`.

Также `CascadeProviderAttemptJob` имеет `$tries = 1`:

- `app/Jobs/CascadeProviderAttemptJob.php:34-36`.

Риск: внешняя проигравшая сделка может остаться активной у провайдера.

Рекомендация: выносить cancel loser в отдельную idempotent job с retry/backoff и явным статусом cleanup. До успешной отмены держать транзакцию в состоянии вроде `cancel_pending/cancel_failed`.

### 4.9. Мёртвый/устаревший код в `CascadeService`

Метод `createInternalProviderDeal()` нигде не вызывается:

- `app/Services/Cascade/CascadeService.php:844-896`.

Он использует отдельный путь и `cascadeDealWinnerAttributes()`, который также не используется в runtime. Это повышает риск, что будущие правки попадут не в тот execution path.

Рекомендация: удалить мёртвый код или явно пометить/перенести в тестовый helper. Основной путь internal уже реализован через `CascadeProviderAttemptJob`.

### 4.10. `queued -> expired` ветка в `createDeal()` недостижима

Условие:

- `app/Services/Cascade/CascadeService.php:124-128`.

Цикл выполняется только пока `$waited < $max_wait_ms`, но проверка требует `$waited > $max_wait_ms + 200`. Эта ветка не сработает.

Риск: misleading code, неверные ожидания по статусу `expired`.

Рекомендация: удалить или исправить условие.

## 5. API-контракт и edge cases

### 5.1. Минимальная сумма не реализована по бизнес-правилам

В `StoreRequest` явно стоит TODO, а `min_amount` зафиксирован как `1`:

- `app/Http/Requests/API/V2/Order/StoreRequest.php:39-41`;
- `app/Http/Requests/API/V2/Order/StoreRequest.php:104`.

Риск: cascade принимает сделки меньше merchant-specific лимитов, хотя старые H2H/merchant контуры могут ожидать другие ограничения.

Рекомендация: реализовать минимальную сумму по `merchant.min_order_amounts[currency]` или явно задокументировать отличие cascade.

### 5.2. Dispute response использует разные форматы `canceled_at`

В `OrderResource`:

- `canceled_at` отдаётся ISO8601: `app/Http/Resources/API/V2/OrderResource.php:54-58`.

В `normalizeDisputeResponse()`:

- `canceled_at` отдаётся Unix timestamp: `app/Services/Cascade/CascadeService.php:656-663`.

Риск: один и тот же публичный контракт dispute отличается в `GET /payin/{uuid}`, callbacks и `GET/POST /payin/{uuid}/dispute`.

Рекомендация: привести к одному формату, лучше ISO8601, как в `OrderResource`.

### 5.3. Base64 receipts декодируются до проверки валидности

`Dispute\StoreRequest::prepareForValidation()` вызывает `base64_decode()` без strict mode и пишет временные файлы до правил `mimes/max`:

- `app/Http/Requests/API/V2/Dispute/StoreRequest.php:29-47`.

Риск: мусорные payload создают временные файлы, возможны пустые/битые `UploadedFile`, нет явной очистки.

Рекомендация: валидировать base64 strict, ограничивать размер строки до декода, чистить tmp-файлы после запроса или использовать managed upload flow.

### 5.4. В `CascadeService` есть `cancelDispute()`, но публичного cascade API для отмены спора нет

Документация говорит, что мерчант не должен отменять спор в cascade (`new_cascade_description.md:136-140`). API route действительно отсутствует. Но сервисный метод `cancelDispute()` остаётся:

- `app/Services/Cascade/CascadeService.php:389-458`.

Риск: будущий код может случайно открыть unsupported path и вернуть отмену спора в cascade API.

Рекомендация: оставить только internal/admin use-case с явным названием или удалить, если отмена спора окончательно запрещена.

### 5.5. `showByExternal` может раскрывать наличие external_id через 404 до Gate

`findDealByExternalId()` делает `firstOrFail()` по merchant UUID и external ID до `Gate`:

- `app/Services/Cascade/CascadeService.php:172-178`;
- `app/Http/Controllers/API/V2/OrderController.php:51-59`.

При строгой модели безопасности лучше сначала авторизовать merchant UUID из токена, потом искать external ID только в его scope.

Риск: слабая информационная утечка/различие ответов по чужому merchant UUID.

Рекомендация: искать только в merchant scope из `merchant_api_credential`, а `merchant_id` в path использовать как дополнительную проверку.

## 6. Jobs, очереди и timeout

### 6.1. Horizon callback supervisor может перебивать retry-настройки job

`SendCascadeDealCallbackJob` задаёт:

- `$tries = 8`;
- `backoff()` с длинной лестницей.

Файл:

- `app/Jobs/SendCascadeDealCallbackJob.php:24-28`;
- `app/Jobs/SendCascadeDealCallbackJob.php:126-129`.

Но supervisor для очереди `callback` имеет `tries => 1`:

- `config/horizon.php:265-276`.

Риск: в зависимости от фактической семантики Horizon/worker options callback может не получить ожидаемые 8 попыток.

Рекомендация: сверить production Horizon config и привести `tries` к контракту job. Для critical callbacks лучше хранить delivery state и next retry.

### 6.2. Provider operation jobs имеют `tries = 1`

`CascadeProviderOperationJob` отвечает за external `cancelDeal`, `storeConfirmationCode`, `openDispute`:

- `app/Jobs/CascadeProviderOperationJob.php:22-24`.

Один сетевой сбой приводит к failed job без автоматического повторения.

Риск: отмена, спор или confirmation code могут потеряться после временной ошибки внешнего API.

Рекомендация: для idempotent external operations добавить retry/backoff и идемпотентные ключи. Для non-idempotent операций явно хранить pending/failed state.

### 6.3. Internal timeout cleanup ищет позднюю Order по хрупким признакам

Cleanup выбирает failed internal transactions по `error_message like '%timeout%'` или `'%вовремя%'`:

- `app/Jobs/CascadeInternalTimeoutCleanupJob.php:43-51`.

Затем ищет последнюю pending `Order` по `merchant_id + external_id`:

- `app/Jobs/CascadeInternalTimeoutCleanupJob.php:62-69`.

Риск: изменение текста ошибки сломает cleanup. При дублях `external_id` или параллельных сценариях можно отменить не ту `Order`.

Рекомендация: хранить структурированный `error_code = timeout`, request correlation id и provider attempt id. Искать внутреннюю `Order` по более строгой связи.

### 6.4. `markAttemptFinished()` зависит от cache-counter без persistent fallback

Завершение orchestration определяется через:

- `Cache::increment`;
- `expected`;
- `finished`.

Файл:

- `app/Jobs/CascadeProviderAttemptJob.php:339-354`.

Риск: при kill/timeout воркера между созданием provider deal и `finally` счётчик может не увеличиться, а синхронный HTTP получит timeout. Persistent transaction state может остаться в промежуточном виде.

Рекомендация: финальный статус orchestration должен вычисляться из БД как fallback: количество transactions по deal, terminal statuses, selected_transaction_id.

## 7. Provider layer и настройки

### 7.1. Дубликаты `cascade_providers.code` опасны

Первичная миграция делала `code` unique:

- `database/migrations/2026_04_25_183023_create_cascade_providers_table.php:18`.

Позже unique снят:

- `database/migrations/2026_04_29_034900_drop_unique_index_from_cascade_providers_code.php:14-16`.

При этом:

- route binding callback ищет `where('code', $value)->firstOrFail()`;
- `CascadeProviderService` кеширует adapter instance по `$provider->code`.

Файлы:

- `app/Providers/AppServiceProvider.php:344-352`;
- `app/Services/Cascade/CascadeProviderService.php:57-67`.

Риск: при нескольких строках с одним `code` callbacks и adapter config могут уйти не в того провайдера. Особенно критично для external callbacks и токенов.

Рекомендация: вернуть уникальность `code`, если одна интеграция = один adapter/config. Если нужны несколько инстансов одного adapter-класса, разделить `code` adapter-а и уникальный `slug`/`instance_code` провайдера.

### 7.2. Provider discovery не видит подпапки

`CascadeProviderDiscoveryService` сканирует только прямые файлы:

- `app/Services/Cascade/CascadeProviderDiscoveryService.php:18-24`.

Риск: adapter в подпапке не попадёт в class map, provider silently отфильтруется в `activeCascadeProviders()`.

Рекомендация: либо задокументировать flat-only правило, либо использовать recursive discovery.

### 7.3. `config` и `weight` в `cascade_providers` стали мёртвыми полями

Первая миграция создаёт `weight` и `config`:

- `database/migrations/2026_04_25_183023_create_cascade_providers_table.php:26-30`.

Модель `CascadeProvider` их не содержит в `$fillable`/casts и runtime использует отдельные поля:

- `app/Models/CascadeProvider.php:45-70`.

Риск: технический долг и путаница в админке/миграциях.

Рекомендация: удалить неиспользуемые поля отдельной миграцией или явно задокументировать reserved-purpose.

### 7.4. `getAvailableProviderCodes()` возвращает коды из БД, а не реализованные integration codes

В `CascadeProviderService`:

- `getAvailableProviderCodes()` возвращает `CascadeProvider::pluck('code')`;
- `getAvailableIntegrationCodes()` возвращает class map.

Файл:

- `app/Services/Cascade/CascadeProviderService.php:110-128`.

Риск: названия методов легко перепутать при дальнейшем развитии.

Рекомендация: переименовать в `registeredProviderCodes()` и `implementedIntegrationCodes()`.

## 8. Данные, ресурсы и UI

### 8.1. Provider Liquidity фильтрует amount как USDT, хотя `cascade_deals.amount` хранится в валюте сделки

В фильтре:

- `app/Http/Controllers/ProviderLiquidity/DashboardController.php:61-63`.

`amount` приводится через `Currency::USDT()`, но `CascadeDeal.amount` кастуется через валюту строки сделки:

- `app/Models/CascadeDeal.php:127-128`.

Риск: фильтр суммы в provider-liquidity deals неверен для RUB/других фиатных валют.

Рекомендация: фильтровать по `amount` в валюте сделки с отдельным фильтром currency или фильтровать по `usdt_amount`, если UI ожидает USDT.

### 8.2. `CascadeDealEventRecorder` и collateral/sync сервисы создаются через `new` в default constructor argument

Примеры:

- `app/Services/Cascade/CascadeDealSyncService.php:22-24`;
- `app/Services/Cascade/CascadeProviderCollateralService.php:22-24`.

Риск: обход контейнера Laravel. Если recorder получит зависимости, singleton/config/decorator не сработают.

Рекомендация: инжектить через контейнер без default `new`.

### 8.3. Merchant cascade settings хранят whitelist как JSON без ссылочной целостности

`allowed_provider_ids` валидируется request-ом, но в БД это произвольный JSON:

- `database/migrations/2026_04_28_000004_create_merchant_cascade_settings_table.php:17`;
- `app/Models/MerchantCascadeSetting.php:20-25`.

Риск: после удаления провайдера в whitelist остаются висящие id.

Рекомендация: для долгосрочной поддержки лучше pivot-таблица `merchant_cascade_provider` или cleanup при удалении provider.

### 8.4. В `cascade_deals` нет индекса по `order_id`

`OrderObserver` ищет связанный `CascadeDeal` по `order_id`:

- `app/Observers/OrderObserver.php:30-32`.

В миграции `cascade_deals` индекса по `order_id` явно нет:

- `database/migrations/2026_04_25_184020_create_cascade_deals_table.php:64-71`.

Foreign key может создать индекс в MySQL, но это зависит от фактической схемы/миграции. Стоит проверить `SHOW INDEX`.

Рекомендация: убедиться в индексе по `order_id`, так как observer вызывается часто.

## 9. Логи и аудит

### 9.1. Provider logs пишутся, но timeout/fail контекст неполный

`CascadeProviderAttemptJob` пишет `CascadeProviderLog` на create/cancel и failure, `CascadeProviderOperationJob` пишет операции, `handleProviderCallback()` пишет callback. Это закрывает большую часть аудита.

Пробелы:

- timeout классифицируется текстом ошибки, а не структурированным кодом;
- при `cancelLoser()` exception записывается в transaction, но не создаётся отдельный failed provider log для cancel;
- `status_code` для adapter-вызовов часто отсутствует, потому что adapters возвращают нормализованный payload, а не HTTP metadata.

Рекомендация: унифицировать adapter response envelope: `raw`, `status_code`, `duration`, `request_id`.

### 9.2. CallbackLog создаётся только после успешного HTTP response

В `SendCascadeDealCallbackJob` `CallbackLog` создаётся после `$http->post()`:

- `app/Jobs/SendCascadeDealCallbackJob.php:62-74`.

Если исключение происходит до получения response, пишется только `RecordCascadeMerchantLogJob`, но не `CallbackLog`.

Риск: два разных журнала дают разную картину delivery attempts.

Рекомендация: писать `CallbackLog` и для transport exceptions, если модель журнала поддерживает `status_code = null`.

## 10. Соответствие требованиям

### Реализовано хорошо

- Отдельная модель `CascadeDeal` как source of truth.
- Собственные cascade enum для статусов и dispute.
- Route namespace `/api/v2/payin`, отделённый от legacy `merchant/order` и `h2h/order`.
- Adapter-интерфейс для internal/external providers.
- Atomic winner selection через `lockForUpdate()`.
- Provider attempts через отдельную очередь `cascade-provider-attempts`.
- Horizon supervisor на 8 процессов для cascade attempts.
- Отдельная очередь и job для cleanup late internal orders.
- Event log `cascade_deal_events`.
- Provider logs и merchant logs.
- Provider collateral hold для external winner.
- Provider-liquidity зона с ограничением по `user_id`.

### Реализовано частично

- Idempotency по `external_id`: есть request-level проверка, но нет DB guarantee и корректного retry contract.
- Callback мерчанту: есть job, но dispatch не всегда привязан к реальному изменению публичного состояния.
- External operations queue-first: операции уходят в очередь, но нет retry/state machine для pending/failed.
- Amount changes от external callbacks: событие есть, financial recalculation отсутствует.
- Disputes: открытие/получение есть, но response format расходится с `OrderResource`; receipts хранятся только агрегированно в history.
- Internal timeout cleanup: есть, но relies on text matching и слабую корреляцию.

### Не закрыто или требует решения

- Что делать с `CascadeDeal`, созданной до полного failure orchestration.
- Полный idempotent retry contract для `POST /api/v2/payin`.
- Гарантированная отмена проигравших external deals.
- Структурированный delivery state для callbacks мерчанту.
- Reconcile/adjustment логика provider collateral после изменения суммы или фактической сверки.
- Безопасная модель нескольких инстансов одного provider adapter-а.

## 11. Рекомендуемый порядок исправлений

### P0

1. Исправить миграцию `2026_04_29_000801_add_supported_currency_codes_to_cascade_providers_table.php`.
2. Убрать `access_token` из provider-liquidity Inertia payload.
3. Перенести pending/idempotency lock из `StoreRequest` в сервис и гарантированно освобождать его.
4. Определить fail-state для созданных, но не завершённых `CascadeDeal`; не оставлять их вечными `pending`.
5. Добавить DB-level уникальность `merchant_id + external_id` или другой строгий idempotency mechanism.

### P1

1. Исправить `usdt_amount` semantics для internal provider.
2. Добавить reliable retry/cancel cleanup для проигравших provider deals.
3. Исправить external cancel response/state: `queued`, `cancel_pending`, callback после выполнения.
4. Отправлять merchant callback только при изменении публичного состояния.
5. Привести dispute `canceled_at` к одному формату.
6. Исправить base64 validation/cleanup для dispute receipts.

### P2

1. Удалить/перенести мёртвый код `createInternalProviderDeal()` и `cascadeDealWinnerAttributes()`.
2. Уточнить модель provider `code`: вернуть уникальность или разделить adapter code и instance code.
3. Добавить structured timeout/error codes.
4. Уточнить callback-on-create contract.
5. Исправить amount filter в provider-liquidity deals.
6. Убрать default `new CascadeDealEventRecorder` из конструкторов сервисов.

## 12. Итог

Текущая реализация уже значительно ближе к полноценному cascade-сервису, чем старое описание в `cascade_full_description.md`: есть отдельная API-линия PayIn, провайдерный race, atomic winner selection, callbacks, logs, events и collateral.

Главные проблемы сейчас не в отсутствии каркаса, а в консистентности production-потока:

- idempotency и retry могут оставлять stuck `CascadeDeal`;
- external operations не имеют надёжной state machine;
- callbacks могут отправляться лишний раз или не отправляться после create;
- часть финансовых полей расходится по семантике;
- есть несколько security/data issues в provider-liquidity и миграциях.

Перед production-включением каскада стоит закрыть P0/P1 пункты, иначе высок риск зависших сделок, неверных повторов по `external_id`, неполных отмен у внешних провайдеров и некорректных callbacks мерчанту.
