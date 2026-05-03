# Сравнение `cascade_audit_report.md` и `cascade_audit_report2.md`

Дата сравнения: 2026-05-03

Цель: найти пункты из первого аудита, которых нет во втором аудите или которые во втором аудите раскрыты заметно уже.

Важно: это сравнение самих Markdown-отчетов, а не повторная проверка кода. Если пункт отсутствует во втором аудите, это не обязательно означает, что он не исправлен. Это означает, что второй отчет его не зафиксировал как найденный/закрытый риск.

## Краткий вывод

Второй аудит покрывает основные критичные и высокие риски первого отчета: idempotency по `merchant_id + external_id`, залипающий pending-lock, external cancel, `usdt_amount`, callback-и без изменения состояния, callback после create, internal sync callback storm, amount callback без пересчета экономики, loser cancellation, dispute/base64 receipts, provider operation retry и timeout cleanup.

Но в первом аудите есть ряд пунктов, которых во втором нет. Самые важные для перепроверки:

- утечка `access_token` в provider-liquidity payload;
- миграция с некорректным `each()` на query builder;
- порядок проверки токена provider callback;
- stuck `CascadeDeal` не только при `cascade_enabled = false`, но и при любом окончательном fail/timeout orchestration;
- Horizon `tries => 1` для callback supervisor;
- DB/cache fallback для завершения orchestration;
- runtime-риск дубликатов `cascade_providers.code`;
- provider-liquidity amount filter в USDT вместо валюты сделки;
- неполный `CallbackLog` при transport exception.

## Есть в первом аудите, нет во втором

### Высокий приоритет для перепроверки

#### 1. Provider Liquidity отдавал `access_token` на фронт

Первый аудит: `3.5. Provider Liquidity получает access_token провайдера на фронт`.

Во втором аудите этого пункта нет.

Почему важно: если это еще не исправлено, это security issue. Provider-liquidity пользователь не должен получать секрет интеграции в браузер. Во втором аудите есть проверка provider-liquidity зоны в целом, но именно утечка `access_token` не упомянута.

Что проверить: `app/Http/Controllers/ProviderLiquidity/DashboardController.php`, payload метода `services()`. Вместо `access_token` должен быть безопасный признак вроде `has_access_token`.

#### 3. Provider callback парсится и ищет сделку до проверки токена

Первый аудит: `4.2. Callback от провайдера парсится и ищет сделку до проверки токена`.

Во втором аудите этого пункта нет.

Почему важно: это hardening-риск. Даже если прямого bypass нет, неавторизованный запрос может запускать adapter parsing и lookup сделок.

Что проверить: `CascadeService::handleProviderCallback()`. Если provider token не зависит от найденной сделки, проверка `Access-Token` должна происходить до тяжелой нормализации и поиска deal.

#### 4. Общий stuck `CascadeDeal` при fail/timeout orchestration раскрыт во втором уже, чем в первом

Первый аудит: `3.2. CascadeDeal создаётся до проверки доступности провайдеров и может остаться вечной pending-записью`.

Во втором аудите есть близкий пункт `MEDIUM-6`, но только про сценарий `cascade_enabled = false`. Первый аудит шире: сделка может остаться `pending` при отсутствии провайдеров, ошибке комиссии/экономики, падении всех attempts или timeout.

Почему важно: если исправлен только сценарий disabled merchant, остаются stuck deals после других окончательных fail-сценариев.

Что проверить: `CascadeService::createDeal()` и финализацию `CascadeProviderAttemptJob`. Любой окончательный fail после создания persistent `CascadeDeal` должен переводить deal в финальный fail/canceled/expired state, писать событие и освобождать/idempotently фиксировать `external_id`.

#### 7. Runtime-риск дубликатов `cascade_providers.code`

Первый аудит: `7.1. Дубликаты cascade_providers.code опасны`.

Во втором аудите есть только `LOW-6` про rollback/cleanup миграций, где упоминается снятие unique code. Но runtime-риск callback route binding и cache adapter-а по `code` отдельно не раскрыт.

Почему важно: если в БД появятся несколько строк с одним `code`, callback может попасть не в того провайдера, а adapter instance/config может кешироваться неоднозначно.

Что проверить: модель provider identity. Если одна integration = один provider config, вернуть unique `code`. Если нужны несколько инстансов одного adapter-а, разделить `adapter_code` и уникальный provider `slug`/`instance_code`.

#### 8. Provider Liquidity фильтрует `amount` как USDT, хотя `cascade_deals.amount` хранится в валюте сделки

Первый аудит: `8.1. Provider Liquidity фильтрует amount как USDT`.

Во втором аудите этого пункта нет.

Почему важно: фильтр суммы в provider-liquidity deals может быть неверным для RUB/других fiat валют.

Что проверить: `ProviderLiquidity\DashboardController`, фильтр `amount`. Если UI ожидает USDT, фильтровать нужно по `usdt_amount`; если fiat amount, нужен отдельный фильтр currency.

#### 9. `CallbackLog` создается только после успешного HTTP response

Первый аудит: `9.2. CallbackLog создаётся только после успешного HTTP response`.

Во втором аудите этого пункта нет.

Почему важно: при transport exception попытка callback-а может быть видна в `CascadeMerchantLog`, но отсутствовать в `CallbackLog`. Это ухудшает аудит доставки webhook-ов.

Что проверить: `SendCascadeDealCallbackJob`. Желательно писать `CallbackLog` и для исключений, если модель поддерживает `status_code = null`/error fields.

### Средний приоритет

#### 10. Мертвый/устаревший код в `CascadeService`

Первый аудит: `4.9. Мёртвый/устаревший код в CascadeService`.

Во втором аудите этого пункта нет.

Что проверить: неиспользуемые `createInternalProviderDeal()` и `cascadeDealWinnerAttributes()`. Если они больше не часть runtime path, лучше удалить или явно изолировать как legacy/test helper.

#### 11. Сервисный `cancelDispute()` существует, хотя публичный cascade API не должен отменять спор

Первый аудит: `5.4. В CascadeService есть cancelDispute(), но публичного cascade API для отмены спора нет`.

Во втором аудите отмечено, что публичного маршрута отмены спора нет и это корректно. Но сам риск существования сервисного метода не разобран.

Что проверить: если cancel dispute окончательно запрещен для cascade API, метод стоит удалить, переименовать под internal/admin use-case или явно ограничить слой вызова.

#### 12. `showByExternal` может раскрывать наличие `external_id` до Gate

Первый аудит: `5.5. showByExternal может раскрывать наличие external_id через 404 до Gate`.

Во втором аудите этого пункта нет.

Что проверить: порядок lookup/auth в `OrderController::showByExternal()` и `CascadeService::findDealByExternalId()`. Более строгий вариант: сначала авторизовать merchant scope из токена, затем искать external ID только в этом scope.

#### 14. `config` и `weight` в `cascade_providers` стали мертвыми полями

Первый аудит: `7.3. config и weight в cascade_providers стали мёртвыми полями`.

Во втором аудите этого пункта нет.

Что проверить: миграции и `CascadeProvider` model. Если поля не используются, лучше удалить отдельной миграцией или явно оставить как reserved-purpose.

#### 16. Provider logs пишутся, но timeout/fail контекст неполный

Первый аудит: `9.1. Provider logs пишутся, но timeout/fail контекст неполный`.

Во втором аудите частично есть `MEDIUM-9` про timeout по тексту ошибки, но нет полного пункта про provider log envelope: `status_code`, `duration`, `request_id`, failed cancel log.

Что проверить: adapter response/logging contract. Хороший целевой формат: `raw`, `status_code`, `duration`, `request_id`, structured `error_code`.

### Низкий приоритет / технический долг

#### 17. Недостижимая ветка `queued -> expired`

Первый аудит: `4.10. queued -> expired ветка в createDeal() недостижима`.

Во втором аудите это упомянуто только внутри `MEDIUM-10` как рекомендация: "убрать недостижимую ветку".

Что проверить: если еще не исправлено, условие можно удалить или переписать так, чтобы оно соответствовало фактическому timeout contract.

#### 18. Непонятные названия `getAvailableProviderCodes()` и `getAvailableIntegrationCodes()`

Первый аудит: `7.4. getAvailableProviderCodes() возвращает коды из БД, а не реализованные integration codes`.

Во втором аудите этого пункта нет.

Что проверить: это не production bug, но риск путаницы. Можно переименовать в `registeredProviderCodes()` и `implementedIntegrationCodes()`.

#### 19. `CascadeDealEventRecorder` создается через `new` в default constructor argument

Первый аудит: `8.2. CascadeDealEventRecorder и collateral/sync сервисы создаются через new`.

Во втором аудите этого пункта нет.

Что проверить: `CascadeDealSyncService` и `CascadeProviderCollateralService`. Лучше инжектить recorder через контейнер без default `new`, особенно если recorder получит зависимости.

#### 20. Нет явного индекса по `cascade_deals.order_id`

Первый аудит: `8.4. В cascade_deals нет индекса по order_id`.

Во втором аудите этого пункта нет.

Что проверить: фактические индексы БД. Если FK не создал индекс автоматически в используемой СУБД/схеме, добавить индекс, потому что `OrderObserver` ищет deal по `order_id`.

## Пункты первого аудита, которые во втором покрыты

Ниже пункты, которые не требуют отдельного переноса из первого отчета, потому что второй аудит их уже содержит:

- `3.1 pending external_id lock` -> `CRITICAL-2`;
- `3.3 DB uniqueness merchant_id + external_id` -> `CRITICAL-1`, отмечено как решено миграцией;
- `4.1 External cancel` -> `HIGH-2`;
- `4.3 Provider callback dispatch без изменения deal` -> `MEDIUM-1`;
- `4.4 Нет callback после create/winner selection` -> `MEDIUM-2`;
- `4.5 Internal sync callback storm` -> `HIGH-6`;
- `4.6 usdt_amount semantics` -> `HIGH-1`;
- `4.7 Amount callback без пересчета экономики/залога` -> `HIGH-5`;
- `4.8 Loser cancellation не гарантирована` -> `HIGH-4`;
- `5.1 Минимальная сумма TODO` -> `LOW-5`;
- `5.2 dispute.canceled_at format mismatch` -> `LOW-1`;
- `5.3 base64 receipts до validation` -> `MEDIUM-8`;
- `6.2 Provider operation jobs tries = 1` -> `MEDIUM-4`;
- `6.3 Internal timeout cleanup по тексту ошибки` -> `MEDIUM-9`.

## Итоговый список на перепроверку

Если цель — убедиться, что после исправлений ничего из первого аудита не осталось, я бы проверял в таком порядке:

1. `access_token` не уходит в provider-liquidity frontend payload.
2. Миграция `2026_04_29_000801...supported_currency_codes...` не использует query builder `each()`.
3. Provider callback token проверяется до ненужной тяжелой обработки, где это возможно.
4. Все fail/timeout сценарии после создания `CascadeDeal` переводят deal в финальный state.
5. Retry policy `SendCascadeDealCallbackJob` не ломается Horizon supervisor-ом.
6. Orchestration completion имеет DB fallback, а не только cache-counter.
7. Provider `code` не может быть неоднозначным в callbacks и adapter cache.
8. Provider-liquidity amount filter соответствует валюте поля.
9. `CallbackLog` фиксирует transport exceptions.
10. Остальной список выше можно закрывать как hardening/технический долг.

