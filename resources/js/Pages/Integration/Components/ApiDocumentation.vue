<script setup>
const formatJSON = (obj) => JSON.stringify(obj, null, 2);

const tocSections = [
    {id: 'about', title: 'Введение'},
    {id: 'base', title: 'Основы'},
    {id: 'order-statuses', title: 'Статусы сделок'},
    {id: 'callback', title: 'Callbacks'},
    {id: 'base-methods', title: 'Базовые методы'},
    {id: 'merchant-api', title: 'H2Form API'},
    {id: 'h2h-api', title: 'H2Host API'},
    {id: 'auto-withdrawals', title: 'Авто вывод'},
    {id: 'payouts-api', title: 'Payouts API'},
    {id: 'statements-api', title: 'Выписки'},
];
</script>

<template>
    <div class="space-y-10">
        <div class="grid grid-cols-1 xl:flex gap-6">
            <aside>
                <div class="card menu menu-sm p-0 bg-base-100 shadow sticky top-6 w-full">
                    <div class="card-body">
                        <h3 class="card-title text-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            Содержание
                        </h3>
                        <ul class="w-full">
                            <li v-for="section in tocSections" :key="section.id">
                                <a :href="`#${section.id}`" class="truncate">
                                    {{ section.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>

            <div class="space-y-6">
                <article id="about" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Введение</h2>
                        <p class="text-base-content/80">
                            Ниже представлено описание того как работает API, с помощью которого вы сможете сделать интеграцию с нашим сервисом.
                        </p>
                    </div>
                </article>

                <article id="base" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Основы работы API</h2>

                        <section class="space-y-4">
                            <div>
                                <p class="text-base-content/80">
                                    Все запросы к API должны содержать обязательные заголовки:
                                </p>
                                <ul class="list-disc list-inside space-y-2 mt-3 text-base-content/80 ml-2">
                                    <li><strong>Accept: application/json</strong> — формат ответа.</li>
                                    <li><strong>Access-Token: token</strong> — ключ авторизации из раздела «Интеграция».</li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-xl font-semibold mb-3">Ответы сервера</h3>
                                <div class="join join-vertical w-full space-y-2">
                                    <div class="collapse collapse-arrow bg-base-200 join-item">
                                        <input type="checkbox" checked />
                                        <div class="collapse-title text-lg font-medium">HTTP 200 - Успех</div>
                                        <div class="collapse-content">
                                            <pre class="bg-base-300 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: [] }) }}</code></pre>
                                        </div>
                                    </div>
                                    <div class="collapse collapse-arrow bg-base-200 join-item">
                                        <input type="checkbox" />
                                        <div class="collapse-title text-lg font-medium">HTTP 422 - Ошибка валидации</div>
                                        <div class="collapse-content">
                                            <div class="overflow-x-auto">
                                                <pre class="bg-base-300 p-4 rounded-lg text-sm min-w-fit"><code>{{ formatJSON({ message: "Общее описание ошибки", errors: { "название поля": ["Описание ошибки"] } }) }}</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="collapse collapse-arrow bg-base-200 join-item">
                                        <input type="checkbox" />
                                        <div class="collapse-title text-lg font-medium">HTTP 400 - Ошибки бизнес-логики</div>
                                        <div class="collapse-content">
                                            <pre class="bg-base-300 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: false, message: "Описание ошибки" }) }}</code></pre>
                                        </div>
                                    </div>
                                    <div class="collapse collapse-arrow bg-base-200 join-item">
                                        <input type="checkbox" />
                                        <div class="collapse-title text-lg font-medium">HTTP 500 - Ошибка сервера</div>
                                        <div class="collapse-content">
                                            <pre class="bg-base-300 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ message: "Internal Server Error" }) }}</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="order-statuses" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Описание статусов сделок</h2>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">Status</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                    <tr>
                                        <th>Значение</th>
                                        <th>Описание</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">success</code></td>
                                        <td>Сделка успешно завершена.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">pending</code></td>
                                        <td>Сделка находится в обработке.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">fail</code></td>
                                        <td>Сделка завершилась неудачно.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="space-y-3">
                            <h3 class="text-xl font-semibold">Sub Status</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                    <tr>
                                        <th>Значение</th>
                                        <th>Описание</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">accepted</code></td>
                                        <td>Закрыта вручную.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">successfully_paid</code></td>
                                        <td>Закрыта автоматически.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">successfully_paid_by_resolved_dispute</code></td>
                                        <td>Закрыта в результате принятого спора.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">waiting_details_to_be_selected</code></td>
                                        <td>Ждёт выбора реквизитов.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">waiting_for_payment</code></td>
                                        <td>Ждёт платежа.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">waiting_for_dispute_to_be_resolved</code></td>
                                        <td>Ждёт решения спора.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">canceled_by_dispute</code></td>
                                        <td>Отменёна в результате спора.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">expired</code></td>
                                        <td>Отменёна по истечению времени.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="bg-base-200 px-1 rounded">cancelled</code></td>
                                        <td>Отменёна вручную.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="callback" class="card bg-base-100 shadow">
                    <div class="card-body space-y-3">
                        <h2 class="card-title text-2xl">Callbacks</h2>
                        <div class="list-disc list-inside space-y-2 text-base-content/80 ml-2">
                            POST-уведомление отправляется на ссылку из настроек магазина (или указанную в callback_url при создании сделки) каждый раз при изменении статуса сделки.
                        </div>
                    </div>
                </article>


                <article id="base-methods" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Базовые методы</h2>

                        <div class="grid gap-6">
                            <section class="p-4 rounded-xl border border-base-200 space-y-3">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Доступные валюты</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/currencies</code>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: [{ currency: "rub", precision: 2, symbol: "₽", name: "Российский рубль" }] }) }}</code></pre>
                                </div>
                            </section>

                            <section class="p-4 rounded-xl border border-base-200 space-y-3">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Доступные платежные методы</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payment-gateways</code>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: [{ name: "Сбербанк", code: "sberbank", schema: "100000000111", currency: "rub", min_limit: "1000", max_limit: "100000", reservation_time: 10, detail_types: ["card", "phone", "mobile_commerce", "account_number", "nspk"] }] }) }}</code></pre>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>

                <article id="merchant-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Host To Form API</h2>

                        <section class="space-y-4">
                            <div class="rounded-xl border border-base-200 p-4 space-y-4">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Создать сделку</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-secondary badge-lg">POST</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/merchant/order</code>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Заголовки</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm text-base-content/80 ml-2">
                                        <li><strong>X-Max-Wait-Ms: 30000</strong> — необязательно. Указывает, сколько ждать выдачи сделки (минимум 1 секунда). При превышении вернётся HTTP 504 без «зависших» запросов.</li>
                                        <li>По умолчанию система ждёт полминуты, затем возвращает ошибку ниже.</li>
                                    </ul>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm mt-2"><code>{{ formatJSON({ success: false, message: "Не удалось обработать запрос вовремя. Повторите попытку позже." }) }}</code></pre>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Параметры запроса</h4>
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra w-full">
                                            <thead>
                                            <tr>
                                                <th>Параметр</th>
                                                <th>Описание</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">external_id</code> <span class="text-error">*</span></td>
                                                <td>id сделки на стороне внешнего сервиса. Должен быть уникальным для мерчанта.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">amount</code> <span class="text-error">*</span></td>
                                                <td>сумма сделки (целое число).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payment_gateway</code></td>
                                                <td>код платежного метода. Не обязательно, если указан currency.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">currency</code></td>
                                                <td>код валюты. Не обязателен, если указан payment_gateway.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payment_detail_type</code></td>
                                                <td>тип реквизита: card, phone, mobile_commerce, account_number, nspk.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">client_id</code></td>
                                                <td>id клиента мерчанта (нужен, если включен антифрод).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">merchant_id</code> <span class="text-error">*</span></td>
                                                <td>uuid мерчанта. Можно найти на странице мерчанта в разделе настройки.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">callback_url</code></td>
                                                <td>POST-ссылка, куда придёт статус сделки.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">success_url</code></td>
                                                <td>GET-ссылка для успешной оплаты.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">fail_url</code></td>
                                                <td>GET-ссылка для неуспешной оплаты.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">manually</code></td>
                                                <td>значение "1" позволяет клиенту самому выбрать платёжный метод на странице с формой оплаты.</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <p class="text-sm text-base-content/80">Поле <code class="bg-base-200 px-1 rounded">base_amount</code> — исходная сумма сделки на момент создания. Поле <code class="bg-base-200 px-1 rounded">amount</code> может быть изменено при пересчёте.</p>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: { order_id: "4b3a163b...", external_id: "...", merchant_id: "...", base_amount: "1000", amount: "1000", currency: "rub", status: "pending", sub_status: "pending", callback_url: null, success_url: null, fail_url: null, payment_gateway: "sberbank", payment_gateway_name: "Сбербанк", finished_at: null, expires_at: 1731375451, created_at: 1731375391, payment_link: "https://example.com/payment/4b3a163b..." } }) }}</code></pre>
                                </div>
                            </div>
                        </section>

                        <section class="border border-base-200 rounded-xl p-4 space-y-3 overflow-x-auto">
                            <div class="grid gap-3">
                                <h3 class="text-xl font-semibold">Получить сделку</h3>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="badge badge-primary badge-lg">GET</span>
                                    <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/merchant/order/{order_id}</code>
                                </div>
                            </div>
                            <p class="text-sm text-base-content/80">Возвращает те же поля, что и ответ при создании.</p>
                            <p class="text-sm text-base-content/80">Альтернатива: <code class="bg-base-200 px-1 rounded">/api/merchant/order/{merchant_id}/{external_id}</code></p>
                        </section>
                    </div>
                </article>

                <article id="h2h-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Host To Host API</h2>

                        <section class="space-y-4">
                            <div class="rounded-xl border border-base-200 p-4 space-y-4">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Создать сделку</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-secondary badge-lg">POST</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/h2h/order</code>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Заголовки</h4>
                                    <ul class="list-disc list-inside space-y-1 text-sm text-base-content/80 ml-2">
                                        <li><strong>X-Max-Wait-Ms: 30000</strong> — необязательный таймаут ожидания выдачи сделки.</li>
                                        <li>По умолчанию система ждёт полминуты прежде чем вернуть ошибку ниже.</li>
                                    </ul>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm mt-2"><code>{{ formatJSON({ success: false, message: "Не удалось обработать запрос вовремя. Повторите попытку позже." }) }}</code></pre>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Описание параметров запроса</h4>
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra w-full">
                                            <thead>
                                            <tr>
                                                <th>Параметр</th>
                                                <th>Описание</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">external_id</code> <span class="text-error">*</span></td>
                                                <td>id сделки на стороне внешнего сервиса. Должен быть уникальным для мерчанта.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">amount</code> <span class="text-error">*</span></td>
                                                <td>сумма сделки (целое число).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payment_gateway</code></td>
                                                <td>код платежного метода. Не обязательно, если указан currency.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">currency</code></td>
                                                <td>код валюты. Не обязателен, если указан payment_gateway.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payment_detail_type</code></td>
                                                <td>тип реквизита: card, phone, mobile_commerce, account_number, nspk.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">client_id</code></td>
                                                <td>id клиента мерчанта (нужен, если включен антифрод).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">merchant_id</code> <span class="text-error">*</span></td>
                                                <td>uuid мерчанта. Можно найти на странице мерчанта в разделе настройки.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">callback_url</code></td>
                                                <td>POST-ссылка, на которую придёт статус.</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <p class="text-sm text-base-content/80">Поле <code class="bg-base-200 px-1 rounded">base_amount</code> — исходная сумма сделки на момент создания. Поле <code class="bg-base-200 px-1 rounded">amount</code> может быть изменено при пересчёте.</p>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: { order_id: "3db07a16...", external_id: "...", merchant_id: "3db07a16...", base_amount: "1000", amount: "1040", profit: "9.94", merchant_profit: "9.05", currency: "rub", profit_currency: "usdt", conversion_price_currency: "rub", conversion_price: "100.77", status: "pending", sub_status: "pending", callback_url: "...", payment_gateway: "sberbank", payment_gateway_name: "Сбербанк", payment_detail: { detail: "1000200030004000", detail_type: "card", initials: "Пол Атрейдес" }, merchant: { name: "...", description: "..." }, finished_at: null, expires_at: 1731375451, created_at: 1731375391, current_server_time: 1731655862 } }) }}</code></pre>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-4">
                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Получить сделку</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/h2h/order/{order_id}</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">Возвращает такой же объект, как при создании.</p>
                                <p class="text-sm text-base-content/80">Альтернатива: <code class="bg-base-200 px-1 rounded">/api/h2h/order/{merchant_id}/{external_id}</code></p>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Закрыть сделку</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/h2h/order/{order_id}/cancel</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">Досрочно закрывает сделку, если она в статусе pending и без открытых споров.</p>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-4 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Открыть спор</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-secondary badge-lg">POST</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/h2h/order/{order_id}/dispute</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">Если сделка ещё открыта, она будет закрыта перед созданием спора.</p>

                                <div>
                                    <h4 class="font-semibold mb-2">Параметры</h4>
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra w-full">
                                            <thead>
                                                <tr>
                                                    <th>Параметр</th>
                                                    <th>Описание</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><code class="bg-base-200 px-1 rounded">receipt</code> <span class="text-error">*</span></td>
                                                    <td>изображение jpeg,jpg,png,pdf в base64 до 5 МБ.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: { order_id: "3db07a16...", status: "pending", cancel_reason: null } }) }}</code></pre>
                                </div>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Получить спор</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/h2h/order/{order_id}/dispute</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">Ответ такой же, как при открытии спора.</p>
                            </div>
                        </section>
                    </div>
                </article>

                <article id="auto-withdrawals" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Авто вывод с баланса</h2>

                        <div class="grid lg:grid-cols-1 gap-6">
                            <section class="border border-base-200 rounded-xl p-4 space-y-3">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Получить доступный баланс</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/wallet/balance</code>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: { balance: "10000.00" } }) }}</code></pre>
                                </div>
                            </section>

                            <section class="border border-base-200 rounded-xl p-4 space-y-3">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Создать запрос на вывод</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-secondary badge-lg">POST</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/wallet/withdraw</code>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Параметры запроса</h4>
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra w-full">
                                            <thead>
                                                <tr>
                                                    <th>Параметр</th>
                                                    <th>Описание</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><code class="bg-base-200 px-1 rounded">amount</code> <span class="text-error">*</span></td>
                                                    <td>сумма вывода (целое число).</td>
                                                </tr>
                                                <tr>
                                                    <td><code class="bg-base-200 px-1 rounded">address</code> <span class="text-error">*</span></td>
                                                    <td>адрес, куда отправить средства.</td>
                                                </tr>
                                                <tr>
                                                    <td><code class="bg-base-200 px-1 rounded">network</code> <span class="text-error">*</span></td>
                                                    <td>USDT сеть: bsc, arb или trx.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({ success: true, data: { invoice_id: "...", tx_hash: "..." } }) }}</code></pre>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>

                <article id="payouts-api" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-2xl">Payouts API</h2>
                        <p class="text-sm text-base-content/80">
                            API для оформления выплат мерчанта трейдеру. Укажите либо платежный метод
                            (<code class="bg-base-200 px-1 rounded text-xs">payment_gateway</code>), либо валюту
                            (<code class="bg-base-200 px-1 rounded text-xs">currency</code>).
                        </p>

                        <section class="rounded-xl border border-base-200 p-4 space-y-4">
                            <div class="grid gap-3">
                                <h3 class="text-xl font-semibold">Создать выплату</h3>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="badge badge-secondary badge-lg">POST</span>
                                    <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payouts</code>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Параметры запроса</h4>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                            <tr>
                                                <th>Параметр</th>
                                                <th>Описание</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">merchant_id</code> <span class="text-error">*</span></td>
                                                <td>UUID мерчанта.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">external_id</code> <span class="text-error">*</span></td>
                                                <td>Внешний ID выплаты на стороне сервиса мерчанта (уникален для мерчанта).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">amount</code> <span class="text-error">*</span></td>
                                                <td>Сумма выплаты в валюте метода или указанной валюте.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payment_gateway</code></td>
                                                <td>Код платежного метода (<code>code</code> из <code>/api/payment-gateways</code>). Если не указан, требуется currency.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">currency</code></td>
                                                <td>Код валюты. Если не указан payment_gateway, используется для расчётов.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">payout_method_type</code> <span class="text-error">*</span></td>
                                                <td><code>sbp</code> или <code>card</code>.</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">requisites</code> <span class="text-error">*</span></td>
                                                <td>Реквизиты получателя (телефон для СБП или номер карты).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">initials</code></td>
                                                <td>ФИО получателя (одним полем).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">bank_name</code></td>
                                                <td>Банк в свободной форме (до 30 символов).</td>
                                            </tr>
                                            <tr>
                                                <td><code class="bg-base-200 px-1 rounded">callback_url</code></td>
                                                <td>Необязательное поле. Если указать, уведомления по этой выплате будут отправляться на данный URL.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-base-content/60 mt-2">
                                    Если не указать <code class="bg-base-200 px-1 rounded text-[11px]">callback_url</code> и в настройках мерчанта нет ссылки для выплат, уведомления отправляться не будут.
                                </p>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({
                                    success: true,
                                    data: {
                                        payout_id: "af8d6a20-...",
                                        external_id: "payout-100500",
                                        status: "open",
                                        payout_method_type: "sbp",
                                        bank_name: "Custom Bank",
                                        requisites: "7926...",
                                        initials: "Иванов Иван",
                                        merchant: {
                                            id: "3db0...",
                                            name: "My Shop"
                                        },
                                        payment_gateway: {
                                            id: 12,
                                            name: "Сбербанк",
                                            code: "sberbank"
                                        },
                                        receipt_url: "https://example.com/api/payouts/af8d6a20-.../receipt",
                                        amounts: {
                                            fiat: {
                                                value: "100000.00",
                                                currency: "RUB"
                                            },
                                            usdt_body: {
                                                value: "1289.54",
                                                currency: "USDT"
                                            },
                                            merchant_debit: {
                                                value: "1328.23",
                                                currency: "USDT"
                                            }
                                        },
                                        fees: {
                                            total: {
                                                value: "38.69",
                                                currency: "USDT"
                                            }
                                        },
                                        commissions: {
                                            total: 3
                                        },
                                        rate: {
                                            market: "bybit",
                                            price: "77.50",
                                            currency: "RUB",
                                            fixed_at: "2026-01-04T12:00:00+00:00"
                                        },
                                        timestamps: {
                                            created_at: "2026-01-04T12:00:00+00:00",
                                            taken_at: null,
                                            sent_at: null,
                                            completed_at: null,
                                            canceled_at: null
                                        }
                                    }
                                }) }}</code></pre>
                            </div>
                        </section>

                        <section class="grid gap-4">
                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Получить выплату</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payouts/{payout_id}</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">Возвращает те же поля, что и при создании.</p>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Отменить выплату</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payouts/{payout_id}/cancel</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">
                                    Доступно, пока заявка в статусе <code class="bg-base-200 px-1 rounded text-xs">open</code>. При отмене происходит возврат средств мерчанту.
                                </p>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-2 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Подтвердить оплату и снять холд</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-warning badge-lg text-white">PATCH</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payouts/{payout_id}/confirm-paid</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">
                                    Используйте, когда трейдер уже отправил деньги и выплата находится в статусе
                                    <code class="bg-base-200 px-1 rounded text-xs">sent</code>. Эндпоинт снимает холд и мгновенно
                                    зачисляет USDT трейдеру. Повторный вызов безопасен — если выплата уже завершена,
                                    сервер вернёт актуальное состояние без повторного зачисления.
                                </p>
                            </div>

                            <div class="border border-base-200 rounded-xl p-4 space-y-3 overflow-x-auto">
                                <div class="grid gap-3">
                                    <h3 class="text-xl font-semibold">Получить чек выплаты</h3>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="badge badge-primary badge-lg">GET</span>
                                        <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/payouts/{payout_id}/receipt</code>
                                    </div>
                                </div>
                                <p class="text-sm text-base-content/80">
                                    Возвращает JSON с метаданными файла и base64-содержимым. Декодируйте значение <code class="bg-base-200 px-1 rounded text-xs">base64</code>,
                                    чтобы получить оригинальный чек (JPEG/PNG/PDF).
                                </p>
                                <div>
                                    <h4 class="font-semibold mb-2">Ответ сервера</h4>
                                    <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({
                                        success: true,
                                        data: {
                                            payout_id: "af8d6a20-...",
                                            filename: "receipt-af8d6a20.pdf",
                                            mime_type: "application/pdf",
                                            size: 102400,
                                            base64: "JVBERi0xLjQKJ..."
                                        }
                                    }) }}</code></pre>
                                </div>
                            </div>
                        </section>

                        <section class="border border-base-200 rounded-xl p-4 space-y-3">
                            <h3 class="text-xl font-semibold">Статусы выплат</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Статус</th>
                                            <th>Описание</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">open</code></td>
                                            <td>Выплата находится в стакане и ждёт трейдера.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">taken</code></td>
                                            <td>Закреплена за трейдером.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">sent</code></td>
                                            <td>Трейдер отметил отправку средств.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">completed</code></td>
                                            <td>Холд завершён, деньги дошли до трейдера.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">canceled</code></td>
                                            <td>Отменена и средства возвращены мерчанту.</td>
                                        </tr>
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
                            Список сделок и выплат для всех ваших мерчантов. Для фильтрации по магазину используйте
                            <code class="bg-base-200 px-1 rounded text-xs">merchant_id</code>. Максимум —
                            <code class="bg-base-200 px-1 rounded text-xs">100</code> записей на страницу.
                            Сортировка по умолчанию — сначала новые.
                        </p>

                        <section class="rounded-xl border border-base-200 p-4 space-y-4">
                            <div class="grid gap-3">
                                <h3 class="text-xl font-semibold">Получить список сделок</h3>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="badge badge-primary badge-lg">GET</span>
                                    <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/statements/orders</code>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Параметры запроса</h4>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                        <tr>
                                            <th>Параметр</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">merchant_id</code></td>
                                            <td>UUID мерчанта. Если не указан — по всем магазинам.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">sort</code></td>
                                            <td>Порядок сортировки: <code class="bg-base-200 px-1 rounded text-xs">new</code> или <code class="bg-base-200 px-1 rounded text-xs">old</code>.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">per_page</code></td>
                                            <td>Количество записей на страницу (1-100).</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">page</code></td>
                                            <td>Номер страницы.</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Поля ответа</h4>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                        <tr>
                                            <th>Поле</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr><td><code class="bg-base-200 px-1 rounded">order_id</code></td><td>UUID сделки.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">external_id</code></td><td>Внешний ID сделки.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">payin.initial_amount</code></td><td>Сумма при создании.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">payin.amount</code></td><td>Текущая сумма сделки.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">payin.currency</code></td><td>Валюта сделки.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">credit.amount</code></td><td>Сумма зачисления мерчанту.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">credit.currency</code></td><td>Валюта зачисления.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.amount</code></td><td>Курс конвертации.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.market</code></td><td>Маркет курса.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.rate_fixed_at</code></td><td>Время фиксации курса (timestamp).</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">status</code></td><td>Статус сделки.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">created_at</code></td><td>Дата создания (timestamp).</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Пример ответа</h4>
                                <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({
                                    success: true,
                                    data: [
                                        {
                                            order_id: "d90f3f03-...",
                                            external_id: "order-100500",
                                            payin: {
                                                initial_amount: "1000.00",
                                                amount: "1040.00",
                                                currency: "rub"
                                            },
                                            credit: {
                                                amount: "9.05",
                                                currency: "usdt"
                                            },
                                            rate: {
                                                amount: "100.77",
                                                market: "bybit",
                                                rate_fixed_at: 1735992000
                                            },
                                            status: "pending",
                                            created_at: 1735992000
                                        }
                                    ],
                                    links: {
                                        first: "https://example.com/api/statements/orders?page=1",
                                        last: "https://example.com/api/statements/orders?page=1",
                                        prev: null,
                                        next: null
                                    },
                                    meta: {
                                        current_page: 1,
                                        from: 1,
                                        last_page: 1,
                                        path: "https://example.com/api/statements/orders",
                                        per_page: 20,
                                        to: 1,
                                        total: 1
                                    }
                                }) }}</code></pre>
                            </div>
                        </section>

                        <section class="rounded-xl border border-base-200 p-4 space-y-4">
                            <div class="grid gap-3">
                                <h3 class="text-xl font-semibold">Получить список выплат</h3>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="badge badge-primary badge-lg">GET</span>
                                    <code class="bg-base-200 px-2 py-1 rounded text-sm">/api/statements/payouts</code>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Параметры запроса</h4>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                        <tr>
                                            <th>Параметр</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">merchant_id</code></td>
                                            <td>UUID мерчанта. Если не указан — по всем магазинам.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">sort</code></td>
                                            <td>Порядок сортировки: <code class="bg-base-200 px-1 rounded text-xs">new</code> или <code class="bg-base-200 px-1 rounded text-xs">old</code>.</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">per_page</code></td>
                                            <td>Количество записей на страницу (1-100).</td>
                                        </tr>
                                        <tr>
                                            <td><code class="bg-base-200 px-1 rounded">page</code></td>
                                            <td>Номер страницы.</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Поля ответа</h4>
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra w-full">
                                        <thead>
                                        <tr>
                                            <th>Поле</th>
                                            <th>Описание</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr><td><code class="bg-base-200 px-1 rounded">uuid</code></td><td>UUID выплаты.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">external_id</code></td><td>Внешний ID выплаты.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">payout.amount</code></td><td>Сумма выплаты.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">payout.currency</code></td><td>Валюта выплаты.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">debit.amount</code></td><td>Списание с мерчанта.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">debit.currency</code></td><td>Валюта списания.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.value</code></td><td>Курс конвертации.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.market</code></td><td>Маркет курса.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">rate.rate_fixed_at</code></td><td>Время фиксации курса (timestamp).</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">status</code></td><td>Статус выплаты.</td></tr>
                                        <tr><td><code class="bg-base-200 px-1 rounded">created_at</code></td><td>Дата создания (timestamp).</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-2">Пример ответа</h4>
                                <pre class="bg-base-200 p-4 rounded-lg overflow-x-auto text-sm"><code>{{ formatJSON({
                                    success: true,
                                    data: [
                                        {
                                            uuid: "af8d6a20-...",
                                            external_id: "payout-100500",
                                            payout: {
                                                amount: "100000.00",
                                                currency: "RUB"
                                            },
                                            debit: {
                                                amount: "1328.23",
                                                currency: "USDT"
                                            },
                                            rate: {
                                                value: "77.50",
                                                market: "bybit",
                                                rate_fixed_at: 1735992000
                                            },
                                            status: "open",
                                            created_at: 1735992000
                                        }
                                    ],
                                    links: {
                                        first: "https://example.com/api/statements/payouts?page=1",
                                        last: "https://example.com/api/statements/payouts?page=1",
                                        prev: null,
                                        next: null
                                    },
                                    meta: {
                                        current_page: 1,
                                        from: 1,
                                        last_page: 1,
                                        path: "https://example.com/api/statements/payouts",
                                        per_page: 20,
                                        to: 1,
                                        total: 1
                                    }
                                }) }}</code></pre>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>

<style scoped>
pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

code {
    font-family: 'Courier New', monospace;
}

:global(html) {
    scroll-behavior: smooth;
}

:global([id]) {
    scroll-margin-top: 1rem;
}
</style>
