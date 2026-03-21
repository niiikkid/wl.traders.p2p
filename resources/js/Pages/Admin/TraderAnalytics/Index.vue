<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({layout: AuthenticatedLayout});

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
    currencyOptions: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    enabledDetailsByDay: {
        type: Array,
        default: () => [],
    },
    topTraders: {
        type: Array,
        default: () => [],
    },
    activeTraders: {
        type: Array,
        default: () => [],
    },
});

const selectedPeriod = ref(props.filters.period ?? 'today');
const selectedCurrency = ref(props.filters.currency ?? 'uah');
const thresholdDialog = ref(null);
const thresholdForm = useForm({
    currency: props.filters.currency ?? 'uah',
    threshold: props.summary.operations_threshold ?? '300',
});

const applyFilters = () => {
    router.get(route('admin.traders-analytics.index'), {
        period: selectedPeriod.value,
        currency: selectedCurrency.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

watch(
    () => props.filters.currency,
    (currency) => {
        thresholdForm.currency = currency ?? 'uah';
    }
);

watch(
    () => props.summary.operations_threshold,
    (threshold) => {
        thresholdForm.threshold = threshold ?? '300';
    }
);

watch(selectedCurrency, (currency) => {
    thresholdForm.currency = currency;
});

const openThresholdDialog = () => {
    thresholdForm.currency = selectedCurrency.value;
    thresholdForm.threshold = props.summary.operations_threshold ?? '300';
    thresholdDialog.value?.showModal();
};

const updateThreshold = () => {
    thresholdForm.patch(route('admin.traders-analytics.operations-threshold.update'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            thresholdDialog.value?.close();
            applyFilters();
        },
    });
};

const avgEnabledBadgeClass = computed(() => {
    const percent = Number(props.summary.avg_enabled_percent ?? 0);

    if (percent >= 80) {
        return 'badge-success';
    }

    if (percent >= 50) {
        return 'badge-warning';
    }

    return 'badge-error';
});
</script>

<template>
    <div>
        <Head title="Аналитика трейдеров" />

        <div class="mx-auto space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-base-content">Аналитика по трейдерам</h1>
                    <p class="text-sm text-base-content/70">
                        Период: {{ summary.date_from }} - {{ summary.date_to }} | Валюта: {{ (summary.currency || '').toUpperCase() }}
                    </p>
                </div>

                <div class="flex items-end gap-3">
                    <label class="form-control w-44">
                        <div class="label py-1">
                            <span class="label-text text-xs">Валюта</span>
                        </div>
                        <select v-model="selectedCurrency" class="select select-bordered select-sm">
                            <option v-for="option in currencyOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="form-control w-44">
                        <div class="label py-1">
                            <span class="label-text text-xs">Период</span>
                        </div>
                        <select v-model="selectedPeriod" class="select select-bordered select-sm">
                            <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <button type="button" class="btn btn-primary btn-sm" @click="applyFilters">
                        Применить
                    </button>
                </div>
            </div>

            <details class="collapse collapse-arrow bg-base-100 shadow">
                <summary class="collapse-title text-base font-semibold">
                    Как считаются показатели и как их правильно интерпретировать
                </summary>
                <div class="collapse-content text-sm text-base-content/80 space-y-4">
                    <p>
                        Ниже кратко описано, как читать показатели на этой странице.
                        Цель — чтобы интерпретация у всех была одинаковой и без ложных ожиданий.
                    </p>

                    <div class="space-y-2">
                        <p class="font-semibold text-base-content">1) Активные трейдеры и время активности</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Показатель отражает время, когда трейдер был в рабочем онлайн-режиме.</li>
                            <li>Если трейдер работал в течение дня несколько раз, это суммируется.</li>
                            <li>Трейдер считается активным, если за период у него есть хотя бы немного зафиксированного онлайн-времени.</li>
                            <li>ТОП по активности строится по суммарному времени активности за период.</li>
                        </ul>
                        <p class="text-xs text-base-content/70">
                            Важно: если активность не была зафиксирована системой, в метрику она не попадет.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <p class="font-semibold text-base-content">2) Включенные реквизиты по дням и % включенных</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Для каждого дня показывается, сколько реквизитов было включено и сколько было всего доступно.</li>
                            <li>“Включенные” — это реквизиты, которые одновременно соответствовали условиям работы:
                                реквизит активен, трейдер онлайн и трафик у трейдера включен.</li>
                            <li>% включенных = (включенные / всего) × 100 по каждому дню.</li>
                            <li>“Средний % за период” — это среднее дневных процентов за выбранный период.</li>
                        </ul>
                        <p class="text-xs text-base-content/70">
                            Важно: подсчет идет по истории включения/выключения, поэтому показатель отражает реальную динамику по дням.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <p class="font-semibold text-base-content">3) Операции от порога</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Считаются операции за выбранный период, где сумма не меньше установленного порога.</li>
                            <li>Порог задается отдельно для каждой валюты.</li>
                            <li>Показатель “Всего операций” рядом нужен как база для сравнения доли крупных операций.</li>
                        </ul>
                        <p class="text-xs text-base-content/70">
                            Важно: это не пересчет всех сумм в одну валюту.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <p class="font-semibold text-base-content">4) Среднее время обработки заявки</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Считается время от создания заявки до ее завершения.</li>
                            <li>Среднее время = среднее арифметическое по всем заявкам, где есть время завершения.</li>
                            <li>“Обработано заявок” показывает, по какому объему данных рассчитано среднее.</li>
                        </ul>
                        <p class="text-xs text-base-content/70">
                            Важно: незавершенные заявки в эту метрику не входят.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <p class="font-semibold text-base-content">5) ТОП-10 трейдеров за неделю</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Рейтинг строится за последние 7 дней.</li>
                            <li>В рейтинг попадают успешные операции.</li>
                            <li>Место в ТОПе определяется количеством успешных операций (чем больше, тем выше).</li>
                            <li>Дополнительно показывается среднее время обработки по этим операциям.</li>
                        </ul>
                        <p class="text-xs text-base-content/70">
                            Важно: это рейтинг по количеству успешных операций, а не по обороту или прибыли.
                        </p>
                    </div>
                </div>
            </details>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <div class="text-sm text-base-content/60">Активные трейдеры</div>
                        <div class="text-2xl font-bold">{{ summary.active_traders_count }} / {{ summary.total_traders }}</div>
                        <progress class="progress progress-primary mt-2" :value="summary.active_traders_percent" max="100"></progress>
                        <div class="text-xs text-base-content/70 mt-1">{{ summary.active_traders_percent }}%</div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <div class="text-sm text-base-content/60">Время активности</div>
                        <div class="text-2xl font-bold">{{ summary.total_active_human }}</div>
                        <div class="text-xs text-base-content/70 mt-2">
                            В среднем {{ summary.avg_active_hours_per_trader }} ч на трейдера
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-sm text-left text-base-content/60 hover:text-base-content underline-offset-2 hover:underline"
                            @click="openThresholdDialog"
                        >
                            <span>Операции от {{ summary.operations_threshold }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>
                        <div class="text-2xl font-bold">{{ summary.operations_over_300_count }}</div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Всего операций: {{ summary.operations_count }}
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <div class="text-sm text-base-content/60">Среднее время обработки</div>
                        <div class="text-2xl font-bold">{{ summary.avg_processing_human }}</div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Обработано заявок: {{ summary.processed_operations_count }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="card-title text-lg">Включенные реквизиты по дням</h2>
                        <span class="badge" :class="avgEnabledBadgeClass">
                            Средний % за период: {{ summary.avg_enabled_percent }}%
                        </span>
                    </div>
                    <div class="overflow-x-auto mt-3">
                        <table class="table table-sm">
                            <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th>Дата</th>
                                    <th>Включенные</th>
                                    <th>Всего</th>
                                    <th>% включенных</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in enabledDetailsByDay" :key="row.date">
                                    <td>{{ row.date_label }}</td>
                                    <td>{{ row.enabled_count }}</td>
                                    <td>{{ row.total_count }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span>{{ row.enabled_percent }}%</span>
                                            <progress class="progress progress-primary w-24" :value="row.enabled_percent" max="100"></progress>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 2xl:grid-cols-2 gap-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h2 class="card-title text-lg">ТОП-10 трейдеров за неделю</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>#</th>
                                        <th>Трейдер</th>
                                        <th>Операции</th>
                                        <th>Среднее время</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in topTraders" :key="item.trader_id">
                                        <td>{{ item.rank }}</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span>{{ item.email }}</span>
                                                <span v-if="item.is_online" class="badge badge-success badge-xs">online</span>
                                            </div>
                                        </td>
                                        <td>{{ item.operations_count }}</td>
                                        <td>{{ item.avg_processing_human }}</td>
                                    </tr>
                                    <tr v-if="topTraders.length === 0">
                                        <td colspan="4" class="text-center text-base-content/60">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h2 class="card-title text-lg">ТОП-10 по времени активности</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>Трейдер</th>
                                        <th>Статус</th>
                                        <th>Активность</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in activeTraders" :key="item.trader_id">
                                        <td>{{ item.email }}</td>
                                        <td>
                                            <span class="badge badge-sm" :class="item.is_online ? 'badge-success' : 'badge-ghost'">
                                                {{ item.is_online ? 'Онлайн' : 'Оффлайн' }}
                                            </span>
                                        </td>
                                        <td>{{ item.active_human }}</td>
                                    </tr>
                                    <tr v-if="activeTraders.length === 0">
                                        <td colspan="3" class="text-center text-base-content/60">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <dialog ref="thresholdDialog" class="modal modal-bottom sm:modal-middle" tabindex="0">
            <div class="modal-box">
                <h3 class="font-semibold text-lg">Порог операций</h3>
                <p class="text-sm opacity-70 mt-1">
                    Изменение порога для валюты {{ (selectedCurrency || '').toUpperCase() }}
                </p>

                <div class="mt-4 space-y-3">
                    <label class="form-control w-full">
                        <div class="label py-1">
                            <span class="label-text">Порог</span>
                        </div>
                        <input
                            v-model="thresholdForm.threshold"
                            type="number"
                            min="0.00000001"
                            step="0.00000001"
                            class="input input-bordered w-full"
                        >
                    </label>
                    <p v-if="thresholdForm.errors.threshold" class="text-sm text-error">
                        {{ thresholdForm.errors.threshold }}
                    </p>
                    <p v-if="thresholdForm.errors.currency" class="text-sm text-error">
                        {{ thresholdForm.errors.currency }}
                    </p>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button type="submit" class="btn btn-sm">Отмена</button>
                    </form>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="thresholdForm.processing"
                        @click="updateThreshold"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" aria-label="Закрыть">close</button>
            </form>
        </dialog>
    </div>
</template>
