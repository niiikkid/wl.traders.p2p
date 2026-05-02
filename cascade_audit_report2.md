# Cascade Audit Report 2

Дата аудита: 2026-05-02

## 1. Scope

Проверен текущий Cascade PayIn end-to-end по документам:

- `new_cascade_description.md`
- `cascade_architecture_map.md`

Проверялись:

- API v2 PayIn / Dispute / Provider Callback;
- `CascadeService` и provider adapters;
- race-логика и выбор победителя;
- job-и, очереди, Horizon, scheduler;
- callbacks мерчанту и callbacks от провайдера;
- sync внутреннего провайдера через `OrderObserver`;
- модели, миграции, enum-ы, залог внешнего провайдера;
- edge cases: idempotency, timeout, duplicate callbacks, dispute receipts, amount changes.

Автоматические тесты не запускались: задача была на аудит и создание отчёта, а проектные правила запрещают запуск тестов без явного запроса.

## 2. Executive Summary

В каскаде уже есть важная основа: PayIn API вынесен в `/api/v2/payin`, публичного cancel dispute маршрута нет, winner selection сделан через `lockForUpdate()`, внешние provider operations частично вынесены в очередь, для cascade attempts настроен Horizon supervisor на 8 процессов, а внутренний провайдер подключён через adapter.

Основные риски сейчас:

- создание PayIn не является надёжно идемпотентным по `merchant_id + external_id`;
- `pending external_id` lock может залипать на 1 час после невалидного запроса;
- часть внешних операций возвращает клиенту успех/queued, но не обновляет `CascadeDeal` после выполнения job;
- у внутреннего провайдера расходится семантика `usdt_amount`;
- callback-и могут отправляться без фактического изменения состояния;
- provider amount callback не пересчитывает USDT-экономику и залог;
- loser cancellation не гарантирует финального статуса транзакции при ошибке отмены;
- dispute flow для внешнего провайдера локально помечает спор открытым до подтверждения провайдера.

## 3. Что Реализовано Корректно

- API v2 использует сегмент `payin`: `routes/api.php`.
- В v2 PayIn есть `store`, `show`, `showByExternal`, `cancel`, `confirmation-code`, `dispute store/show`.
- В публичном v2 API нет маршрута отмены спора мерчантом, что соответствует требованию.
- `CascadeProviderAttemptJob::tryAcceptWinner()` использует `lockForUpdate()` и проверяет `selected_transaction_id`, поэтому победитель атомарно выбирается один.
- `config/horizon.php` содержит `supervisor-cascade-provider-attempts` с `maxProcesses = 8` и `timeout = 10`.
- `routes/console.php` запускает `CascadeInternalTimeoutCleanupJob` каждые 10 секунд с `withoutOverlapping()`.
- Для внешнего победителя collateral hold идёт после winner selection через `CascadeProviderCollateralService::holdForWinner()`.
- Для внешнего provider timeout clamp ограничен до 10 секунд в `CascadeProviderService`.
- `OrderResource` используется и для API-ответов, и для cascade callbacks.

## 4. Findings

### CRITICAL-1. Создание PayIn не защищено уникальным индексом по `merchant_id + external_id`

Файлы:

- `app/Http/Requests/API/V2/Order/StoreRequest.php`
- `app/Services/Cascade/CascadeService.php`
- `database/migrations/2026_04_25_184020_create_cascade_deals_table.php`

Проблема:

- `StoreRequest` проверяет существование `external_id` через cache/DB;
- в БД есть только обычный индекс `external_id`;
- уникального ограничения `(merchant_id, external_id)` нет;
- `CascadeService::createCascadeDeal()` делает обычный `CascadeDeal::create()`.

Сценарий:

Два параллельных `POST /api/v2/payin` с одинаковыми `merchant_id` и `external_id` могут одновременно пройти validation и создать две разные `CascadeDeal`. После этого `showByExternal()` использует `firstOrFail()` и возвращает произвольную первую сделку.

Риск:

Дубли денежных обязательств, двойной race провайдеров, двойное занятие реквизитов/залогов, неоднозначный lookup по external ID.

Рекомендация:

- добавить уникальный индекс `(merchant_id, external_id)`;
- перенести идемпотентность в сервис/транзакцию;
- повторный create с тем же external ID должен возвращать существующую сделку или стабильный `409 Conflict`, а не создавать новую.

### CRITICAL-2. `pending external_id` lock ставится во время validation и может залипнуть на 1 час

Файл:

- `app/Http/Requests/API/V2/Order/StoreRequest.php`

Проблема:

Closure для `external_id` вызывает `Cache::put($pending_key, true, 60 * 60)` до завершения полной валидации запроса. Если потом падает `amount`, `currency`, `exchange_rate`, `manual_acquiring` или любое другое поле, pending lock не снимается. Поиск по проекту не нашёл `Cache::forget()` для `pendingCascadeDealCacheKey()`.

Сценарий:

1. Мерчант отправляет валидный `external_id`, но невалидный `amount`.
2. Validation падает.
3. Повтор с тем же `external_id` получает ошибку "Сделка с таким external_id уже в процессе создания" в течение часа.

Риск:

Легитимные retries блокируются, а поддержка видит "в процессе создания" без реальной сделки.

Рекомендация:

- не ставить pending lock в validation closure;
- ставить lock в `CascadeService::createDeal()` после полной валидации;
- снимать lock в `finally`;
- защитить БД уникальным индексом из CRITICAL-1.

### HIGH-1. Внутренний провайдер записывает неверную семантику `usdt_amount`

Файлы:

- `app/Jobs/CascadeProviderAttemptJob.php`
- `app/Services/Cascade/CascadeService.php`
- `app/Services/Cascade/CascadeDealSyncService.php`
- `app/Models/CascadeDeal.php`
- `app/Http/Resources/API/V2/OrderResource.php`

Проблема:

Для external winner `usdt_amount` заполняется как `convertedAmount`, то есть полная сумма после конвертации. `CascadeDeal` PHPDoc описывает это поле так же: "Сумма amount после конвертации по курсу в USDT".

Но для internal winner:

- `CascadeProviderAttemptJob::winnerAttributes()` пишет `usdt_amount => $order->merchant_profit`;
- `CascadeService::cascadeDealWinnerAttributes()` делает то же;
- `CascadeDealSyncService::syncFromInternalOrder()` позже перезаписывает `usdt_amount => $order->total_profit`.

Сценарий:

Сразу после победы внутреннего провайдера `OrderResource.amounts.exchanged_amount` отдаёт сумму после комиссии (`merchant_profit`), а после первого sync начинает отдавать другую сумму (`total_profit`).

Риск:

Плавающие суммы в API/callbacks, некорректные отчёты, неверная интерпретация merchant obligation, потенциальные ошибки при будущей логике залога/сверки.

Рекомендация:

- закрепить доменную семантику `usdt_amount`;
- для internal winner использовать тот же смысл, что в sync и external ветке;
- вынести маппинг финансовых полей внутреннего `Order` в один helper, чтобы job и sync не расходились.

### HIGH-2. External cancel не обновляет `CascadeDeal`

Файлы:

- `app/Services/Cascade/CascadeService.php`
- `app/Jobs/CascadeProviderOperationJob.php`
- `app/Http/Controllers/API/V2/OrderController.php`

Проблема:

`CascadeService::cancelDeal()` для external provider только dispatch-ит `CascadeProviderOperationJob('cancelDeal')` и сразу возвращает `$cascadeDeal->refresh()`. `CascadeProviderOperationJob` при успешном `cancelDeal` только пишет provider log и event, но не меняет статус `CascadeDeal` и `CascadeTransaction`.

Сценарий:

Мерчант вызывает `PATCH /api/v2/payin/{uuid}/cancel` по external deal. API возвращает старое состояние, job отменяет сделку у провайдера, но локальный `CascadeDeal` остаётся pending до внешнего callback. Если callback не придёт, локальная сделка зависнет.

Риск:

API и провайдер расходятся; merchant callbacks не отражают отмену; админка видит активную сделку, которая уже отменена во внешнем сервисе.

Рекомендация:

- после успешного external `cancelDeal` обновлять `CascadeDeal` и selected transaction в job;
- записывать `STATUS_CHANGED`/`PROVIDER_OPERATION`;
- отправлять merchant callback только после фактического обновления;
- определить API-контракт: `202 queued` или синхронный финальный статус.

### HIGH-3. External dispute open локально считается открытым до подтверждения провайдера

Файлы:

- `app/Services/Cascade/CascadeService.php`
- `app/Jobs/CascadeProviderOperationJob.php`

Проблема:

Для external provider `openDispute()` сначала вызывает `rememberCascadeDispute()` с локальным payload `status = opened`, затем ставит `CascadeProviderOperationJob('openDispute')` и сразу возвращает success. Если job потом упадёт или провайдер отклонит спор, `CascadeDeal` уже хранит dispute как opened. При успехе job обновляет только `selectedTransaction.response_payload['dispute']`, но не нормализует результат обратно в `CascadeDeal`.

Сценарий:

Мерчант получил успешный ответ на открытие спора, но внешний API недоступен. Локально спор открыт, у провайдера спора нет.

Риск:

Расхождение статуса спора между каскадом и провайдером, неверные callback-и и админская история.

Рекомендация:

- для external возвращать `202 queued` с явной семантикой;
- локально хранить промежуточный статус вроде `opening`, либо не менять финальный dispute state до ответа provider job;
- в `CascadeProviderOperationJob` после `openDispute` вызывать нормализацию и update `CascadeDeal`;
- предусмотреть failed state/alert при ошибке job.

### HIGH-4. Ошибка отмены проигравшей попытки оставляет транзакцию неотменённой

Файл:

- `app/Jobs/CascadeProviderAttemptJob.php`

Проблема:

`cancelLoser()` при успешной отмене ставит transaction status `CANCELLED`. Но если `provider->cancelDeal()` бросает исключение, catch пишет только `error_code` и `error_message`, не меняя `status`.

Сценарий:

Провайдер успел создать сделку, но проиграл race. Отмена у провайдера вернула timeout/500. Локальная `CascadeTransaction` остаётся в `OPENED` с ошибкой.

Риск:

Нарушается требование "проигравшие созданные сделки должны быть отменены". Внешняя сделка может остаться живой, а локальный статус не отражает обязательную cleanup-проблему.

Рекомендация:

- ввести отдельный статус/флаг `cancel_failed` или переводить в `FAILED_TO_OPEN` с понятным `error_code`;
- ставить повторную `CascadeProviderOperationJob('cancelDeal')` с retry/backoff;
- сделать отмену loser идемпотентной и наблюдаемой в админских логах.

### HIGH-5. Callback provider amount меняет только fiat `amount`, но не пересчитывает USDT-экономику и залог

Файл:

- `app/Services/Cascade/CascadeService.php`

Проблема:

`callbackCascadeDealAttributes()` при наличии `amount` в provider callback обновляет только fiat `amount` и пишет event `AMOUNT_CHANGED`. Поля `usdt_amount`, `credit`, `fee`, `service_profit`, `debit`, collateral hold и merchant obligation не пересчитываются.

Сценарий:

Внешний провайдер присылает callback с новой суммой. В `CascadeDeal.amount` уже новая сумма, но `OrderResource.amounts.exchanged_amount` и `merchant_credit` остаются старыми.

Риск:

Финансовое состояние агрегата становится внутренне противоречивым; залог провайдера и обязательства перед мерчантом могут не соответствовать фактической сумме.

Рекомендация:

- после изменения amount запускать единый economics recalculation pipeline;
- фиксировать историю изменения суммы;
- пересматривать collateral hold/reconcile policy;
- отправлять callback только после консистентного обновления всех связанных финансовых полей.

### HIGH-6. Внутренний sync отправляет callback при любом update `Order`

Файлы:

- `app/Observers/OrderObserver.php`
- `app/Services/Cascade/CascadeDealSyncService.php`

Проблема:

`OrderObserver::updated()` проверяет только факт существования `CascadeDeal` по `order_id` и вызывает `syncFromInternalOrder()` на каждое обновление `Order`. `CascadeDealSyncService` после sync всегда делает `SendCascadeDealCallbackJob::dispatch($deal)`, даже если публичные поля не изменились.

Сценарий:

У внутреннего `Order` меняется служебное поле, не влияющее на API PayIn. Каскад всё равно обновляет deal и отправляет callback мерчанту.

Риск:

Дубли webhook’ов, шум в `CallbackLog`, лишняя нагрузка на callback queue, сложная идемпотентность на стороне мерчанта.

Рекомендация:

- запускать sync только по meaningful fields (`status`, `sub_status`, `amount`, `manual_control_*`, dispute fields, finished_at);
- отправлять callback только если изменился публичный payload или revision;
- добавить business event/revision для callback idempotency.

### MEDIUM-1. `handleProviderCallback()` отправляет merchant callback даже без изменения `CascadeDeal`

Файл:

- `app/Services/Cascade/CascadeService.php`

Проблема:

Внутри transaction update `CascadeDeal` выполняется только если callback относится к selected transaction или если selected transaction ещё нет. Но после transaction `SendCascadeDealCallbackJob::dispatch($cascade_deal->refresh())` вызывается всегда.

Сценарий:

Приходит callback от проигравшей/неактуальной транзакции или duplicate callback с тем же состоянием. Локальный `CascadeDeal` не меняется, но merchant callback всё равно уходит.

Риск:

Дублирование webhook’ов и ложные события у мерчанта.

Рекомендация:

- диспатчить merchant callback только при фактическом изменении публичного состояния;
- хранить `callback_payload_hash` или `status_revision`.

### MEDIUM-2. После успешного winner selection нет обязательного callback мерчанту

Файлы:

- `app/Jobs/CascadeProviderAttemptJob.php`
- `app/Services/Cascade/CascadeService.php`
- `app/Http/Controllers/API/V2/OrderController.php`

Проблема:

Поиск `SendCascadeDealCallbackJob::dispatch` показывает отправку только из provider callback, internal sync и ручного resend. После `tryAcceptWinner()` callback не ставится.

Сценарий:

Создан PayIn, провайдер выбран, реквизиты получены, API вернул response. Если merchant ожидает webhook при создании/выборе провайдера, он его не получит.

Риск:

Расхождение с `cascade_architecture_map.md`, где create flow заканчивается callback queued. Особенно критично для интеграторов, которые полагаются на webhooks, а не polling.

Рекомендация:

- после winner selection записывать event и ставить initial merchant callback;
- либо явно документировать, что create response заменяет первый callback.

### MEDIUM-3. External `getDispute()` не обращается к провайдеру

Файл:

- `app/Services/Cascade/CascadeService.php`

Проблема:

Для external provider `getDispute()` возвращает локальную нормализацию из `CascadeDeal`, не вызывает adapter `getDispute()`.

Сценарий:

Провайдер уже принял или отклонил спор, но callback ещё не пришёл. `GET /api/v2/payin/{uuid}/dispute` отдаёт устаревший локальный статус.

Риск:

API не отражает состояние провайдера и может вводить мерчанта в заблуждение.

Рекомендация:

- либо явно задокументировать endpoint как "local snapshot";
- либо добавить queued/sync refresh с провайдера;
- при ручном запросе админа/мерчанта можно запускать короткую синхронизацию с timeout.

### MEDIUM-4. External provider operation jobs не имеют retry/backoff

Файл:

- `app/Jobs/CascadeProviderOperationJob.php`

Проблема:

`CascadeProviderOperationJob` имеет `tries = 1`. При сетевой ошибке `cancelDeal`, `openDispute`, `storeConfirmationCode` исключение пробрасывается, лог пишется, но автоматического retry нет.

Риск:

Временный сбой внешнего API превращается в постоянное расхождение состояния.

Рекомендация:

- задать retry/backoff;
- добавить dead-letter/alert;
- сделать операции идемпотентными по provider operation key.

### MEDIUM-5. Внешняя экономика пишется в `CascadeDeal` до выбора победителя

Файл:

- `app/Jobs/CascadeProviderAttemptJob.php`

Проблема:

Каждая external attempt вызывает `persistExternalMerchantEconomics()` и обновляет `cascade_deals` до winner selection, без `lockForUpdate()`. Обновление защищено только `whereNull('selected_transaction_id')`.

Сценарий:

Несколько external jobs параллельно перезаписывают `usdt_amount`, `fee`, `fee_rate`, `credit` до выбора победителя. Значения в теории одинаковые для одного deal, но сама модель получает промежуточные writes до финальной привязки.

Риск:

Непредсказуемые промежуточные чтения, лишние writes, потенциальные расхождения при будущих provider-specific economics.

Рекомендация:

- хранить attempt economics в `CascadeTransaction`;
- переносить в `CascadeDeal` только под winner lock;
- либо выполнять update под той же DB transaction, что и выбор победителя.

### MEDIUM-6. `cascade_enabled = false` создаёт сделку, а потом падает как "нет активных провайдеров"

Файлы:

- `app/Services/Cascade/CascadeService.php`
- `app/Models/MerchantCascadeSetting.php`

Проблема:

`createDeal()` сначала создаёт `CascadeDeal`, а потом вызывает `activeCascadeProviders()`. Если `MerchantCascadeSetting.cascade_enabled = false`, провайдеры возвращаются пустыми, и API падает с "Нет активных провайдеров каскада".

Риск:

В БД остаётся созданная, но не обработанная сделка. Сообщение выглядит как инфраструктурная ошибка, хотя это merchant policy.

Рекомендация:

- проверять merchant cascade policy до создания `CascadeDeal`;
- возвращать явную ошибку "cascade disabled for merchant";
- не резервировать `external_id`, если политика запрещает каскад.

### MEDIUM-7. Нет настройки "manual acquiring только во внешние провайдеры"

Файлы:

- `app/Models/MerchantCascadeSetting.php`
- `app/Services/Cascade/CascadeService.php`

Проблема:

В настройках есть только общие `allow_internal_providers` и `allow_external_providers`. `activeCascadeProviders()` не учитывает, что конкретная сделка является manual acquiring. В ТЗ описан сценарий, когда ручные сделки мерчанта нужно не отправлять во внутреннего провайдера.

Риск:

Manual flow может уйти во внутренний provider, хотя бизнес-политика ожидала only-external routing.

Рекомендация:

- добавить policy flag для manual acquiring routing;
- фильтровать providers с учётом `CascadeDeal.manual_control`.

### MEDIUM-8. Dispute receipts не ограничены тремя файлами и декодируются до validation

Файл:

- `app/Http/Requests/API/V2/Dispute/StoreRequest.php`

Проблема:

Правило `receipts` содержит `required|array`, но нет `max:3`. При этом `prepareForValidation()` сначала проходит по всему массиву, делает `base64_decode()`, пишет временный файл и создаёт `UploadedFile`, а уже потом включается validation.

Риск:

Большой массив receipt-ов может потреблять память/диск до validation. Это расходится с требованием "до трёх чеков".

Рекомендация:

- добавить `max:3`;
- валидировать base64 строго (`base64_decode(..., true)`);
- ограничить размер исходной строки до записи во временный файл;
- очищать temporary files после обработки.

### MEDIUM-9. Cleanup internal timeout ищет timeout по тексту ошибки

Файл:

- `app/Jobs/CascadeInternalTimeoutCleanupJob.php`

Проблема:

Job выбирает транзакции по `error_message LIKE '%timeout%' OR '%вовремя%'`.

Сценарий:

Если timeout записан как `cURL error 28`, `Connection timed out`, кастомная русская формулировка или wrapped exception, cleanup может не найти поздно созданный internal `Order`.

Риск:

Внутренний Order останется активным и будет держать реквизиты, хотя cascade attempt уже failed/expired.

Рекомендация:

- записывать структурированный `error_code = timeout`;
- фильтровать cleanup по code/type, а не по message;
- добавить отдельный флаг `timed_out`.

### MEDIUM-10. `createDeal()` удерживает HTTP-запрос до 10 секунд

Файл:

- `app/Services/Cascade/CascadeService.php`

Проблема:

API create ставит jobs, затем в том же HTTP request делает polling cache через `usleep()` до 10 секунд.

Риск:

При нагрузке PHP worker удерживается до глобального timeout; это повышает вероятность очередей на web-слое. По ТЗ внешние обращения должны быть queue-first, и текущая схема частично queue-first, но HTTP всё равно ждёт результат race.

Рекомендация:

- рассмотреть async API контракт: `202 Accepted + polling/callback`;
- если синхронный ответ нужен, сделать это явной частью контракта и ограничить short wait;
- убрать недостижимую ветку `queued && waited > max_wait_ms + interval`.

### MEDIUM-11. `StoreConfirmationCode` для external ставит job, но не синхронизирует результат

Файлы:

- `app/Services/Cascade/CascadeService.php`
- `app/Jobs/CascadeProviderOperationJob.php`

Проблема:

Для external `storeConfirmationCode()` возвращает `queued`. Job вызывает provider method и логирует результат, но не обновляет `CascadeDeal.manual_control`, selected transaction state или merchant callback.

Риск:

Мерчант не получает финальный результат операции, а локальная модель не отражает provider response.

Рекомендация:

- определить финальный контракт operation response;
- обновлять selected transaction / manual control status из job;
- отправлять callback при изменении публичного состояния.

### LOW-1. Формат `dispute.canceled_at` различается между Dispute API и `OrderResource`

Файлы:

- `app/Services/Cascade/CascadeService.php`
- `app/Http/Resources/API/V2/OrderResource.php`

Проблема:

`normalizeDisputeResponse()` отдаёт `canceled_at` как Unix timestamp, а `OrderResource.dispute.canceled_at` отдаёт ISO-8601 string.

Риск:

Интеграторы получают разные типы одного поля в разных endpoint/callback.

Рекомендация:

Унифицировать формат. Лучше ISO-8601, так как ресурс уже отдаёт даты в этом формате.

### LOW-2. В споре сохраняется только count чеков, не сами receipt metadata

Файл:

- `app/Services/Cascade/CascadeService.php`

Проблема:

`rememberCascadeDispute()` добавляет в `dispute_receipts` только `count` и `stored_at`.

Риск:

Требование хранить всю историю спора и чеков в `CascadeDeal` выполнено неполно. Для админского аудита нельзя понять, какие файлы были отправлены.

Рекомендация:

Сохранять безопасные metadata: имя, mime, размер, storage path/hash, created_at. Для внутреннего провайдера можно хранить все metadata, даже если в `Order` ушёл только первый чек.

### LOW-3. `markReconciled()` для collateral hold не транзакционный

Файл:

- `app/Services/Cascade/CascadeProviderCollateralService.php`

Проблема:

`holdForWinner()` и `release()` обёрнуты в `Transaction::run()`, а `markReconciled()` сначала обновляет hold, потом пишет event без транзакции.

Риск:

При ошибке между update и event audit-log расходится с состоянием hold.

Рекомендация:

Оформить `markReconciled()` через `Transaction::run()`.

### LOW-4. Нет отдельного rate limiting для `/api/v2`

Файл:

- `routes/api.php`

Проблема:

Группа v2 использует только `api-v2-access-token`. Дополнительного throttle для дорогих операций create PayIn / dispute receipts не видно.

Риск:

Повышенная нагрузка и возможность abuse через валидный токен.

Рекомендация:

Добавить throttle по merchant/api credential, особенно для `POST /payin` и `POST /payin/{uuid}/dispute`.

### LOW-5. Минимальная сумма PayIn пока TODO и захардкожена как 1

Файл:

- `app/Http/Requests/API/V2/Order/StoreRequest.php`

Проблема:

В request есть TODO про `merchant.min_order_amounts`, но фактически `$min_amount = 1`.

Риск:

Cascade API может принимать суммы ниже настроек мерчанта/рынка, если такие ограничения уже есть в старом H2H/Merchant контуре.

Рекомендация:

Сверить с `Order`/H2H validation и включить те же min amount правила.

### LOW-6. Миграции имеют rollback/cleanup недочёты

Файлы:

- `database/migrations/2026_04_28_000001_extend_cascade_deals_for_full_state.php`
- `database/migrations/2026_04_28_204528_drop_unique_code_from_cascade_providers_table.php`
- `database/migrations/2026_04_29_000001_drop_merchant_id_from_cascade_providers_table.php`

Проблемы:

- `down()` миграции full state не откатывает data update `waiting_details_to_be_selected -> waiting_for_payment`;
- одна миграция снятия unique code пустая, а фактическое снятие делается другой миграцией;
- rollback `drop_merchant_id_from_cascade_providers_table` восстанавливает колонку как `string`, без исходной FK-семантики.

Риск:

Откаты staging/dev могут давать схему, отличающуюся от исходной.

Рекомендация:

Либо документировать миграции как forward-only, либо сделать rollback консистентным.

## 5. Edge Cases

### Duplicate create / retry

Сейчас есть сразу два проблемных сценария:

- параллельные валидные запросы могут создать две сделки;
- невалидный запрос может заблокировать external_id на 1 час.

Это стоит исправить первым, потому что idempotency является базовой защитой денежного API.

### Race loser cancellation

Race winner защищён хорошо, но cleanup проигравших не гарантирован:

- отмена выполняется прямо внутри attempt job;
- при exception статус не становится финальным;
- retry/backoff отмены нет.

### Callback idempotency

Callback lock защищает от одновременной отправки, но не от повторной отправки одного и того же payload в разные моменты. Нужен revision/hash.

### External operation finality

`cancelDeal`, `openDispute`, `storeConfirmationCode` для external в API выглядят как принятые, но job не всегда переносит provider response в `CascadeDeal`. Нужен единый post-job sync.

### Amount changes

Фиатная сумма может измениться, но без пересчёта USDT полей и залога агрегат перестаёт быть source of truth.

## 6. Рекомендуемый Порядок Исправлений

1. Исправить idempotency create: unique index `(merchant_id, external_id)`, убрать pending lock из validation, добавить service-level lock/finally.
2. Унифицировать `usdt_amount` для internal/external и вынести маппинг экономики в один метод.
3. Доработать `CascadeProviderOperationJob`: update `CascadeDeal` после external cancel/dispute/confirmation, retry/backoff, event, callback.
4. Исправить loser cancellation: retryable cancellation job и финальный статус ошибки отмены.
5. Добавить recalculation pipeline для amount changes от provider callbacks.
6. Ограничить callback dispatch только фактическими изменениями payload/revision.
7. Усилить dispute request validation: `max:3`, strict base64, temp cleanup.
8. Привести Dispute API и `OrderResource` к одному формату дат.
9. Добавить manual acquiring routing policy в `MerchantCascadeSetting`.
10. Добавить throttle для v2 PayIn/dispute.

## 7. Проверенные Файлы

- `routes/api.php`
- `routes/console.php`
- `config/horizon.php`
- `app/Http/Controllers/API/V2/OrderController.php`
- `app/Http/Controllers/API/V2/DisputeController.php`
- `app/Http/Controllers/API/V2/ProviderCallbackController.php`
- `app/Http/Requests/API/V2/Order/StoreRequest.php`
- `app/Http/Requests/API/V2/Dispute/StoreRequest.php`
- `app/Http/Requests/API/H2H/Order/StoreConfirmationCodeRequest.php`
- `app/Http/Resources/API/V2/OrderResource.php`
- `app/Services/Cascade/CascadeService.php`
- `app/Services/Cascade/CascadeProviderService.php`
- `app/Services/Cascade/CascadeProviderDiscoveryService.php`
- `app/Services/Cascade/CascadeDealSyncService.php`
- `app/Services/Cascade/CascadeProviderCollateralService.php`
- `app/Services/Cascade/CascadeDealEventRecorder.php`
- `app/Services/Cascade/Providers/CascadeProviderInterface.php`
- `app/Services/Cascade/Providers/InternalCascadeProvider.php`
- `app/Services/Cascade/Providers/MockProcessingCascadeProvider.php`
- `app/Jobs/CascadeProviderAttemptJob.php`
- `app/Jobs/CascadeProviderOperationJob.php`
- `app/Jobs/CascadeInternalTimeoutCleanupJob.php`
- `app/Jobs/SendCascadeDealCallbackJob.php`
- `app/Observers/OrderObserver.php`
- `app/Models/CascadeDeal.php`
- `app/Models/CascadeTransaction.php`
- `app/Models/CascadeProvider.php`
- `app/Models/CascadeDealEvent.php`
- `app/Models/MerchantCascadeSetting.php`
- `app/Enums/CascadeDealStatus.php`
- `app/Enums/CascadeDealSubStatus.php`
- `app/Enums/CascadeDisputeStatus.php`
- `app/Enums/CascadeTransactionStatus.php`
- `app/Enums/CascadeDealEventType.php`
- `database/migrations/2026_04_25_184020_create_cascade_deals_table.php`
- `database/migrations/2026_04_25_184021_create_cascade_transactions_table.php`
- `database/migrations/2026_04_25_184040_add_selected_transaction_foreign_key_to_cascade_deals_table.php`
- `database/migrations/2026_04_28_000001_extend_cascade_deals_for_full_state.php`
- `database/migrations/2026_04_28_000002_create_cascade_deal_events_table.php`
- `database/migrations/2026_04_28_000003_update_cascade_provider_settings.php`
- `database/migrations/2026_04_28_000004_create_merchant_cascade_settings_table.php`
- `database/migrations/2026_04_28_000005_create_provider_liquidity_role.php`

## 8. Итог

Текущая реализация уже содержит правильный каркас каскада, но ещё не полностью гарантирует финансовую и событийную консистентность. Главные исправления должны быть вокруг идемпотентного создания, единой экономики `CascadeDeal`, финализации external provider jobs и контроля callback idempotency.

До исправления CRITICAL/HIGH пунктов каскад лучше считать функционально рабочим прототипом, но не полностью безопасным контуром для денежного production-трафика.
