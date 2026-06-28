<script setup>
import CodeBlock from '@/Components/CodeBlock.vue';

const tocSections = [
    { id: 'about', title: 'Введение' },
    { id: 'base', title: 'Основы' },
    { id: 'order-statuses', title: 'Статусы сделок' },
    { id: 'callback', title: 'Callbacks' },
    { id: 'base-methods', title: 'Базовые методы' },
    { id: 'h2h-api', title: 'Host To Host API' },
    { id: 'manual-control-acquiring', title: 'Manual Control Acquiring' },
    { id: 'payouts-api', title: 'Payouts API' },
    { id: 'statements-api', title: 'Выписки' },
];

const responseSuccessExample = `{
  "success": true,
  "data": { } // полезная нагрузка метода
}`;

const responseValidationExample = `{
  "message": "Общее описание ошибки",
  "errors": {
    "field_name": ["Описание ошибки по полю"]
  }
}`;

const responseBusinessExample = `{
  "success": false,
  "message": "Описание ошибки бизнес-логики"
}`;

const responseServerExample = `{
  "message": "Internal Server Error"
}`;

const currenciesResponse = `{
  "success": true,
  "data": [
    {
      "currency": "rub",          // код валюты
      "precision": 2,             // знаков после запятой
      "symbol": "\u20bd",              // символ валюты
      "name": "Российский рубль"  // название
    }
  ]
}`;

const gatewaysResponse = `{
  "success": true,
  "data": [
    {
      "name": "Сбербанк",                // название метода
      "code": "sberbank",                // код (передаётся в payment_gateway)
      "currency": "rub",                 // валюта метода
      "min_limit": "1000",               // мин. сумма сделки
      "max_limit": "100000",             // макс. сумма сделки
      "reservation_time": 10,            // время резерва реквизита, мин
      "detail_types": ["card", "phone"]  // доступные типы реквизитов
    }
  ]
}`;

const orderCreateRequest = `{
  "merchant_id": "3db07a16-...",   // обязательно — UUID магазина
  "external_id": "order-100500",   // обязательно — ваш ID сделки (уникален в магазине)
  "amount": 1000,                  // обязательно — сумма сделки (целое число)
  "payment_gateway": "sberbank",   // код метода ИЛИ currency (что-то одно)
  "currency": "rub",               // код валюты ИЛИ payment_gateway
  "payment_detail_type": "card",   // необязательно — тип реквизита
  "rate": "100.5",                 // обязателен только при источнике курса merchant_api
  "client_id": "user-42",          // необязательно — ID клиента (для антифрода)
  "callback_url": "https://shop.example/callback" // необязательно — куда слать статусы
}`;

const orderResponse = `{
  "success": true,
  "data": {
    "order_id": "3db07a16-...",          // UUID сделки
    "external_id": "order-100500",       // ваш ID сделки
    "merchant_id": "3db07a16-...",       // UUID магазина
    "initial_amount": "1000",            // исходная сумма при создании
    "amount": "1040",                    // текущая сумма (может пересчитаться)
    "total_profit": "9.94",              // общая прибыль по сделке, USDT
    "merchant_profit": "9.05",           // прибыль магазина, USDT
    "currency": "rub",                   // валюта сделки
    "profit_currency": "usdt",           // валюта прибыли
    "rate_currency": "rub",              // валюта курса
    "rate": "100.77",                    // курс конвертации
    "status": "pending",                 // pending | success | fail
    "sub_status": "waiting_for_payment", // детальный под-статус
    "reject_reason": null,               // причина отклонения, если есть
    "callback_url": "https://...",       // URL для callback
    "manual_control_acquiring": false,   // режим Manual Control Acquiring
    "manual_control_confirmation_type": null, // тип подтверждения (MCA)
    "payment_gateway": "sberbank",       // код метода
    "payment_gateway_name": "Сбербанк",  // название метода
    "payment_detail": {
      "requisites": "2200...",           // реквизит для оплаты (карта/телефон/счёт)
      "type": "card",                    // тип реквизита
      "holder_name": "Иван Иванов"       // владелец реквизита
      // "dispute": { "status": "pending", "reason": null } — появляется при наличии спора
    },
    "merchant": {
      "name": "My Shop",                 // название магазина
      "description": "..."               // описание магазина
    },
    "finished_at": null,                            // когда завершена (ISO 8601)
    "expires_at": "2026-01-04T12:05:00+00:00",      // срок оплаты (ISO 8601)
    "created_at": "2026-01-04T12:00:00+00:00",      // когда создана (ISO 8601)
    "current_server_time": "2026-01-04T12:01:00+00:00" // текущее время сервера (синхронизация)
  }
}`;

const confirmationCodeRequest = `{
  "confirmation_code": "123456" // код подтверждения (OTP/CVC и т.п.)
}`;

const confirmationCodeResponse = `{
  "success": true,
  "data": {
    "order_id": "3db07a16-...",
    "confirmation_code": {
      "value": "123456",
      "created_at": "2026-01-04T12:02:00+00:00" // ISO 8601
    }
  }
}`;

const disputeResponse = `{
  "success": true,
  "data": {
    "order_id": "3db07a16-...",
    "status": "pending", // статус спора
    "reason": null       // причина (если отклонён)
  }
}`;

const mcaRequest = `{
  "merchant_id": "3db07a16-...",
  "external_id": "mca-order-1001",
  "amount": 1500,
  "currency": "rub",               // только currency; payment_gateway указывать нельзя
  "manual_control_acquiring": true,
  "payment_detail_type": "card",   // допустим только card
  "card_number": "4444333322221111",
  "expiry_month": 12,
  "expiry_year": 2029,
  "cvc": "123",
  "cardholder_name": "IVAN IVANOV", // опционально
  "callback_url": "https://shop.example/callback"
}`;

const payoutCreateRequest = `{
  "merchant_id": "3db07a16-...",   // обязательно — UUID магазина
  "external_id": "payout-100500",  // обязательно — ваш ID выплаты (уникален в магазине)
  "amount": 100000,                // обязательно — сумма (целое число)
  "payout_method": "sbp",          // обязательно — sbp | card
  "payment_gateway": "sberbank",   // код метода ИЛИ currency (что-то одно)
  "currency": "rub",               // код валюты ИЛИ payment_gateway
  "rate": "77.5",                  // обязателен только при источнике курса merchant_api
  "requisites": "79260000000",     // обязательно — телефон (СБП) или номер карты
  "recipient_name": "Иванов Иван", // обязательно — ФИО получателя
  "bank_name": "Custom Bank",      // необязательно (нельзя вместе с payment_gateway)
  "callback_url": "https://shop.example/payout-callback" // необязательно
}`;

const payoutResponse = `{
  "success": true,
  "data": {
    "payout_id": "af8d6a20-...",     // UUID выплаты
    "external_id": "payout-100500",  // ваш ID выплаты
    "status": "open",                // open | taken | sent | completed | canceled
    "payout_method": "sbp",          // метод выплаты
    "bank_name": "Custom Bank",      // банк (если задан)
    "requisites": "79260000000",     // реквизиты получателя
    "recipient_name": "Иванов Иван", // ФИО получателя
    "merchant": {
      "id": "3db07a16-...",          // UUID магазина
      "name": "My Shop"
    },
    "payment_gateway": {
      "name": "Сбербанк",            // название метода
      "code": "sberbank"             // код метода
    },
    "receipt_url": null,             // ссылка на чек (когда появится)
    "amounts": {
      "fiat":            { "value": "100000.00", "currency": "RUB" },  // сумма в фиате
      "usdt":            { "value": "1289.54",   "currency": "USDT" }, // тело в USDT
      "merchant_charge": { "value": "1328.23",   "currency": "USDT" }  // списание с магазина
    },
    "fees": {
      "total": { "value": "38.69", "currency": "USDT" } // комиссия, USDT
    },
    "commission_percent": 3,         // ставка комиссии, %
    "rate": {
      "market": "bybit",             // источник курса
      "value": "77.50",              // курс
      "currency": "RUB",             // валюта курса
      "fixed_at": "2026-01-04T12:00:00+00:00" // когда зафиксирован (ISO 8601)
    },
    "timestamps": {
      "created_at":   "2026-01-04T12:00:00+00:00",
      "taken_at":     null,
      "sent_at":      null,
      "completed_at": null,
      "canceled_at":  null
    }
  }
}`;

const payoutReceiptsResponse = `{
  "success": true,
  "data": {
    "payout_id": "af8d6a20-...",
    "receipts": [
      {
        "receipt_id": 101,
        "filename": "receipt-1.jpg",
        "mime_type": "image/jpeg",
        "size": 82451,
        "base64": "/9j/4AAQSkZJRg..."
      }
    ]
  }
}`;

const statementsOrdersResponse = `{
  "success": true,
  "data": [
    {
      "order_id": "d90f3f03-...",
      "external_id": "order-100500",
      "payin": {
        "initial_amount": "1000.00", // сумма при создании
        "amount": "1040.00",         // текущая сумма
        "currency": "rub"
      },
      "credit": {
        "amount": "9.05",            // зачисление магазину
        "currency": "usdt"
      },
      "rate": {
        "value": "100.77",           // курс
        "market": "bybit",           // источник курса
        "fixed_at": "2026-01-04T12:00:00+00:00" // ISO 8601
      },
      "status": "pending",
      "created_at": "2026-01-04T12:00:00+00:00" // ISO 8601
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}`;

const statementsPayoutsResponse = `{
  "success": true,
  "data": [
    {
      "payout_id": "af8d6a20-...",
      "external_id": "payout-100500",
      "payout": { "amount": "100000.00", "currency": "RUB" }, // сумма выплаты
      "debit":  { "amount": "1328.23",   "currency": "USDT" }, // списание с магазина
      "rate": {
        "value": "77.50",
        "market": "bybit",
        "fixed_at": "2026-01-04T12:00:00+00:00"
      },
      "status": "open",
      "created_at": "2026-01-04T12:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}`;

const orderCurlExample = `curl -X POST https://api.example.com/api/h2h/order \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -H "Access-Token: <ВАШ_API_ТОКЕН>" \\
  -d '{
    "merchant_id": "3db07a16-...",
    "external_id": "order-100500",
    "amount": 1000,
    "currency": "rub"
  }'`;

const callbackOrderBody = `{
  "order_id": "3db07a16-...",
  "external_id": "order-100500",
  "status": "success",                // изменившийся статус
  "sub_status": "successfully_paid",
  "amount": "1040",
  "currency": "rub"
  // ... остальные поля — как в ответе создания сделки
}`;

const callbackVerifyJs = `// Node.js / Express
const crypto = require('crypto');

const rawBody = request.rawBody; // сырое тело запроса (до парсинга JSON)
const signature = request.headers['x-webhook-signature'];
const expected = crypto
    .createHmac('sha256', webhookSecret)
    .update(rawBody)
    .digest('hex');

if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
    throw new Error('Invalid webhook signature');
}`;

const callbackVerifyPhp = `<?php
// PHP
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $payload, $webhookSecret);

if (! hash_equals($expected, $signature)) {
    http_response_code(400);
    exit('Invalid signature');
}`;
</script>

<template>
    <div class="space-y-10" data-api-docs-markdown-root>
        <div class="grid grid-cols-1 gap-6 xl:flex">
            <aside>
                <div class="card menu menu-sm sticky top-6 w-full bg-base-100 p-0 shadow xl:w-64">
                    <div class="card-body">
                        <h3 class="card-title text-lg">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            Содержание
                        </h3>
                        <ul class="w-full">
                            <li v-for="section in tocSections" :key="section.id">
                                <a :href="`#${section.id}`" class="truncate">{{ section.title }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>

            <div class="min-w-0 flex-1 space-y-6">
                <article id="about" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Введение</h2>
                        <p class="text-base-content/80">
                            Здесь описано, как работает API для интеграции вашего сервиса с платформой:
                            приём платежей (Host To Host), выплаты, выписки и callback-уведомления.
                            Все денежные суммы передаются строками, время — в формате ISO 8601 (UTC).
                        </p>
                    </div>
                </article>

                <article id="base" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Основы работы с API</h2>

                        <div>
                            <h3 class="mb-2 text-xl font-semibold">Заголовки запросов</h3>
                            <ul class="ml-2 list-inside list-disc space-y-2 text-base-content/80">
                                <li><strong>Accept: application/json</strong> — формат ответа.</li>
                                <li><strong>Content-Type: application/json</strong> — для запросов с телом.</li>
                                <li><strong>Access-Token: &lt;token&gt;</strong> — API токен из раздела «API Интеграция». Показывается один раз после генерации.</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="mb-3 text-xl font-semibold">Ответы сервера</h3>
                            <div class="join join-vertical w-full space-y-2">
                                <div class="collapse collapse-arrow join-item bg-base-200">
                                    <input type="checkbox" checked />
                                    <div class="collapse-title text-lg font-medium">HTTP 200 — Успех</div>
                                    <div class="collapse-content">
                                        <CodeBlock :code="responseSuccessExample" lang="jsonc" label="200 OK" />
                                    </div>
                                </div>
                                <div class="collapse collapse-arrow join-item bg-base-200">
                                    <input type="checkbox" />
                                    <div class="collapse-title text-lg font-medium">HTTP 422 — Ошибка валидации</div>
                                    <div class="collapse-content">
                                        <CodeBlock :code="responseValidationExample" lang="jsonc" label="422 Unprocessable" />
                                    </div>
                                </div>
                                <div class="collapse collapse-arrow join-item bg-base-200">
                                    <input type="checkbox" />
                                    <div class="collapse-title text-lg font-medium">HTTP 400 — Ошибка бизнес-логики</div>
                                    <div class="collapse-content">
                                        <CodeBlock :code="responseBusinessExample" lang="jsonc" label="400 Bad Request" />
                                    </div>
                                </div>
                                <div class="collapse collapse-arrow join-item bg-base-200">
                                    <input type="checkbox" />
                                    <div class="collapse-title text-lg font-medium">HTTP 500 — Ошибка сервера</div>
                                    <div class="collapse-content">
                                        <CodeBlock :code="responseServerExample" lang="jsonc" label="500 Server Error" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <article id="order-statuses" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Статусы сделок</h2>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">status</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th>Значение</th><th>Описание</th></tr></thead>
                                    <tbody>
                                    <tr><td><code class="rounded bg-base-200 px-1">pending</code></td><td>Сделка в обработке.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">success</code></td><td>Сделка успешно завершена.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">fail</code></td><td>Сделка завершилась неудачно.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">sub_status</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th>Значение</th><th>Описание</th></tr></thead>
                                    <tbody>
                                    <tr><td><code class="rounded bg-base-200 px-1">waiting_details_to_be_selected</code></td><td>Ждёт выбора реквизитов.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">waiting_for_payment</code></td><td>Ждёт платежа.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">successfully_paid</code></td><td>Закрыта автоматически.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">accepted</code></td><td>Закрыта вручную.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">successfully_paid_by_resolved_dispute</code></td><td>Закрыта по принятому спору.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">waiting_for_dispute_to_be_resolved</code></td><td>Ждёт решения спора.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">canceled_by_dispute</code></td><td>Отменена по спору.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">expired</code></td><td>Отменена по истечению времени.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">cancelled</code></td><td>Отменена вручную.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="callback" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Callbacks</h2>
                        <p class="text-base-content/80">
                            При каждом изменении статуса сделки или выплаты на ваш URL отправляется POST-запрос.
                            Адрес берётся из <code class="rounded bg-base-200 px-1">callback_url</code> запроса, иначе — из настроек магазина.
                        </p>
                        <div role="alert" class="alert alert-info alert-soft items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="h-5 w-5 shrink-0 stroke-current">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="space-y-1">
                                <h4 class="font-semibold">Тело callback повторяет ответ метода</h4>
                                <p class="text-sm text-base-content/80">
                                    В callback приходит сразу содержимое объекта
                                    <code class="rounded bg-base-100/70 px-1 font-mono text-xs">data</code>
                                    из ответа соответствующего метода — <strong>без обёртки</strong>
                                    <code class="rounded bg-base-100/70 px-1 font-mono text-xs">{ "success": ..., "data": ... }</code>.
                                    По сделке структура совпадает с ответом создания сделки, по выплате — с ответом создания выплаты.
                                </p>
                            </div>
                        </div>

                        <CodeBlock :code="callbackOrderBody" lang="jsonc" label="Пример тела callback по сделке" />
                        <p class="text-base-content/80">
                            Callback подписывается отдельным Webhook secret из раздела «API Интеграция».
                            Не используйте API токен для проверки подписи.
                        </p>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">Заголовки callback-запроса</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th>Заголовок</th><th>Описание</th></tr></thead>
                                    <tbody>
                                    <tr><td><code class="rounded bg-base-200 px-1">X-Webhook-Signature</code></td><td>HMAC SHA-256 подпись сырого тела, рассчитанная по Webhook secret.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">X-Webhook-Signature-Algorithm</code></td><td>Всегда <code class="rounded bg-base-200 px-1">HMAC-SHA256</code>.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">Проверка подписи</h3>
                            <CodeBlock :code="callbackVerifyJs" lang="javascript" label="Node.js" />
                            <CodeBlock :code="callbackVerifyPhp" lang="php" label="PHP" />
                        </section>
                    </div>
                </article>

                <article id="base-methods" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Базовые методы</h2>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/currencies</code>
                                <span class="text-base-content/70">Доступные валюты</span>
                            </div>
                            <CodeBlock :code="currenciesResponse" lang="jsonc" label="Ответ" />
                        </section>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/payment-gateways</code>
                                <span class="text-base-content/70">Доступные платёжные методы</span>
                            </div>
                            <CodeBlock :code="gatewaysResponse" lang="jsonc" label="Ответ" />
                        </section>
                    </div>
                </article>

                <article id="h2h-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Host To Host API</h2>
                        <p class="text-sm text-base-content/80">
                            Создание сделки выполняется через пуллинг: API ждёт подбор реквизитов и возвращает готовую сделку.
                            Заголовок <code class="rounded bg-base-200 px-1">X-Max-Wait-Ms</code> задаёт время ожидания (по умолчанию ~30 секунд).
                        </p>

                        <section class="space-y-4 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-secondary badge-lg">POST</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order</code>
                                <span class="text-base-content/70">Создать сделку</span>
                            </div>

                            <div>
                                <h4 class="mb-2 font-semibold">Параметры запроса</h4>
                                <CodeBlock :code="orderCreateRequest" lang="jsonc" label="Запрос" />
                            </div>

                            <div>
                                <h4 class="mb-2 font-semibold">Ответ сервера</h4>
                                <CodeBlock :code="orderResponse" lang="jsonc" label="Ответ" />
                            </div>

                            <div>
                                <h4 class="mb-2 font-semibold">Пример (curl)</h4>
                                <CodeBlock :code="orderCurlExample" lang="bash" label="curl" />
                            </div>
                        </section>

                        <section class="space-y-2 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order/{order_id}</code>
                                <span class="text-base-content/70">Получить сделку</span>
                            </div>
                            <p class="text-sm text-base-content/80">Возвращает тот же объект, что и при создании. Альтернатива: <code class="rounded bg-base-200 px-1">/api/h2h/order/{merchant_id}/{external_id}</code>.</p>
                        </section>

                        <section class="space-y-2 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order/{order_id}/cancel</code>
                                <span class="text-base-content/70">Закрыть сделку как неуспешную</span>
                            </div>
                            <p class="text-sm text-base-content/80">Доступно для сделки в статусе <code class="rounded bg-base-200 px-1">pending</code> без открытого спора.</p>
                        </section>

                        <section class="space-y-2 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order/{order_id}/finish</code>
                                <span class="text-base-content/70">Завершить сделку как успешную</span>
                            </div>
                            <p class="text-sm text-base-content/80">
                                Помечает сделку как <code class="rounded bg-base-200 px-1">success</code>
                                с под-статусом <code class="rounded bg-base-200 px-1">cancelled</code>.
                            </p>
                        </section>

                        <section class="space-y-4 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-secondary badge-lg">POST</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order/{order_id}/confirmation-code</code>
                                <span class="text-base-content/70">Передать код подтверждения</span>
                            </div>
                            <p class="text-sm text-base-content/80">Доступно для сделок в режиме <code class="rounded bg-base-200 px-1">manual_control_acquiring</code> и статусе <code class="rounded bg-base-200 px-1">pending</code>.</p>
                            <CodeBlock :code="confirmationCodeRequest" lang="jsonc" label="Запрос" />
                            <CodeBlock :code="confirmationCodeResponse" lang="jsonc" label="Ответ" />
                        </section>

                        <section class="space-y-4 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-secondary badge-lg">POST</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/h2h/order/{order_id}/dispute</code>
                                <span class="text-base-content/70">Открыть спор</span>
                            </div>
                            <p class="text-sm text-base-content/80">
                                Поле <code class="rounded bg-base-200 px-1">receipt</code> (обязательно) — файл jpeg/jpg/png/pdf в base64 до 5 МБ.
                                Если сделка ещё открыта, она будет закрыта перед созданием спора.
                            </p>
                            <CodeBlock :code="disputeResponse" lang="jsonc" label="Ответ" />
                        </section>
                    </div>
                </article>

                <article id="manual-control-acquiring" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Manual Control Acquiring</h2>
                        <p class="text-sm text-base-content/80">
                            Режим H2H, в котором сделка создаётся с принудительным использованием реквизита карты клиента.
                            Реквизиты сохраняются в самой сделке и не создают отдельный реквизит в системе.
                        </p>
                        <ul class="ml-2 list-inside list-disc space-y-2 text-sm text-base-content/80">
                            <li>Передайте <code class="rounded bg-base-200 px-1 text-xs">manual_control_acquiring=true</code>.</li>
                            <li><code class="rounded bg-base-200 px-1 text-xs">payment_gateway</code> указывать нельзя — только <code class="rounded bg-base-200 px-1 text-xs">currency</code>.</li>
                            <li><code class="rounded bg-base-200 px-1 text-xs">payment_detail_type</code> — только <code class="rounded bg-base-200 px-1 text-xs">card</code>.</li>
                            <li>Обязательны: <code class="rounded bg-base-200 px-1 text-xs">card_number</code>, <code class="rounded bg-base-200 px-1 text-xs">expiry_month</code>, <code class="rounded bg-base-200 px-1 text-xs">expiry_year</code>, <code class="rounded bg-base-200 px-1 text-xs">cvc</code>. <code class="rounded bg-base-200 px-1 text-xs">cardholder_name</code> — опционально.</li>
                        </ul>
                        <p class="text-sm text-base-content/80">
                            В ответе поля внутри <code class="rounded bg-base-200 px-1 text-xs">payment_detail</code>
                            (<code class="rounded bg-base-200 px-1 text-xs">requisites</code>,
                            <code class="rounded bg-base-200 px-1 text-xs">type</code>,
                            <code class="rounded bg-base-200 px-1 text-xs">holder_name</code>) возвращаются как
                            <code class="rounded bg-base-200 px-1 text-xs">null</code>.
                        </p>

                        <div>
                            <h4 class="mb-2 font-semibold">Пример создания сделки</h4>
                            <CodeBlock :code="mcaRequest" lang="jsonc" label="Запрос" />
                        </div>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">Значения manual_control_confirmation_type</h3>
                            <p class="text-sm text-base-content/80">Устанавливается оператором после взятия заявки в работу; пока не выбран — <code class="rounded bg-base-200 px-1 text-xs">null</code>.</p>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th>Значение</th><th>Описание</th></tr></thead>
                                    <tbody>
                                    <tr><td><code class="rounded bg-base-200 px-1">otp_code</code></td><td>OTP-код.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">in_app_confirmation</code></td><td>Подтверждение в приложении.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">bank_call</code></td><td>Звонок в банк.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">otp_code_and_pin_code</code></td><td>OTP-код и PIN-код.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">sms_with_instructions</code></td><td>SMS с инструкциями.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="payouts-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Payouts API</h2>
                        <p class="text-sm text-base-content/80">
                            Выплаты мерчанта клиенту через трейдера. Укажите либо <code class="rounded bg-base-200 px-1 text-xs">payment_gateway</code>,
                            либо <code class="rounded bg-base-200 px-1 text-xs">currency</code>. Заголовок <code class="rounded bg-base-200 px-1 text-xs">X-Max-Wait-Ms</code> задаёт время ожидания создания.
                        </p>

                        <section class="space-y-4 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-secondary badge-lg">POST</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/payouts</code>
                                <span class="text-base-content/70">Создать выплату</span>
                            </div>
                            <div>
                                <h4 class="mb-2 font-semibold">Параметры запроса</h4>
                                <CodeBlock :code="payoutCreateRequest" lang="jsonc" label="Запрос" />
                            </div>
                            <div>
                                <h4 class="mb-2 font-semibold">Ответ сервера</h4>
                                <CodeBlock :code="payoutResponse" lang="jsonc" label="Ответ" />
                            </div>
                        </section>

                        <section class="space-y-2 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/payouts/{payout_id}</code>
                                <span class="text-base-content/70">Получить выплату</span>
                            </div>
                            <p class="text-sm text-base-content/80">Возвращает те же поля, что и при создании.</p>
                        </section>

                        <section class="space-y-2 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/payouts/{payout_id}/cancel</code>
                                <span class="text-base-content/70">Отменить выплату</span>
                            </div>
                            <p class="text-sm text-base-content/80">Доступно, пока выплата в статусе <code class="rounded bg-base-200 px-1">open</code>. Средства возвращаются магазину.</p>
                        </section>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/payouts/{payout_id}/receipts</code>
                                <span class="text-base-content/70">Чеки выплаты</span>
                            </div>
                            <p class="text-sm text-base-content/80">Возвращает до 5 чеков в base64.</p>
                            <CodeBlock :code="payoutReceiptsResponse" lang="jsonc" label="Ответ" />
                        </section>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <h3 class="text-xl font-semibold">Статусы выплат</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead><tr><th>Статус</th><th>Описание</th></tr></thead>
                                    <tbody>
                                    <tr><td><code class="rounded bg-base-200 px-1">open</code></td><td>Ждёт трейдера.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">taken</code></td><td>Закреплена за трейдером.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">sent</code></td><td>Трейдер отметил отправку средств.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">completed</code></td><td>Завершена, средства дошли до трейдера.</td></tr>
                                    <tr><td><code class="rounded bg-base-200 px-1">canceled</code></td><td>Отменена, средства возвращены магазину.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="statements-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Выписки</h2>
                        <p class="text-sm text-base-content/80">
                            Списки сделок и выплат по вашим магазинам. Параметры: <code class="rounded bg-base-200 px-1 text-xs">merchant_id</code> (UUID, опционально),
                            <code class="rounded bg-base-200 px-1 text-xs">sort</code> (<code class="rounded bg-base-200 px-1 text-xs">new</code> | <code class="rounded bg-base-200 px-1 text-xs">old</code>),
                            <code class="rounded bg-base-200 px-1 text-xs">per_page</code> (1–100), <code class="rounded bg-base-200 px-1 text-xs">page</code>.
                        </p>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/statements/orders</code>
                                <span class="text-base-content/70">Список сделок</span>
                            </div>
                            <CodeBlock :code="statementsOrdersResponse" lang="jsonc" label="Ответ" />
                        </section>

                        <section class="space-y-3 rounded-xl border border-base-200 p-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="badge badge-primary badge-lg">GET</span>
                                <code class="rounded bg-base-200 px-2 py-1 text-sm">/api/statements/payouts</code>
                                <span class="text-base-content/70">Список выплат</span>
                            </div>
                            <CodeBlock :code="statementsPayoutsResponse" lang="jsonc" label="Ответ" />
                        </section>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
