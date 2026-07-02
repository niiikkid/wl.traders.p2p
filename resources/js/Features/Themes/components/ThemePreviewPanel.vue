<script setup>
import { computed } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';
import { evaluateContrast, contrastBadgeClass } from '../lib/theme-contrast.js';

const store = useThemeGeneratorStore();

const orders = [
    { id: 'A-10241', amount: '15 000 ₽', status: 'В ожидании', badge: 'badge-warning' },
    { id: 'A-10240', amount: '42 300 ₽', status: 'Оплачен', badge: 'badge-success' },
    { id: 'A-10238', amount: '8 750 ₽', status: 'Спор', badge: 'badge-error' },
    { id: 'A-10235', amount: '120 000 ₽', status: 'Завершён', badge: 'badge-info' },
];

const contrastPairs = computed(() => evaluateContrast(store.draftTokens));
</script>

<template>
    <div class="h-full overflow-y-auto bg-base-200 p-4">
        <div class="mx-auto max-w-3xl space-y-4">
            <!-- Contrast overview -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-2 p-4">
                    <h3 class="text-sm font-semibold">Контраст</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="pair in contrastPairs"
                            :key="pair.label"
                            class="badge badge-sm gap-1"
                            :class="contrastBadgeClass(pair.level)"
                        >{{ pair.label }} {{ pair.level }}</span>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <h3 class="text-sm font-semibold">Кнопки</h3>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-sm">Primary</button>
                        <button class="btn btn-secondary btn-sm">Secondary</button>
                        <button class="btn btn-accent btn-sm">Accent</button>
                        <button class="btn btn-neutral btn-sm">Neutral</button>
                        <button class="btn btn-ghost btn-sm">Ghost</button>
                        <button class="btn btn-sm" disabled>Disabled</button>
                    </div>
                </div>
            </div>

            <!-- USDT balance card -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <h3 class="text-sm font-semibold">Баланс USDT</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs opacity-60">Всего</div>
                            <div class="text-lg font-bold">12 480.50</div>
                        </div>
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs opacity-60">Заморожено</div>
                            <div class="text-lg font-bold text-warning">1 200.00</div>
                        </div>
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs opacity-60">Доступно</div>
                            <div class="text-lg font-bold text-success">11 280.50</div>
                        </div>
                    </div>
                    <progress class="progress progress-primary w-full" value="70" max="100"></progress>
                </div>
            </div>

            <!-- Alerts -->
            <div class="space-y-2">
                <div class="alert alert-info"><span>Курс обновлён: 1 USDT = 92.4 ₽</span></div>
                <div class="alert alert-success"><span>Выплата успешно отправлена трейдеру.</span></div>
                <div class="alert alert-warning"><span>Ожидание подтверждения оплаты.</span></div>
                <div class="alert alert-error"><span>Спор по заявке A-10238 открыт.</span></div>
            </div>

            <!-- Form -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <h3 class="text-sm font-semibold">Форма</h3>
                    <input type="text" placeholder="Сумма" class="input input-bordered input-sm w-full" />
                    <select class="select select-bordered select-sm w-full">
                        <option>Банковская карта</option>
                        <option>СБП</option>
                    </select>
                    <textarea class="textarea textarea-bordered textarea-sm w-full" placeholder="Комментарий"></textarea>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary" checked /> Чекбокс</label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="pv-radio" class="radio radio-sm radio-primary" checked /> Радио</label>
                        <input type="checkbox" class="toggle toggle-sm toggle-primary" checked />
                    </div>
                </div>
            </div>

            <!-- Orders table -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <h3 class="text-sm font-semibold">Заявки</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>ID</th><th>Сумма</th><th>Статус</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in orders" :key="order.id">
                                    <td class="font-mono">{{ order.id }}</td>
                                    <td>{{ order.amount }}</td>
                                    <td><span class="badge badge-sm" :class="order.badge">{{ order.status }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabs + menu -->
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3 p-4">
                    <div class="tabs tabs-box tabs-sm">
                        <button class="tab tab-active">Обзор</button>
                        <button class="tab">История</button>
                        <button class="tab">Настройки</button>
                    </div>
                    <ul class="menu menu-sm rounded-box bg-base-200">
                        <li><a class="active">Активный пункт</a></li>
                        <li><a>Обычный пункт</a></li>
                    </ul>
                    <div class="flex items-center gap-2">
                        <div class="badge badge-primary">primary</div>
                        <div class="badge badge-secondary">secondary</div>
                        <div class="badge badge-accent">accent</div>
                        <div class="badge badge-outline">outline</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
