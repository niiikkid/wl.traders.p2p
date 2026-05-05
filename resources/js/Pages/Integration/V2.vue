<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });

const formatJSON = (value) => JSON.stringify(value, null, 2);

const sections = [
    { id: 'overview', title: 'Обзор' },
    { id: 'auth', title: 'Авторизация' },
    { id: 'responses', title: 'Ответы' },
    { id: 'endpoints', title: 'Эндпоинты' },
    { id: 'lists', title: 'Списки' },
    { id: 'payin', title: 'Payin' },
    { id: 'disputes', title: 'Disputes' },
    { id: 'payout', title: 'Payout' },
    { id: 'callbacks', title: 'Callbacks' },
];

const activeTab = ref(sections[0]?.id ?? 'overview');
const showPayinCodeSamples = ref(false);
const showPayoutCodeSamples = ref(false);

const endpoints = [
    { method: 'GET', path: '/api/v2/currencies', description: 'Список валют и точность значений.' },
    { method: 'GET', path: '/api/v2/wallet/balance', description: 'Доступный баланс кошелька мерчанта.' },
    { method: 'GET', path: '/api/v2/payin', description: 'Список payin-сделок с пагинацией.' },
    { method: 'POST', path: '/api/v2/payin', description: 'Создание payin-сделки.' },
    { method: 'GET', path: '/api/v2/payin/{payin_id}', description: 'Получение payin-сделки по UUID.' },
    { method: 'GET', path: '/api/v2/payin/external/{external_id}', description: 'Получение payin-сделки по внешнему ID в рамках мерчанта API token.' },
    { method: 'PATCH', path: '/api/v2/payin/{payin_id}/cancel', description: 'Отмена payin-сделки.' },
    { method: 'POST', path: '/api/v2/payin/{payin_id}/confirmation-code', description: 'Передача кода подтверждения для Manual Control Acquiring.' },
    { method: 'POST', path: '/api/v2/payin/{payin_id}/dispute', description: 'Открытие спора по payin-сделке.' },
    { method: 'GET', path: '/api/v2/payin/{payin_id}/dispute', description: 'Получение информации о споре.' },
    { method: 'GET', path: '/api/v2/payout', description: 'Список выплат с пагинацией.' },
    { method: 'POST', path: '/api/v2/payout', description: 'Создание выплаты.' },
    { method: 'GET', path: '/api/v2/payout/{payout_id}', description: 'Получение выплаты по UUID.' },
    { method: 'PATCH', path: '/api/v2/payout/{payout_id}/cancel', description: 'Отмена выплаты.' },
    { method: 'GET', path: '/api/v2/payout/{payout_id}/receipts', description: 'Получение чеков выплаты в base64.' },
];

const listQueryFields = [
    { name: 'page', type: 'integer', required: false, description: 'Номер страницы, минимум 1.' },
    { name: 'per_page', type: 'integer', required: false, description: 'Количество записей на странице: от 1 до 100. По умолчанию 20.' },
    { name: 'sort', type: 'string', required: false, description: 'new или old. По умолчанию new: новые записи первыми.' },
];

const payinFields = [
    { name: 'external_id', type: 'string', required: true, description: 'Уникальный ID сделки в вашей системе, максимум 255 символов. Повторное создание с тем же external_id для одного мерчанта вернёт ошибку.' },
    { name: 'amount', type: 'integer', required: true, description: 'Сумма сделки, целое положительное значение.' },
    { name: 'currency', type: 'string', required: true, description: 'Код валюты, например rub.' },
    { name: 'payin_method', type: 'string', required: true, description: 'card, sbp, mobile_commerce, iban_uah или e-com.' },
    { name: 'callback_url', type: 'string', required: false, description: 'HTTPS URL для callback по этой сделке, максимум 256 символов. Если не указан, используется URL из настроек мерчанта.' },
    { name: 'client_id', type: 'string', required: false, description: 'ID клиента в вашей системе, максимум 255 символов.' },
    { name: 'exchange_rate', type: 'decimal', required: false, description: 'Курс фиата за 1 USDT. Обязателен, если для GEO выбран merchant_api; иначе поле нельзя передавать.' },
    { name: 'manual_acquiring', type: 'boolean', required: false, description: 'Включает ручной эквайринг. Допустим только payin_method card.' },
    { name: 'card_number', type: 'string', required: false, description: 'Обязателен только при manual_acquiring=true. После удаления нецифровых символов должно остаться 12–19 цифр.' },
    { name: 'card_expiry_month, card_expiry_year, cvc', type: 'integer/string', required: false, description: 'Обязательны только при manual_acquiring=true. Месяц 1–12, год 2000–2999, cvc до 20 символов.' },
    { name: 'card_holder_name', type: 'string', required: false, description: 'Опционально только при manual_acquiring=true, максимум 255 символов.' },
];

const payinMethods = [
    { value: 'card', description: 'Оплата банковской картой.' },
    { value: 'sbp', description: 'Оплата через СБП.' },
    { value: 'mobile_commerce', description: 'Мобильная коммерция.' },
    { value: 'iban_uah', description: 'Оплата на IBAN UAH.' },
    { value: 'e-com', description: 'E-COM метод.' },
];

const payinStatuses = [
    { value: 'pending', description: 'Сделка в процессе обработки.' },
    { value: 'success', description: 'Сделка успешно завершена.' },
    { value: 'fail', description: 'Сделка завершена неуспешно.' },
];

const payinSubStatuses = [
    { value: 'waiting_for_payment', description: 'Ожидается оплата от клиента.' },
    { value: 'successfully_paid', description: 'Оплата успешно подтверждена.' },
    { value: 'cancelled', description: 'Сделка отменена.' },
    { value: 'successfully_paid_by_resolved_dispute', description: 'Сделка завершена после принятого спора.' },
    { value: 'waiting_for_dispute_to_be_resolved', description: 'Ожидается решение по спору.' },
    { value: 'canceled_by_dispute', description: 'Сделка отменена по результату спора.' },
];

const disputeStatuses = [
    { value: 'opened', description: 'Спор открыт и ожидает решения.' },
    { value: 'accepted', description: 'Спор принят, сделка засчитана в пользу мерчанта.' },
    { value: 'rejected', description: 'Спор отклонён.' },
];

/** Поля объекта `dispute` в ресурсе payin (GET, списки, callback), когда значение не null. */
const payinDisputeResourceFields = [
    {
        name: 'status',
        type: 'string',
        description: 'Текущий статус спора: одно из opened, accepted, rejected (расшифровка — в списке статусов ниже).',
    },
    {
        name: 'reason',
        type: 'string | null',
        description: 'Текст причины от провайдера/обработчика (часто при rejected), иначе null.',
    },
    {
        name: 'canceled_at',
        type: 'string | null',
        description: 'Момент отмены спора в ISO 8601 (UTC), если зафиксирован; иначе null.',
    },
];

const payinDisputeExampleObject = {
    status: 'opened',
    reason: null,
    canceled_at: null,
};

const payoutFields = [
    { name: 'external_id', type: 'string', required: true, description: 'Уникальный ID выплаты в вашей системе, максимум 255 символов.' },
    { name: 'amount', type: 'integer', required: true, description: 'Сумма выплаты, целое положительное значение.' },
    { name: 'currency', type: 'string', required: true, description: 'Код валюты, например rub.' },
    { name: 'payout_method', type: 'string', required: true, description: 'sbp или card.' },
    { name: 'payout_details', type: 'string', required: true, description: 'Реквизиты получателя: телефон, карта или другое значение метода, максимум 255 символов.' },
    { name: 'recipient_name', type: 'string', required: true, description: 'Имя получателя, максимум 255 символов.' },
    { name: 'bank_name', type: 'string', required: false, description: 'Название банка, до 30 символов.' },
    { name: 'callback_url', type: 'string', required: false, description: 'HTTPS URL для callback по этой выплате, максимум 256 символов. Если не указан, используется URL из настроек мерчанта.' },
    { name: 'exchange_rate', type: 'decimal', required: false, description: 'Курс фиата за 1 USDT. Обязателен, если для GEO выбран merchant_api; иначе поле нельзя передавать.' },
];

const payoutMethods = [
    { value: 'sbp', description: 'Выплата по номеру телефона через СБП.' },
    { value: 'card', description: 'Выплата на банковскую карту.' },
];

const payoutStatuses = [
    { value: 'open', description: 'Выплата создана и ожидает исполнителя.' },
    { value: 'taken', description: 'Выплата закреплена за трейдером.' },
    { value: 'sent', description: 'Трейдер отметил, что отправил деньги.' },
    { value: 'completed', description: 'Выплата завершена.' },
    { value: 'canceled', description: 'Выплата отменена, средства возвращены мерчанту.' },
];

const payoutSubStatuses = [
    { value: 'не используется', description: 'В API v2 у payout нет отдельного поля sub_status; используйте status.' },
];

const payinRequest = {
    external_id: 'payin-10001',
    amount: 100000,
    currency: 'rub',
    payin_method: 'card',
    callback_url: 'https://merchant.example/callbacks/payin',
    client_id: 'client-42',
    exchange_rate: '95.12345678',
};

const payinResponse = {
    success: true,
    data: {
        id: '7e2b4b44-36b9-44a9-8b61-8d4100b166e1',
        external_id: 'payin-10001',
        merchant_id: '9b2cf9e7-8e37-4bcb-8c9c-7f2b1e3c88c4',
        status: 'pending',
        sub_status: 'waiting_for_payment',
        amounts: {
            amount: { value: '100000.00', currency: 'RUB' },
            initial_amount: { value: '100000.00', currency: 'RUB' },
            exchanged_amount: { value: '1051.26', currency: 'USDT' },
            merchant_credit: { value: '1040.75', currency: 'USDT' },
        },
        exchange_rate: {
            price: { value: '95.12345678', currency: 'RUB' },
            fixed_at: '2026-05-01T00:00:00+00:00',
        },
        payin_method: 'card',
        payin_details: {
            bank_name: 'Example Bank',
            value: '220000******0000',
            recipient_name: 'Ivan Ivanov',
        },
        manual_acquiring: null,
        dispute: null,
        finished_at: null,
        created_at: '2026-05-01T00:00:00+00:00',
        current_server_time: '2026-05-01T00:00:05+00:00',
    },
};

const manualAcquiringRequest = {
    external_id: 'manual-payin-10002',
    amount: 100000,
    currency: 'rub',
    payin_method: 'card',
    callback_url: 'https://merchant.example/callbacks/payin',
    client_id: 'client-43',
    exchange_rate: '95.12345678',
    manual_acquiring: true,
    card_number: '4111111111111111',
    card_expiry_month: 12,
    card_expiry_year: 2030,
    cvc: '123',
    card_holder_name: 'IVAN IVANOV',
};

const manualAcquiringResponse = {
    success: true,
    data: {
        ...payinResponse.data,
        id: 'ff5a6f08-c548-4e67-b9cf-3f95363fdca2',
        external_id: 'manual-payin-10002',
        payin_details: null,
        manual_acquiring: {
            confirmation_type: 'otp_code',
            reject_reason: null,
        },
    },
};

const payoutRequest = {
    external_id: 'payout-20001',
    amount: 25000,
    currency: 'rub',
    payout_method: 'sbp',
    payout_details: '79990000000',
    recipient_name: 'Ivan Ivanov',
    bank_name: 'Example Bank',
    callback_url: 'https://merchant.example/callbacks/payout',
    exchange_rate: '95.12345678',
};

const payoutResponse = {
    success: true,
    data: {
        id: 'f0fbb6e3-e1a2-44f1-b3b5-8ea9f307ef8d',
        external_id: 'payout-20001',
        merchant_id: '9b2cf9e7-8e37-4bcb-8c9c-7f2b1e3c88c4',
        status: 'open',
        amounts: {
            amount: { value: '25000.00', currency: 'RUB' },
            exchanged_amount: { value: '262.81', currency: 'USDT' },
            merchant_debit: { value: '265.44', currency: 'USDT' },
            commission: { value: '2.63', currency: 'USDT' },
        },
        exchange_rate: {
            price: { value: '95.12345678', currency: 'RUB' },
            fixed_at: '2026-05-01T00:00:00+00:00',
        },
        payout_method: 'sbp',
        payout_details: {
            bank_name: 'Example Bank',
            value: '79990000000',
            recipient_name: 'Ivan Ivanov',
        },
        receipts_url: null,
        finished_at: null,
        created_at: '2026-05-01T00:00:00+00:00',
        current_server_time: '2026-05-01T00:00:05+00:00',
    },
};

const currencyResponse = {
    success: true,
    data: [
        { currency: 'rub', precision: 2, symbol: '₽', name: 'Russian Ruble' },
        { currency: 'usdt', precision: 8, symbol: 'USDT', name: 'Tether USD' },
    ],
};

const balanceResponse = {
    success: true,
    data: {
        balance: '1250.50',
    },
};

const confirmationCodeRequest = {
    confirmation_code: '123456',
};

const confirmationCodeResponse = {
    success: true,
    data: {
        payin_id: '7e2b4b44-36b9-44a9-8b61-8d4100b166e1',
        status: 'queued',
    },
};

const disputeRequest = {
    receipts: [
        'JVBERi0xLjQKJc...',
        'iVBORw0KGgoAAA...',
    ],
};

const disputeResponse = {
    success: true,
    data: {
        payin_id: '7e2b4b44-36b9-44a9-8b61-8d4100b166e1',
        status: 'opened',
        reason: null,
        canceled_at: null,
    },
};

const receiptsResponse = {
    success: true,
    data: {
        payout_id: 'f0fbb6e3-e1a2-44f1-b3b5-8ea9f307ef8d',
        receipts: [
            {
                receipt_id: 123,
                filename: 'receipt.pdf',
                mime_type: 'application/pdf',
                size: 18542,
                base64: 'JVBERi0xLjQKJc...',
            },
        ],
    },
};

const responseExamples = [
    {
        title: 'Успешный ответ',
        httpCode: 200,
        code: formatJSON({ success: true, data: { id: 'uuid', status: 'pending' } }),
    },
    {
        title: 'Ошибка бизнес-логики',
        httpCode: 400,
        code: formatJSON({ success: false, message: 'Описание ошибки' }),
    },
    {
        title: 'Неверный API token',
        httpCode: 400,
        code: formatJSON({ success: false, message: 'Invalid Access Token.' }),
    },
    {
        title: 'Ошибка валидации',
        httpCode: 422,
        code: formatJSON({ message: 'The given data was invalid.', errors: { external_id: ['Поле external_id обязательно.'] } }),
    },
    {
        title: 'Превышено ожидание payin',
        httpCode: 504,
        code: formatJSON({ success: false, message: 'Не удалось обработать запрос вовремя. Повторите попытку позже.' }),
    },
    {
        title: 'Пагинация',
        httpCode: 200,
        code: formatJSON({
            success: true,
            data: [{ id: 'uuid', status: 'pending' }],
            links: { first: 'https://example.com/api/v2/payin?page=1', last: 'https://example.com/api/v2/payin?page=1', prev: null, next: null },
            meta: { current_page: 1, per_page: 20, total: 1 },
        }),
    },
    {
        title: 'Ошибка сервера',
        httpCode: 500,
        code: formatJSON({ message: 'Internal Server Error' }),
    },
];

const codeSamples = [
    {
        language: 'curl',
        code: `curl -X POST "https://example.com/api/v2/payin" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -H "Access-Token: YOUR_API_V2_TOKEN" \\
  -H "X-Max-Wait-Ms: 30000" \\
  -d '${formatJSON(payinRequest)}'`,
    },
    {
        language: 'PHP',
        code: `<?php

$payload = json_decode(<<<'JSON'
${formatJSON(payinRequest)}
JSON, true, flags: JSON_THROW_ON_ERROR);

$ch = curl_init('https://example.com/api/v2/payin');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Access-Token: YOUR_API_V2_TOKEN',
        'X-Max-Wait-Ms: 30000',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
]);

$response = curl_exec($ch);
curl_close($ch);`,
    },
    {
        language: 'Python',
        code: `import requests

payload = ${formatJSON(payinRequest)}

response = requests.post(
    'https://example.com/api/v2/payin',
    json=payload,
    headers={
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Access-Token': 'YOUR_API_V2_TOKEN',
        'X-Max-Wait-Ms': '30000',
    },
    timeout=15,
)

print(response.json())`,
    },
    {
        language: 'Node.js',
        code: `const payload = ${formatJSON(payinRequest)};

const response = await fetch('https://example.com/api/v2/payin', {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'Access-Token': 'YOUR_API_V2_TOKEN',
    'X-Max-Wait-Ms': '30000',
  },
  body: JSON.stringify(payload),
});

console.log(await response.json());`,
    },
];

const payoutCodeSamples = [
    {
        language: 'curl',
        code: `curl -X POST "https://example.com/api/v2/payout" \\
  -H "Accept: application/json" \\
  -H "Content-Type: application/json" \\
  -H "Access-Token: YOUR_API_V2_TOKEN" \\
  -d '${formatJSON(payoutRequest)}'`,
    },
    {
        language: 'PHP',
        code: `<?php

$payload = json_decode(<<<'JSON'
${formatJSON(payoutRequest)}
JSON, true, flags: JSON_THROW_ON_ERROR);

$response = file_get_contents('https://example.com/api/v2/payout', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Access-Token: YOUR_API_V2_TOKEN',
        ],
        'content' => json_encode($payload, JSON_THROW_ON_ERROR),
    ],
]));`,
    },
    {
        language: 'Python',
        code: `import requests

payload = ${formatJSON(payoutRequest)}

response = requests.post(
    'https://example.com/api/v2/payout',
    json=payload,
    headers={
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Access-Token': 'YOUR_API_V2_TOKEN',
    },
    timeout=15,
)

print(response.json())`,
    },
    {
        language: 'Node.js',
        code: `const payload = ${formatJSON(payoutRequest)};

const response = await fetch('https://example.com/api/v2/payout', {
  method: 'POST',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'Access-Token': 'YOUR_API_V2_TOKEN',
  },
  body: JSON.stringify(payload),
});

console.log(await response.json());`,
    },
];
</script>

<template>
    <Head title="API Интеграция v2" />

    <div class="antialiased">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-2xl bg-base-100 p-5 shadow-sm ring-1 ring-base-300 sm:p-6">
                <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-start">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-primary">API v2</span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-base-content sm:text-3xl">
                                API Интеграция
                            </h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/70">
                                Здесь описана новая версия API для payin-сделок, выплат, баланса, валют и callback-уведомлений.
                            </p>
                        </div>
                    </div>

                    <Link :href="route('integration.index')" class="btn btn-outline btn-sm">
                        Legacy API
                    </Link>
                </div>
            </section>

            <div class="space-y-6">
                <section class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                    <div class="card-body gap-3 p-4">
                        <h2 class="text-sm font-semibold">Содержание</h2>
                        <div class="overflow-x-auto">
                            <div class="tabs tabs-boxed w-max min-w-full flex-nowrap bg-base-200 rounded-lg p-1">
                                <button
                                    v-for="section in sections"
                                    :key="section.id"
                                    type="button"
                                    class="tab whitespace-nowrap"
                                    :class="{ 'tab-active font-semibold': activeTab === section.id }"
                                    @click="activeTab = section.id"
                                >
                                    {{ section.title }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <main class="space-y-6">
                    <article v-if="activeTab === 'overview'" id="overview" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-4">
                            <h2 class="card-title">Обзор</h2>
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="rounded-xl bg-base-200 p-4">
                                    <div class="text-xs uppercase tracking-wide text-base-content/50">Base URL</div>
                                    <code class="mt-2 block break-all text-sm font-semibold">https://example.com/api/v2</code>
                                </div>
                                <div class="rounded-xl bg-base-200 p-4">
                                    <div class="text-xs uppercase tracking-wide text-base-content/50">Формат</div>
                                    <div class="mt-2 text-sm font-semibold">JSON over HTTPS</div>
                                </div>
                                <div class="rounded-xl bg-base-200 p-4">
                                    <div class="text-xs uppercase tracking-wide text-base-content/50">Версия</div>
                                    <div class="mt-2 text-sm font-semibold">v2, отдельные API keys</div>
                                </div>
                            </div>

                            <div role="alert" class="alert alert-info py-3 text-sm">
                                <span>
                                    API v2 использует токены из настроек мерчанта: <strong>API token</strong> для входящих запросов
                                    и <strong>Callback token</strong> для проверки исходящих callback-уведомлений. API token всегда привязан
                                    к одному мерчанту, поэтому <code class="rounded bg-base-200 px-1">merchant_id</code> в запросах API v2
                                    передавать не нужно.
                                </span>
                            </div>

                            <div class="rounded-xl border border-base-300 bg-base-200/60 p-4 text-sm">
                                <h3 class="mb-2 font-semibold">Что такое payin в API v2</h3>
                                <p class="text-base-content/70">
                                    Payin в API v2 — это каскадная сделка. Вы отправляете один запрос в
                                    <code class="rounded bg-base-300 px-1">/api/v2/payin</code>, а система сама выбирает внутреннего
                                    или внешнего провайдера. Некоторые операции с внешним провайдером выполняются через очередь,
                                    поэтому финальное изменение статуса может прийти позже в callback.
                                </p>
                            </div>

                            <div class="rounded-xl border border-base-300 bg-base-200/60 p-4 text-sm">
                                <h3 class="mb-2 font-semibold">Суммы, валюты и курс</h3>
                                <p class="text-base-content/70">
                                    В запросах поле <code class="rounded bg-base-300 px-1">amount</code> передаётся целым положительным числом.
                                    Коды валют берите из <code class="rounded bg-base-300 px-1">GET /api/v2/currencies</code>.
                                    В ответах суммы возвращаются строками в <code class="rounded bg-base-300 px-1">amounts.*.value</code>,
                                    а код валюты — в верхнем регистре. Если для GEO выбран источник курса
                                    <code class="rounded bg-base-300 px-1">merchant_api</code>, передавайте
                                    <code class="rounded bg-base-300 px-1">exchange_rate</code> как курс фиата за 1 USDT; иначе это поле передавать нельзя.
                                </p>
                            </div>

                            <div class="rounded-xl border border-base-300 bg-base-200/60 p-4 text-sm">
                                <h3 class="mb-2 font-semibold">Формат времени</h3>
                                <p class="text-base-content/70">
                                    Все поля времени в API передаются в формате ISO 8601 (RFC 3339), например
                                    <code class="rounded bg-base-300 px-1">2026-05-01T00:00:05+00:00</code>.
                                    Рекомендуем парсить их как datetime c timezone (UTC offset указан прямо в строке).
                                </p>
                            </div>
                        </div>
                    </article>

                    <article v-if="activeTab === 'auth'" id="auth" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-4">
                            <h2 class="card-title">Авторизация</h2>
                            <p class="text-sm leading-6 text-base-content/70">
                                Каждый запрос к API v2 должен содержать заголовок <code class="rounded bg-base-200 px-1">Access-Token</code>.
                                Используйте именно API token из раздела ключей API v2, а не legacy-токен.
                            </p>
                            <div role="alert" class="alert alert-warning py-3 text-sm">
                                <span>
                                    Один API token v2 принадлежит одному мерчанту. Все payin, payout, баланс и списки выполняются только
                                    в контексте мерчанта этого токена. Не передавайте <code class="rounded bg-base-200 px-1">merchant_id</code>
                                    в теле запроса, query-параметрах или URL: API определит мерчанта автоматически по
                                    <code class="rounded bg-base-200 px-1">Access-Token</code>.
                                </span>
                            </div>
                            <pre class="overflow-x-auto rounded-xl bg-base-300 p-4 text-sm"><code>Accept: application/json
Content-Type: application/json
Access-Token: YOUR_API_V2_TOKEN</code></pre>
                            <p class="text-sm leading-6 text-base-content/70">
                                Если токен отсутствует, неверный или относится к архивному пользователю, API вернёт
                                <code class="rounded bg-base-200 px-1">{ success: false, message: 'Invalid Access Token.' }</code>.
                            </p>
                        </div>
                    </article>

                    <article v-if="activeTab === 'responses'" id="responses" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-4">
                            <h2 class="card-title">Формат ответов</h2>
                            <div class="flex flex-col gap-4">
                                <div v-for="example in responseExamples" :key="example.title" class="rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <h3 class="mb-3 flex flex-wrap items-center gap-2 font-semibold">
                                        <span>{{ example.title }}</span>
                                        <span class="badge badge-sm badge-neutral">HTTP {{ example.httpCode }}</span>
                                    </h3>
                                    <pre class="max-h-80 overflow-auto rounded-lg bg-base-300 p-3 text-xs"><code>{{ example.code }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article v-if="activeTab === 'endpoints'" id="endpoints" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-4">
                            <h2 class="card-title">Эндпоинты</h2>
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Метод</th>
                                        <th>Путь</th>
                                        <th>Описание</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="endpoint in endpoints" :key="`${endpoint.method}-${endpoint.path}`">
                                        <td>
                                            <span class="badge badge-outline">{{ endpoint.method }}</span>
                                        </td>
                                        <td><code class="whitespace-nowrap rounded bg-base-200 px-2 py-1">{{ endpoint.path }}</code></td>
                                        <td class="min-w-72 text-base-content/70">{{ endpoint.description }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </article>

                    <article v-if="activeTab === 'lists'" id="lists" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-5">
                            <h2 class="card-title">Списки, валюты и баланс</h2>
                            <section class="space-y-3">
                                <h3 class="font-semibold">Параметры списков payin и payout</h3>
                                <p class="text-sm text-base-content/70">
                                    Эти query-параметры поддерживаются в
                                    <code class="rounded bg-base-200 px-1">GET /api/v2/payin</code> и
                                    <code class="rounded bg-base-200 px-1">GET /api/v2/payout</code>.
                                </p>
                                <div class="overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Параметр</th>
                                            <th>Тип</th>
                                            <th>Обязательный</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="field in listQueryFields" :key="field.name">
                                            <td><code class="rounded bg-base-200 px-2 py-1">{{ field.name }}</code></td>
                                            <td>{{ field.type }}</td>
                                            <td>
                                                <span class="badge badge-sm" :class="field.required ? 'badge-error' : 'badge-ghost'">
                                                    {{ field.required ? 'Да' : 'Нет' }}
                                                </span>
                                            </td>
                                            <td class="min-w-72 text-base-content/70">{{ field.description }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <section>
                                    <h3 class="mb-2 font-semibold">GET /api/v2/currencies</h3>
                                    <p class="mb-2 text-sm text-base-content/70">
                                        Используйте этот метод, чтобы проверить доступные коды валют и точность значений.
                                    </p>
                                    <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(currencyResponse) }}</code></pre>
                                </section>
                                <section>
                                    <h3 class="mb-2 font-semibold">GET /api/v2/wallet/balance</h3>
                                    <p class="mb-2 text-sm text-base-content/70">
                                        Возвращает доступный баланс merchant-кошелька.
                                    </p>
                                    <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(balanceResponse) }}</code></pre>
                                </section>
                            </div>
                        </div>
                    </article>

                    <article v-if="activeTab === 'payin'" id="payin" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="card-title">Payin</h2>
                                    <p class="mt-2 text-sm text-base-content/70">
                                        Payin-сделка создаётся через <code class="rounded bg-base-200 px-1">POST /api/v2/payin</code>.
                                        Поле <code class="rounded bg-base-200 px-1">id</code> в ответе — это UUID каскадной сделки;
                                        используйте его в путях <code class="rounded bg-base-200 px-1">/payin/{payin_id}</code>.
                                        Поиск по вашему внешнему ID выполняется через
                                        <code class="rounded bg-base-200 px-1">GET /api/v2/payin/external/{external_id}</code>.
                                        Поле <code class="rounded bg-base-200 px-1">dispute</code> имеет тип «<code class="rounded bg-base-200 px-1">null</code>
                                        или объект»: при отсутствии спора — <code class="rounded bg-base-200 px-1">null</code>; после открытия спора всегда объект
                                        с тремя полями (см. таблицу и пример ниже), он сохраняется при смене и завершении спора.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm"
                                    @click="showPayinCodeSamples = !showPayinCodeSamples"
                                >
                                    {{ showPayinCodeSamples ? 'Скрыть примеры кода' : 'Примеры кода' }}
                                </button>
                            </div>

                            <div role="alert" class="alert alert-info py-3 text-sm">
                                <span>
                                    Очень просим при создании payin-сделки передавать поле
                                    <code class="rounded bg-base-200 px-1 font-semibold">client_id</code>,
                                    если в вашей системе есть идентификатор клиента.
                                </span>
                            </div>

                            <div role="alert" class="alert alert-warning py-3 text-sm">
                                <span>
                                    <code class="rounded bg-base-200 px-1">external_id</code> должен относиться к сделке того мерчанта,
                                    чей API token указан в <code class="rounded bg-base-200 px-1">Access-Token</code>. UUID мерчанта
                                    в запросе не передаётся.
                                </span>
                            </div>

                            <div role="alert" class="alert alert-info py-3 text-sm">
                                <span>
                                    При создании payin можно передать заголовок
                                    <code class="rounded bg-base-200 px-1 font-semibold">X-Max-Wait-Ms</code> — сколько миллисекунд ждать
                                    подбора реквизитов. Максимум и значение по умолчанию: <code class="rounded bg-base-200 px-1">30000</code>.
                                    Если ожидание истекло, API вернёт <code class="rounded bg-base-200 px-1">504</code>, а уже запущенные
                                    provider-попытки будут остановлены или отменены после создания.
                                </span>
                            </div>

                            <template v-if="!showPayinCodeSamples">
                                <section class="space-y-3">
                                    <h3 class="font-semibold">Поля запроса</h3>
                                    <div class="overflow-x-auto">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>Поле</th>
                                                <th>Тип</th>
                                                <th>Обязательное</th>
                                                <th>Описание</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="field in payinFields" :key="field.name">
                                                <td><code class="rounded bg-base-200 px-2 py-1">{{ field.name }}</code></td>
                                                <td>{{ field.type }}</td>
                                                <td>
                                                    <span class="badge badge-sm" :class="field.required ? 'badge-error' : 'badge-ghost'">
                                                        {{ field.required ? 'Да' : 'Нет' }}
                                                    </span>
                                                </td>
                                                <td class="min-w-72 text-base-content/70">{{ field.description }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <div class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Методы payin</div>
                                        <div class="space-y-1.5">
                                            <div v-for="method in payinMethods" :key="method.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ method.value }}</code>
                                                <span class="text-base-content/70">{{ method.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Status</div>
                                        <div class="space-y-1.5">
                                            <div v-for="status in payinStatuses" :key="status.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ status.value }}</code>
                                                <span class="text-base-content/70">{{ status.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Sub status</div>
                                        <div class="space-y-1.5">
                                            <div v-for="status in payinSubStatuses" :key="status.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ status.value }}</code>
                                                <span class="text-base-content/70">{{ status.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-sm text-base-content/70">
                                        Значения <code class="rounded bg-base-300 px-1">cancelled</code> и
                                        <code class="rounded bg-base-300 px-1">canceled_by_dispute</code> отличаются намеренно:
                                        первое означает обычную отмену, второе — отмену по результату спора.
                                    </p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <section>
                                        <h3 class="mb-2 font-semibold">Пример запроса</h3>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payinRequest) }}</code></pre>
                                    </section>
                                    <section>
                                        <h3 class="mb-2 font-semibold">Пример ответа</h3>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payinResponse) }}</code></pre>
                                    </section>
                                </div>

                                <section class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <h3 class="font-semibold">Поле dispute в теле payin</h3>
                                    <p class="text-sm text-base-content/70">
                                        Во всех ответах, где возвращается сделка целиком (создание, GET по id или external id, список, callback),
                                        поле <code class="rounded bg-base-300 px-1">dispute</code> либо <code class="rounded bg-base-300 px-1">null</code>,
                                        либо объект следующей структуры — третьих вариантов нет.
                                    </p>
                                    <div class="overflow-x-auto">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>Поле</th>
                                                <th>Тип</th>
                                                <th>Описание</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="field in payinDisputeResourceFields" :key="field.name">
                                                <td><code class="rounded bg-base-200 px-2 py-1">{{ field.name }}</code></td>
                                                <td>{{ field.type }}</td>
                                                <td class="min-w-72 text-base-content/70">{{ field.description }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <h4 class="mb-2 text-sm font-semibold">Пример значения (спор открыт)</h4>
                                        <pre class="max-h-48 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payinDisputeExampleObject) }}</code></pre>
                                    </div>
                                </section>

                                <section class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div>
                                        <h3 class="font-semibold">Manual Acquiring</h3>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            Используйте <code class="rounded bg-base-300 px-1">manual_acquiring=true</code> только с
                                            <code class="rounded bg-base-300 px-1">payin_method=card</code>. В этом режиме вы передаёте карточные данные,
                                            а в ответе поле <code class="rounded bg-base-300 px-1">payin_details</code> будет <code class="rounded bg-base-300 px-1">null</code>,
                                            а <code class="rounded bg-base-300 px-1">manual_acquiring</code> показывает тип подтверждения или причину отказа.
                                            Если требуется OTP/код подтверждения, отправьте его в
                                            <code class="rounded bg-base-300 px-1">POST /api/v2/payin/{payin_id}/confirmation-code</code>.
                                        </p>
                                    </div>
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <section>
                                            <h4 class="mb-2 text-sm font-semibold">Запрос</h4>
                                            <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(manualAcquiringRequest) }}</code></pre>
                                        </section>
                                        <section>
                                            <h4 class="mb-2 text-sm font-semibold">Ответ</h4>
                                            <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(manualAcquiringResponse) }}</code></pre>
                                        </section>
                                    </div>
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <section>
                                            <h4 class="mb-2 text-sm font-semibold">Передача кода подтверждения</h4>
                                            <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(confirmationCodeRequest) }}</code></pre>
                                        </section>
                                        <section>
                                            <h4 class="mb-2 text-sm font-semibold">Ответ</h4>
                                            <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(confirmationCodeResponse) }}</code></pre>
                                        </section>
                                    </div>
                                </section>
                            </template>

                            <section v-else class="space-y-3">
                                <div v-for="sample in codeSamples" :key="`payin-${sample.language}`" class="rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div class="mb-2 badge badge-outline">{{ sample.language }}</div>
                                    <pre class="max-h-96 overflow-auto rounded-lg bg-base-300 p-3 text-xs"><code>{{ sample.code }}</code></pre>
                                </div>
                            </section>
                        </div>
                    </article>

                    <article v-if="activeTab === 'disputes'" id="disputes" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-5">
                            <div>
                                <h2 class="card-title">Disputes</h2>
                                <p class="mt-2 text-sm text-base-content/70">
                                    Спор открывается по payin-сделке через
                                    <code class="rounded bg-base-200 px-1">POST /api/v2/payin/{payin_id}/dispute</code>.
                                    Отмены спора со стороны мерчанта в API v2 нет: после открытия спор должен быть обработан трейдером
                                    или внешним провайдером.
                                </p>
                            </div>

                            <div class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                <div>
                                    <h3 class="text-sm font-semibold">Объект dispute в ресурсе payin</h3>
                                    <p class="mt-1 text-sm text-base-content/70">
                                        В <code class="rounded bg-base-300 px-1">GET /api/v2/payin/…</code>, списках payin и callback поле
                                        <code class="rounded bg-base-300 px-1">dispute</code> — либо <code class="rounded bg-base-300 px-1">null</code>
                                        (спор ещё не открыт), либо объект с фиксированным набором полей (ниже). После появления объекта он не возвращается к
                                        <code class="rounded bg-base-300 px-1">null</code> при смене статуса спора.
                                        Раньше при отсутствии спора приходил объект с тремя полями, в каждом из которых было
                                        <code class="rounded bg-base-300 px-1">null</code>; теперь в этом случае — одно значение
                                        <code class="rounded bg-base-300 px-1">null</code>.
                                    </p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Поле</th>
                                            <th>Тип</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr v-for="field in payinDisputeResourceFields" :key="`disputes-${field.name}`">
                                            <td><code class="rounded bg-base-200 px-2 py-1">{{ field.name }}</code></td>
                                            <td>{{ field.type }}</td>
                                            <td class="min-w-72 text-base-content/70">{{ field.description }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div>
                                    <h4 class="mb-2 text-sm font-semibold">Пример JSON объекта</h4>
                                    <pre class="max-h-48 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payinDisputeExampleObject) }}</code></pre>
                                </div>
                                <p class="text-sm text-base-content/70">
                                    В ответах эндпоинта <code class="rounded bg-base-300 px-1">GET/POST …/dispute</code> поле
                                    <code class="rounded bg-base-300 px-1">canceled_at</code> может быть Unix timestamp или
                                    <code class="rounded bg-base-300 px-1">null</code> — это другой контракт, не путать с
                                    <code class="rounded bg-base-300 px-1">dispute.canceled_at</code> внутри payin (там только ISO 8601 или
                                    <code class="rounded bg-base-300 px-1">null</code>).
                                </p>
                                <div class="text-sm">
                                    <div class="mb-2 font-semibold">Статусы спора (значение dispute.status)</div>
                                    <div class="space-y-1.5">
                                        <div v-for="status in disputeStatuses" :key="status.value" class="flex flex-wrap items-start gap-2">
                                            <code class="rounded bg-base-300 px-2 py-0.5">{{ status.value }}</code>
                                            <span class="text-base-content/70">{{ status.description }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <section class="space-y-3">
                                <h3 class="font-semibold">Тело запроса</h3>
                                <p class="text-sm text-base-content/70">
                                    Передавайте JSON с массивом <code class="rounded bg-base-200 px-1">receipts</code>.
                                    Каждый элемент — base64-строка файла jpeg, jpg, png или pdf; лимит на один файл — 5120 KB.
                                </p>
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <section>
                                        <h4 class="mb-2 text-sm font-semibold">Запрос</h4>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(disputeRequest) }}</code></pre>
                                    </section>
                                    <section>
                                        <h4 class="mb-2 text-sm font-semibold">Ответ</h4>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(disputeResponse) }}</code></pre>
                                    </section>
                                </div>
                            </section>
                        </div>
                    </article>

                    <article v-if="activeTab === 'payout'" id="payout" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="card-title">Payout</h2>
                                    <p class="mt-2 text-sm text-base-content/70">
                                        Выплата создаётся через <code class="rounded bg-base-200 px-1">POST /api/v2/payout</code>.
                                        Для выплаты нет отдельного <code class="rounded bg-base-200 px-1">sub_status</code> — ориентируйтесь на поле
                                        <code class="rounded bg-base-200 px-1">status</code>. Мерчант определяется по
                                        <code class="rounded bg-base-200 px-1">Access-Token</code>, поэтому
                                        <code class="rounded bg-base-200 px-1">merchant_id</code> в теле запроса не используется.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm"
                                    @click="showPayoutCodeSamples = !showPayoutCodeSamples"
                                >
                                    {{ showPayoutCodeSamples ? 'Скрыть примеры кода' : 'Примеры кода' }}
                                </button>
                            </div>

                            <template v-if="!showPayoutCodeSamples">
                                <section class="space-y-3">
                                    <h3 class="font-semibold">Поля запроса</h3>
                                    <div class="overflow-x-auto">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>Поле</th>
                                                <th>Тип</th>
                                                <th>Обязательное</th>
                                                <th>Описание</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="field in payoutFields" :key="field.name">
                                                <td><code class="rounded bg-base-200 px-2 py-1">{{ field.name }}</code></td>
                                                <td>{{ field.type }}</td>
                                                <td>
                                                    <span class="badge badge-sm" :class="field.required ? 'badge-error' : 'badge-ghost'">
                                                        {{ field.required ? 'Да' : 'Нет' }}
                                                    </span>
                                                </td>
                                                <td class="min-w-72 text-base-content/70">{{ field.description }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <div class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Методы payout</div>
                                        <div class="space-y-1.5">
                                            <div v-for="method in payoutMethods" :key="method.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ method.value }}</code>
                                                <span class="text-base-content/70">{{ method.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Status</div>
                                        <div class="space-y-1.5">
                                            <div v-for="status in payoutStatuses" :key="status.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ status.value }}</code>
                                                <span class="text-base-content/70">{{ status.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm">
                                        <div class="mb-2 font-semibold">Sub status</div>
                                        <div class="space-y-1.5">
                                            <div v-for="status in payoutSubStatuses" :key="status.value" class="flex flex-wrap items-start gap-2">
                                                <code class="rounded bg-base-300 px-2 py-0.5">{{ status.value }}</code>
                                                <span class="text-base-content/70">{{ status.description }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <section>
                                        <h3 class="mb-2 font-semibold">Пример запроса</h3>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payoutRequest) }}</code></pre>
                                    </section>
                                    <section>
                                        <h3 class="mb-2 font-semibold">Пример ответа</h3>
                                        <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payoutResponse) }}</code></pre>
                                    </section>
                                </div>

                                <section class="space-y-3 rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div>
                                        <h3 class="font-semibold">Чеки выплаты</h3>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            Если в объекте выплаты поле <code class="rounded bg-base-300 px-1">receipts_url</code> не равно
                                            <code class="rounded bg-base-300 px-1">null</code>, по нему можно получить чеки в base64.
                                            Если чеков нет или файл не найден, API вернёт ошибку 404.
                                        </p>
                                    </div>
                                    <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(receiptsResponse) }}</code></pre>
                                </section>

                                <div role="alert" class="alert alert-info py-3 text-sm">
                                    <span>
                                        При отмене выплаты <code class="font-bold px-1">PATCH /api/v2/payout/{payout_id}/cancel</code>
                                        ответ содержит поле <code class="font-bold px-1">message</code> и обновлённый объект выплаты в
                                        <code class="font-bold px-1">data</code>.
                                    </span>
                                </div>
                            </template>

                            <section v-else class="space-y-3">
                                <div v-for="sample in payoutCodeSamples" :key="`payout-${sample.language}`" class="rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <div class="mb-2 badge badge-outline">{{ sample.language }}</div>
                                    <pre class="max-h-96 overflow-auto rounded-lg bg-base-300 p-3 text-xs"><code>{{ sample.code }}</code></pre>
                                </div>
                            </section>
                        </div>
                    </article>

                    <article v-if="activeTab === 'callbacks'" id="callbacks" class="card bg-base-100 shadow-sm ring-1 ring-base-300">
                        <div class="card-body gap-4">
                            <h2 class="card-title">Callbacks</h2>
                            <p class="text-sm leading-6 text-base-content/70">
                                Callback отправляется POST-запросом на URL из запроса или из настроек мерчанта. Для API v2 есть отдельные callback
                                по payin и payout, оба подписываются <code class="rounded bg-base-200 px-1">Callback token</code> в заголовке
                                <code class="rounded bg-base-200 px-1">Access-Token</code>.
                            </p>
                            <div class="rounded-xl border border-base-300 bg-base-200/60 p-4 text-sm">
                                <h3 class="mb-2 font-semibold">
                                    Заголовок <code class="rounded bg-base-300 px-1">X-Callback-Revision</code>
                                </h3>
                                <p class="text-base-content/70">
                                    В callback API v2 ревизия передаётся не в теле, а в HTTP-заголовке
                                    <code class="rounded bg-base-300 px-1">X-Callback-Revision</code>. Значение — целое неотрицательное число,
                                    которое монотонно растёт при каждом новом уведомлении об изменении данных, попадающих в ресурс:
                                    новая ревизия означает новую версию состояния для доставки мерчанту.
                                </p>
                                <ul class="mt-2 list-inside list-disc space-y-1 text-base-content/70">
                                    <li>
                                        Повторная отправка одного и того же уведомления (ретрай после сетевой ошибки или не-2xx) использует
                                        <strong>ту же</strong> ревизию и тот же смысл полей — обработайте идемпотентно: если ревизия уже была
                                        применена, ответьте 2xx и не меняйте состояние повторно.
                                    </li>
                                    <li>
                                        Если приходит ревизия <strong>меньше</strong>, чем уже обработанная у вас для этой сделки или выплаты,
                                        уведомление устарело (например, опоздавший ретрай) — безопасно ответить 2xx и игнорировать тело.
                                    </li>
                                    <li>
                                        Обычные ответы API v2 не содержат ревизию. Используйте
                                        <code class="rounded bg-base-300 px-1">X-Callback-Revision</code> только при обработке callback и
                                        не ориентируйтесь для идемпотентности на <code class="rounded bg-base-300 px-1">current_server_time</code>.
                                    </li>
                                </ul>
                            </div>
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <h3 class="mb-2 font-semibold">Заголовки callback</h3>
                                    <pre class="overflow-x-auto rounded-lg bg-base-300 p-3 text-xs"><code>Accept: application/json
Access-Token: YOUR_CALLBACK_TOKEN
X-Callback-Revision: 1</code></pre>
                                </div>
                                <div class="rounded-xl border border-base-300 bg-base-200/60 p-4">
                                    <h3 class="mb-2 font-semibold">Ожидаемый ответ</h3>
                                    <p class="text-sm text-base-content/70">
                                        Верните любой HTTP 2xx, если уведомление принято. Ответ сохраняется в логах callback.
                                        Если вернуть не-2xx или запрос завершится ошибкой, callback может быть отправлен повторно.
                                    </p>
                                </div>
                            </div>
                            <section class="space-y-3">
                                <h3 class="font-semibold">Payin callback</h3>
                                <p class="text-sm text-base-content/70">
                                    Тело callback совпадает с ресурсом payin без обёртки <code class="rounded bg-base-200 px-1">success/data</code>.
                                </p>
                                <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payinResponse.data) }}</code></pre>
                            </section>

                            <section class="space-y-3">
                                <h3 class="font-semibold">Payout callback</h3>
                                <p class="text-sm text-base-content/70">
                                    Тело callback совпадает с ресурсом payout без обёртки <code class="rounded bg-base-200 px-1">success/data</code>.
                                </p>
                                <pre class="max-h-96 overflow-auto rounded-xl bg-base-300 p-4 text-xs"><code>{{ formatJSON(payoutResponse.data) }}</code></pre>
                            </section>
                        </div>
                    </article>
                </main>
            </div>
        </div>
    </div>
</template>
