# Infrastructure API (v1)

Документация по внутреннему API для интеграции между сервисами инфраструктуры.

API ориентирован на межсистемное взаимодействие: другая система может получать данные из этого проекта, а в будущем контракт может быть расширен операциями записи.

## 1. Базовая информация

- Base URL: `/api/integration/v1`
- Формат данных: `application/json`
- Таймзона дат: ISO-8601 с таймзоной (`2026-04-27T08:00:00+10:00`)
- Пагинация по умолчанию: `per_page=10`
- Максимальная пагинация: `per_page=100`
- Все текущие endpoints: read-only (GET)

## 2. Авторизация

Используется токен в заголовке:

- Header: `Access-Token: <your_token>`

Если токен отсутствует или неверный, API вернет:

```json
{
  "message": "Invalid Access-Token."
}
```

HTTP status: `401 Unauthorized`

## 3. Унифицированные правила фильтрации и пагинации

Большинство list-endpoints поддерживают:

- `page` — номер страницы
- `per_page` — размер страницы (`1..100`, по умолчанию `10`)
- `date_from` — начало периода (строка, формат даты/времени, например ISO-8601)
- `date_to` — конец периода

Для мульти-ID фильтров используется CSV:

- пример: `ids=1,2,3`
- пример: `user_ids=10,11,12`

## 4. Формат пагинированного ответа

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 125,
    "last_page": 13
  },
  "links": {
    "first": "https://example.com/api/integration/v1/users?page=1",
    "last": "https://example.com/api/integration/v1/users?page=13",
    "prev": null,
    "next": "https://example.com/api/integration/v1/users?page=2"
  }
}
```

## 5. Endpoints

---

## 5.1 Пользователи

### GET `/users`

Список пользователей.

Фильтры:

- `ids` (CSV)
- `email`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/users/{user}`

Детальная информация по пользователю.

### GET `/users/{user}/offers`

Предложения пользователя (в текущей реализации: реквизиты пользователя + связанные payment methods).

Фильтры:

- `is_active`
- `page`, `per_page`

---

## 5.2 Реквизиты

### GET `/payment-details`

Список реквизитов.

Фильтры:

- `ids` (CSV)
- `user_ids` (CSV)
- `is_active`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/payment-details/{paymentDetail}`

Детальная информация по реквизиту.

Примечание: в ответе присутствует список `payment_methods`.

---

## 5.3 Сделки

### GET `/orders`

Список сделок.

Фильтры:

- `user_ids` (CSV, фильтрация по trader/user)
- `status`
- `payment_detail_id`
- `payment_gateway_id`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/orders/{order:uuid}`

Детальная информация по сделке через UUID.

Примечание: в ответе есть краткий блок `payment_detail` и `payment_method`.

---

## 5.4 Споры

### GET `/disputes`

Список споров.

Фильтры:

- `order_ids` (CSV)
- `status`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/disputes/{dispute}`

Детальная информация по спору.

---

## 5.5 Заявки / депозиты / выводы

В текущем API используются сущности `invoices` как заявки финансовых операций.

### GET `/invoices`

Общий список заявок/инвойсов.

Фильтры:

- `wallet_ids` (CSV)
- `user_ids` (CSV) — через связанные кошельки
- `type` (`deposit` | `withdrawal`)
- `status`
- `balance_type`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/invoices/{invoice}`

Детальная заявка/инвойс.

### GET `/deposits`

Список только депозитов (alias для `/invoices?type=deposit`).

### GET `/withdrawals`

Список только выводов (alias для `/invoices?type=withdrawal`).

---

## 5.6 Выплаты

### GET `/payouts`

Список выплат.

Фильтры:

- `user_ids` (CSV, trader)
- `status`
- `payment_gateway_id`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/payouts/{payout:uuid}`

Детальная выплата по UUID.

---

## 5.7 Кошелек

### GET `/wallets/{wallet}`

Информация по кошельку и балансам.

### GET `/wallets/{wallet}/transactions`

Транзакции кошелька (с пагинацией).

Фильтры:

- `type`
- `direction`
- `balance_type`
- `date_from`, `date_to`
- `page`, `per_page`

### GET `/wallets/{wallet}/transactions/{transaction}`

Детальная информация по транзакции кошелька.

---

## 6. Примеры запросов

### Получить пользователей

```bash
curl -X GET "https://your-domain.com/api/integration/v1/users?per_page=10&page=1" \
  -H "Accept: application/json" \
  -H "Access-Token: YOUR_TOKEN"
```

### Получить сделки конкретного пользователя со статусом

```bash
curl -X GET "https://your-domain.com/api/integration/v1/orders?user_ids=101&status=success&per_page=20" \
  -H "Accept: application/json" \
  -H "Access-Token: YOUR_TOKEN"
```

### Получить транзакции кошелька

```bash
curl -X GET "https://your-domain.com/api/integration/v1/wallets/55/transactions?balance_type=trust&page=1" \
  -H "Accept: application/json" \
  -H "Access-Token: YOUR_TOKEN"
```

## 7. Минимальный контракт полей (ориентиры)

### User (list/single)

- `id`, `email`, `name`
- `roles[]`
- `is_online`, `can_set_order_amount_limits`
- `created_at`, `updated_at`

### PaymentDetail

- `id`, `name`, `detail`, `detail_type`, `is_active`
- `user { id, email }`
- `payment_methods[] { id, code, name }`
- `created_at`, `updated_at`

### Order

- `id`, `uuid`, `external_id`
- `status`, `sub_status`
- `amount`, `currency`
- `merchant { id, uuid }`
- `user { id, email }`
- `payment_detail { id, name, user_id }`
- `payment_method { id, code, name }`
- `created_at`, `updated_at`

### Dispute

- `id`, `status`, `reason`, `receipt` (в single)
- `trader { id, email }`
- `order { id, uuid }`
- `created_at`, `updated_at`

### Invoice

- `id`, `external_id`
- `amount`, `currency`
- `address`, `network`, `tx_hash`
- `type`, `balance_type`, `status`
- `wallet { id, user_id, user_email }`
- `created_at`, `updated_at`

### Payout

- `id`, `uuid`, `external_id`
- `status`, `amount_fiat`, `payout_method_type`, `requisites`
- `merchant { id, uuid }`
- `trader { id, email }`
- `payment_method { id, code, name }`
- `created_at`, `updated_at`

### Wallet / Transaction

- wallet: balances (`merchant_balance`, `trust_balance`, `reserve_balance`, `commission_balance`, `teamleader_balance`)
- transaction: `id`, `amount`, `direction`, `type`, `balance_type`, `wallet`, `created_at`, `updated_at`

## 8. Рекомендации по интеграции (для AI/агента/сервиса)

Ниже практический алгоритм, как интегратору (включая AI-агента) корректно работать с API.

### Шаг 1. Инициализация клиента

- Сохранять `baseUrl = /api/integration/v1`.
- Всегда отправлять `Accept: application/json`.
- Всегда отправлять `Access-Token`.
- На 401 считать токен невалидным и эскалировать в конфиг-сервис.

### Шаг 2. Унификация запросов

- Для каждого list-endpoint реализовать единый helper:
  - принимает `filters`, `page`, `per_page`
  - автоматически сериализует CSV-поля (`ids`, `user_ids` и т.д.)
  - возвращает `{ items, meta, links }`

### Шаг 3. Паттерн синхронизации

- Инкрементальная синхронизация:
  - хранить `last_synced_at` для каждой сущности;
  - запрашивать с `date_from=last_synced_at`;
  - обязательно проходить все страницы (`links.next`).
- Для коррекции использовать периодический full-check за ограниченный диапазон.

### Шаг 4. Идемпотентность на стороне интегратора

- Хранить сущности по стабильным ключам:
  - users: `id`
  - orders: `uuid`
  - payouts: `uuid`
  - invoices/disputes/payment-details/transactions: `id`
- Использовать upsert, а не blind insert.

### Шаг 5. Обработка ошибок и ретраи

- `401` — не ретраить, запросить новый токен/проверить конфиг.
- `429/5xx` — ретрай с exponential backoff.
- Timeout — ограничить `connect/read timeout`, повторять с jitter.

### Шаг 6. Наблюдаемость

- Логировать:
  - endpoint, query params (без токена),
  - status code,
  - latency,
  - correlation id (свой).
- Хранить метрики по объему синхронизации и числу ошибок.

## 9. План расширения на future write API

Чтобы безболезненно добавить операции записи:

- Сохранить namespace `infrastructure` как общий, не `read`.
- Для write-операций добавить явные версии или capability флаг:
  - например, `/api/integration/v2/...`
- Разделить разрешения токена по scope (read/write/admin).
- Для write-endpoints добавить идемпотентный ключ (например `Idempotency-Key`).

## 10. Чеклист готовности интеграции

- [ ] Токен получен и сохранен в секретах
- [ ] Проверен доступ к `/users`
- [ ] Реализован общий пагинатор
- [ ] Реализован retry/backoff
- [ ] Реализован upsert по стабильным ключам
- [ ] Реализованы мониторинг и алерты

