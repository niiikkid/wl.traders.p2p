<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TraderSearchSelect from '@/Pages/Admin/TraderAnalytics/Components/TraderSearchSelect.vue';

defineOptions({layout: AuthenticatedLayout});

const props = defineProps({
    filters: {type: Object, default: () => ({})},
    periodOptions: {type: Array, default: () => []},
    currencyOptions: {type: Array, default: () => []},
    routes: {
        type: Object,
        default: () => ({
            index: 'admin.traders-analytics.index',
            update_threshold: 'admin.traders-analytics.operations-threshold.update',
            search_traders: 'admin.traders-analytics.traders.search',
        }),
    },
    amountRanges: {type: Array, default: () => []},
    summary: {type: Object, default: () => ({})},
    enabledDetailsByDay: {type: Array, default: () => []},
    topTraders: {type: Array, default: () => []},
    activeTraders: {type: Array, default: () => []},
    traderAmountRangeStats: {type: Array, default: () => []},
    individualTrader: {type: Object, default: null},
    individualByDay: {type: Array, default: () => []},
    individualSummary: {type: Object, default: () => ({})},
});

const selectedTab = ref(props.filters.tab ?? 'overview');
const selectedPeriod = ref(props.filters.period ?? 'today');
const selectedCurrency = ref(props.filters.currency ?? 'uah');
const selectedTraderId = ref(props.filters.trader_id ? String(props.filters.trader_id) : '');
const selectedAmountRanges = ref(
    (props.amountRanges ?? []).length > 0
        ? (props.amountRanges ?? []).map((range) => ({min: range.min ?? '', max: range.max ?? ''}))
        : [{min: '', max: ''}]
);

const thresholdDialog = ref(null);
const thresholdForm = useForm({
    currency: props.filters.currency ?? 'uah',
    threshold: props.summary.operations_threshold ?? '300',
});

const serializeAmountRanges = (ranges) => {
    return (ranges ?? [])
        .map((range) => {
            const min = String(range.min ?? '').trim();
            const max = String(range.max ?? '').trim();

            if (min === '') {
                return null;
            }

            return `${min}-${max}`;
        })
        .filter(Boolean)
        .join(',');
};

const applyFilters = () => {
    router.get(route(props.routes.index), {
        tab: selectedTab.value,
        period: selectedPeriod.value,
        currency: selectedCurrency.value,
        trader_id: selectedTraderId.value || null,
        amount_ranges: serializeAmountRanges(selectedAmountRanges.value),
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const switchTab = (tab) => {
    if (selectedTab.value === tab) {
        return;
    }

    selectedTab.value = tab;
    applyFilters();
};

const openThresholdDialog = () => {
    thresholdForm.currency = selectedCurrency.value;
    thresholdForm.threshold = props.summary.operations_threshold ?? '300';
    thresholdDialog.value?.showModal();
};

const updateThreshold = () => {
    thresholdForm.patch(route(props.routes.update_threshold), {
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
    if (percent >= 80) return 'badge-success';
    if (percent >= 50) return 'badge-warning';
    return 'badge-error';
});

const addAmountRange = () => {
    if (selectedAmountRanges.value.length >= 8) return;
    selectedAmountRanges.value.push({min: '', max: ''});
};

const removeAmountRange = (index) => {
    selectedAmountRanges.value.splice(index, 1);
    if (selectedAmountRanges.value.length === 0) {
        selectedAmountRanges.value.push({min: '', max: ''});
    }
};

watch(() => props.filters.tab, (tab) => {
    selectedTab.value = tab ?? 'overview';
});
watch(() => props.filters.period, (period) => {
    selectedPeriod.value = period ?? 'today';
});
watch(() => props.filters.currency, (currency) => {
    selectedCurrency.value = currency ?? 'uah';
    thresholdForm.currency = currency ?? 'uah';
});
watch(() => props.filters.trader_id, (traderId) => {
    selectedTraderId.value = traderId ? String(traderId) : '';
});
watch(() => props.summary.operations_threshold, (threshold) => {
    thresholdForm.threshold = threshold ?? '300';
});
watch(() => props.amountRanges, (ranges) => {
    selectedAmountRanges.value = (ranges ?? []).length > 0
        ? (ranges ?? []).map((range) => ({min: range.min ?? '', max: range.max ?? ''}))
        : [{min: '', max: ''}];
});
watch(selectedCurrency, (currency) => {
    thresholdForm.currency = currency;
});
</script>

<template>
    <div>
        <Head title="Аналитика трейдеров" />

        <div class="mx-auto space-y-6">
            <div class="space-y-3">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-base-content">Аналитика по трейдерам</h1>
                    <p class="text-sm text-base-content/70">
                        Период: {{ summary.date_from }} - {{ summary.date_to }} | Валюта: {{ (summary.currency || '').toUpperCase() }}
                    </p>
                </div>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <a
                                @click.prevent="switchTab('overview')"
                                href="#"
                                :class="selectedTab === 'overview' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'"
                                aria-current="page"
                            >
                                <span>Общая аналитика</span>
                            </a>
                        </li>
                        <li class="me-2">
                            <a
                                @click.prevent="switchTab('trader')"
                                href="#"
                                :class="selectedTab === 'trader' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'"
                                aria-current="page"
                            >
                                <span>По трейдеру</span>
                            </a>
                        </li>
                        <li class="me-2">
                            <a
                                @click.prevent="switchTab('tops')"
                                href="#"
                                :class="selectedTab === 'tops' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'"
                                aria-current="page"
                            >
                                <span>Топы</span>
                            </a>
                        </li>
                    </ul>

                    <div v-if="selectedTab === 'overview'" class="flex flex-wrap items-end justify-end gap-3">
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
                        <button type="button" class="btn btn-sm btn-primary" @click="applyFilters">
                            Применить фильтры
                        </button>
                    </div>
                </div>
            </div>

            <template v-if="selectedTab === 'overview'">
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
                                <li>Считаются операции за выбранный период, где сумма не меньше порога.</li>
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
                                <li>Рейтинг строится за текущую неделю (с понедельника по воскресенье).</li>
                                <li>В рейтинг попадают успешные операции.</li>
                                <li>Место в ТОПе определяется количеством успешных операций (чем больше, тем выше).</li>
                                <li>При одинаковом количестве операций выше будет трейдер с меньшим средним временем обработки.</li>
                                <li>Дополнительно показывается среднее время обработки по этим операциям.</li>
                            </ul>
                            <p class="text-xs text-base-content/70">
                                Важно: это рейтинг по количеству успешных операций, а не по обороту или прибыли.
                            </p>
                        </div>
                    </div>
                </details>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Активные трейдеры</div><div class="text-2xl font-bold">{{ summary.active_traders_count }} / {{ summary.total_traders }}</div><progress class="progress progress-primary mt-2" :value="summary.active_traders_percent" max="100"></progress><div class="text-xs text-base-content/70 mt-1">{{ summary.active_traders_percent }}%</div></div></div>
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Время активности</div><div class="text-2xl font-bold">{{ summary.total_active_human }}</div><div class="text-xs text-base-content/70 mt-2">В среднем {{ summary.avg_active_hours_per_trader }} ч на трейдера</div></div></div>
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><button type="button" class="inline-flex items-center gap-1 text-sm text-left text-base-content/60 hover:text-base-content underline-offset-2 hover:underline" @click="openThresholdDialog"><span>Операции от {{ summary.operations_threshold }}</span></button><div class="text-2xl font-bold">{{ summary.operations_over_300_count }}</div><div class="text-xs text-base-content/70 mt-2">Всего операций: {{ summary.operations_count }}</div></div></div>
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Среднее время обработки</div><div class="text-2xl font-bold">{{ summary.avg_processing_human }}</div><div class="text-xs text-base-content/70 mt-2">Обработано заявок: {{ summary.processed_operations_count }}</div></div></div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="card-title text-lg">Включенные реквизиты по дням</h2>
                            <span class="badge" :class="avgEnabledBadgeClass">Средний % за период: {{ summary.avg_enabled_percent }}%</span>
                        </div>
                        <div class="overflow-x-auto mt-3">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr><th>Дата</th><th>Включенные</th><th>Всего</th><th>% включенных</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in enabledDetailsByDay" :key="row.date">
                                        <td>{{ row.date_label }}</td><td>{{ row.enabled_count }}</td><td>{{ row.total_count }}</td>
                                        <td><div class="flex items-center gap-2"><span>{{ row.enabled_percent }}%</span><progress class="progress progress-primary w-24" :value="row.enabled_percent" max="100"></progress></div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </template>

            <template v-else-if="selectedTab === 'trader'">
                <details open class="collapse collapse-arrow bg-base-100 shadow">
                    <summary class="collapse-title text-base font-semibold">Фильтры индивидуальной аналитики</summary>
                    <div class="collapse-content space-y-4">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                            <label class="form-control">
                                <div class="label py-1"><span class="label-text text-xs">Валюта</span></div>
                                <select v-model="selectedCurrency" class="select select-bordered select-sm">
                                    <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <label class="form-control">
                                <div class="label py-1"><span class="label-text text-xs">Период</span></div>
                                <select v-model="selectedPeriod" class="select select-bordered select-sm">
                                    <option v-for="option in periodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <TraderSearchSelect v-model="selectedTraderId" :search-route="route(props.routes.search_traders)" />
                        </div>

                        <div class="space-y-2">
                            <div v-for="(range, index) in selectedAmountRanges" :key="`amount-range-trader-${index}`" class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2">
                                <div class="form-control flex flex-col">
                                    <label class="label py-1">
                                        <span class="label-text text-xs">От</span>
                                    </label>
                                    <input v-model="range.min" type="number" min="0" step="0.01" class="input input-bordered input-sm" placeholder="Минимум">
                                </div>
                                <div class="form-control flex flex-col">
                                    <label class="label py-1">
                                        <span class="label-text text-xs">До (необязательно)</span>
                                    </label>
                                    <input v-model="range.max" type="number" min="0" step="0.01" class="input input-bordered input-sm" placeholder="Максимум">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" class="btn btn-sm btn-ghost text-error" @click="removeAmountRange(index)">Удалить</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline" @click="addAmountRange">Добавить диапазон</button>
                            <button type="button" class="btn btn-sm btn-primary ml-auto" @click="applyFilters">Применить фильтры</button>
                        </div>
                    </div>
                </details>

                <div v-if="individualTrader" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Трейдер</div><div class="text-base font-semibold">{{ individualTrader.email }}</div></div></div>
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Чеков за период</div><div class="text-2xl font-bold">{{ individualSummary.operations_count ?? 0 }}</div></div></div>
                    <div class="card bg-base-100 shadow"><div class="card-body p-5"><div class="text-sm text-base-content/60">Среднее время обработки</div><div class="text-2xl font-bold">{{ individualSummary.avg_processing_human ?? '0 мин' }}</div></div></div>
                </div>

                <div v-if="individualTrader" class="card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h2 class="card-title text-lg">Статистика по дням</h2>
                        <div class="overflow-x-auto mt-3">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>Дата</th>
                                        <th class="text-right">Чеки</th>
                                        <th class="text-right">Обработано</th>
                                        <th class="text-right">Среднее время</th>
                                        <th v-for="range in amountRanges" :key="`daily-head-${range.key}`" class="text-right">{{ range.label }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in individualByDay" :key="row.date">
                                        <td>{{ row.date_label }}</td>
                                        <td class="text-right">{{ row.operations_count }}</td>
                                        <td class="text-right">{{ row.processed_operations_count }}</td>
                                        <td class="text-right">{{ row.avg_processing_human }}</td>
                                        <td v-for="range in row.ranges" :key="`${row.date}-${range.key}`" class="text-right">{{ range.count }}</td>
                                    </tr>
                                    <tr v-if="individualByDay.length === 0">
                                        <td :colspan="4 + amountRanges.length" class="text-center text-base-content/60">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="alert">
                    <span>Выберите трейдера через поиск и нажмите "Применить фильтры".</span>
                </div>
            </template>

            <template v-else>
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
            </template>
        </div>

        <dialog ref="thresholdDialog" class="modal modal-bottom sm:modal-middle" tabindex="0">
            <div class="modal-box">
                <h3 class="font-semibold text-lg">Порог операций</h3>
                <p class="text-sm opacity-70 mt-1">Изменение порога для валюты {{ (selectedCurrency || '').toUpperCase() }}</p>
                <div class="mt-4 space-y-3">
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">Порог</span></div>
                        <input v-model="thresholdForm.threshold" type="number" min="0.00000001" step="0.00000001" class="input input-bordered w-full">
                    </label>
                    <p v-if="thresholdForm.errors.threshold" class="text-sm text-error">{{ thresholdForm.errors.threshold }}</p>
                    <p v-if="thresholdForm.errors.currency" class="text-sm text-error">{{ thresholdForm.errors.currency }}</p>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button type="submit" class="btn btn-sm">Отмена</button></form>
                    <button type="button" class="btn btn-sm btn-primary" :disabled="thresholdForm.processing" @click="updateThreshold">Сохранить</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button type="submit" aria-label="Закрыть">close</button></form>
        </dialog>
    </div>
</template>
