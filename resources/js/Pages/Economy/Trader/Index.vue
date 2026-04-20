<script setup>
import {Head, router} from '@inertiajs/vue3';
import {computed, reactive, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    months: {
        type: Array,
        required: true,
    },
    selectedMonth: {
        type: Object,
        default: null,
    },
    days: {
        type: Array,
        required: true,
    },
});

const MONTH_NAMES = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
];

const FIELDS = [
    'rate',
    'start_balance',
    'card_uah',
    'end_balance',
    'exchange_balance',
    'circles',
    'arbitrage_usd',
    'expense_uah',
];

const toNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const normalized = typeof value === 'string' ? value.replace(',', '.') : value;
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
};

const formatMoney = (value, fractionDigits = 2) => {
    if (value === null || value === undefined || !Number.isFinite(value)) {
        return '—';
    }
    return value.toLocaleString('ru-RU', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
};

const makeRowState = (dayData) => {
    const state = reactive({day: dayData.day});
    FIELDS.forEach((field) => {
        state[field] = dayData[field] ?? '';
    });
    return state;
};

const rows = ref(props.days.map(makeRowState));

watch(
    () => props.days,
    (next) => {
        rows.value = next.map(makeRowState);
    },
);

const computeRow = (row) => {
    const rate = toNumber(row.rate);
    const startBalance = toNumber(row.start_balance);
    const cardUah = toNumber(row.card_uah);
    const endBalance = toNumber(row.end_balance);
    const exchangeBalance = toNumber(row.exchange_balance);
    const arbitrageUsd = toNumber(row.arbitrage_usd);
    const expenseUah = toNumber(row.expense_uah);

    const cardUsd = (cardUah !== null && rate) ? cardUah / rate : null;
    const totalEnd = (endBalance !== null || exchangeBalance !== null || cardUsd !== null)
        ? (endBalance ?? 0) + (exchangeBalance ?? 0) + (cardUsd ?? 0)
        : null;
    const grossProfit = (totalEnd !== null && startBalance !== null)
        ? totalEnd - startBalance
        : null;
    const expenseUsd = (expenseUah !== null && rate) ? expenseUah / rate : null;
    const netUsd = (grossProfit !== null)
        ? grossProfit - (arbitrageUsd ?? 0) - (expenseUsd ?? 0)
        : null;
    const netUah = (netUsd !== null && rate) ? netUsd * rate : null;

    return {cardUsd, totalEnd, grossProfit, expenseUsd, netUsd, netUah};
};

const computedRows = computed(() => rows.value.map(computeRow));

const totals = computed(() => {
    let profit = 0, expenseUsd = 0, netUsd = 0, netUah = 0;
    let hasProfit = false, hasExpense = false, hasNet = false, hasNetUah = false;

    computedRows.value.forEach((c) => {
        if (c.grossProfit !== null) { profit += c.grossProfit; hasProfit = true; }
        if (c.expenseUsd !== null) { expenseUsd += c.expenseUsd; hasExpense = true; }
        if (c.netUsd !== null) { netUsd += c.netUsd; hasNet = true; }
        if (c.netUah !== null) { netUah += c.netUah; hasNetUah = true; }
    });

    return {
        profit: hasProfit ? profit : null,
        expenseUsd: hasExpense ? expenseUsd : null,
        netUsd: hasNet ? netUsd : null,
        netUah: hasNetUah ? netUah : null,
    };
});

const saveTimers = new Map();
const savingRows = ref(new Set());

const persistRow = (row) => {
    if (!props.selectedMonth) {
        return;
    }

    const payload = {};
    FIELDS.forEach((field) => {
        const value = row[field];
        payload[field] = value === '' ? null : value;
    });

    savingRows.value.add(row.day);
    router.patch(
        route('trader.economy.days.update', {month: props.selectedMonth.id, day: row.day}),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            only: [],
            onFinish: () => {
                savingRows.value.delete(row.day);
            },
        },
    );
};

const scheduleSave = (row) => {
    if (!props.selectedMonth) {
        return;
    }
    const existing = saveTimers.get(row.day);
    if (existing) {
        clearTimeout(existing);
    }
    const timer = setTimeout(() => {
        saveTimers.delete(row.day);
        persistRow(row);
    }, 600);
    saveTimers.set(row.day, timer);
};

const handleBlur = (row) => {
    const existing = saveTimers.get(row.day);
    if (existing) {
        clearTimeout(existing);
        saveTimers.delete(row.day);
    }
    persistRow(row);
};

const selectMonth = (monthId) => {
    if (props.selectedMonth?.id === monthId) {
        return;
    }
    router.visit(route('trader.economy.index', {month_id: monthId}), {
        preserveScroll: true,
    });
};

const createModalRef = ref(null);
const createForm = reactive({
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
});
const createProcessing = ref(false);

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current - 2; y <= current + 1; y++) {
        list.push(y);
    }
    return list;
});

const openCreateModal = () => {
    createForm.year = new Date().getFullYear();
    createForm.month = new Date().getMonth() + 1;
    createModalRef.value?.showModal();
};

const submitCreate = () => {
    createProcessing.value = true;
    router.post(route('trader.economy.store'), {...createForm}, {
        preserveScroll: true,
        onFinish: () => {
            createProcessing.value = false;
            createModalRef.value?.close();
        },
    });
};

const deleteModalRef = ref(null);
const deleteProcessing = ref(false);

const openDeleteModal = () => {
    deleteModalRef.value?.showModal();
};

const submitDelete = () => {
    if (!props.selectedMonth) {
        return;
    }
    deleteProcessing.value = true;
    router.delete(route('trader.economy.destroy', {month: props.selectedMonth.id}), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteModalRef.value?.close();
        },
    });
};

const monthLabel = (m) => `${MONTH_NAMES[m.month - 1]} ${m.year}`;

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Экономика" />

        <div class="mx-auto space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Экономика</h2>
                <button type="button" class="btn btn-primary btn-sm" @click="openCreateModal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Новый месяц
                </button>
            </div>

            <div v-if="months.length" class="flex flex-wrap items-center gap-2">
                <button
                    v-for="month in months"
                    :key="month.id"
                    type="button"
                    class="btn btn-xs"
                    :class="selectedMonth?.id === month.id ? 'btn-primary' : 'btn-outline'"
                    @click="selectMonth(month.id)"
                >
                    {{ monthLabel(month) }}
                </button>
                <button
                    v-if="selectedMonth"
                    type="button"
                    class="btn btn-xs btn-ghost text-error"
                    @click="openDeleteModal"
                    title="Удалить выбранный месяц"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    Удалить
                </button>
            </div>

            <div v-if="!selectedMonth" class="alert alert-info">
                <span>Создайте первый месяц, чтобы начать вести учёт.</span>
            </div>

            <div v-if="selectedMonth" class="space-y-3">
                <div class="overflow-x-auto rounded-lg border border-base-300">
                    <table class="table table-xs table-zebra w-full [&_th]:px-1 [&_td]:px-1 [&_th]:py-1 [&_td]:py-1">
                        <thead class="sticky top-0 bg-base-200 text-[11px]">
                            <tr>
                                <th class="text-center">День</th>
                                <th class="text-center">Курс</th>
                                <th class="text-center">Баланс на поч.</th>
                                <th class="text-center">Залиш. грн на картах</th>
                                <th class="text-center">Залиш. в $</th>
                                <th class="text-center">Баланс на кін</th>
                                <th class="text-center">Баланс на біржі</th>
                                <th class="text-center">Кін + біржа + $</th>
                                <th class="text-center">К-сть кругів</th>
                                <th class="text-center">Профіт</th>
                                <th class="text-center">Арбітраж $</th>
                                <th class="text-center">Витрати грн</th>
                                <th class="text-center">Витрати в $</th>
                                <th class="text-center">Чистий приб.</th>
                                <th class="text-center">Приб в грн</th>
                            </tr>
                        </thead>
                        <tbody class="text-[11px]">
                            <tr v-for="(row, index) in rows" :key="row.day">
                                <td class="text-center font-semibold">
                                    {{ row.day }}
                                    <span v-if="savingRows.has(row.day)" class="loading loading-spinner loading-xs text-primary/60 ml-1" />
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-20 text-right px-1"
                                        v-model="row.rate"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-24 text-right px-1"
                                        v-model="row.start_balance"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-24 text-right px-1"
                                        v-model="row.card_uah"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td class="text-right text-base-content/70">
                                    {{ formatMoney(computedRows[index].cardUsd, 2) }}
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-24 text-right px-1"
                                        v-model="row.end_balance"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-24 text-right px-1"
                                        v-model="row.exchange_balance"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td class="text-right text-base-content/70">
                                    {{ formatMoney(computedRows[index].totalEnd, 2) }}
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-16 text-right px-1"
                                        v-model="row.circles"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td
                                    class="text-right font-medium"
                                    :class="computedRows[index].grossProfit === null ? 'text-base-content/60'
                                        : computedRows[index].grossProfit >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(computedRows[index].grossProfit, 2) }}
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-20 text-right px-1"
                                        v-model="row.arbitrage_usd"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-xs input-bordered w-24 text-right px-1"
                                        v-model="row.expense_uah"
                                        @input="scheduleSave(row)"
                                        @blur="handleBlur(row)"
                                    >
                                </td>
                                <td class="text-right text-base-content/70">
                                    {{ formatMoney(computedRows[index].expenseUsd, 2) }}
                                </td>
                                <td
                                    class="text-right font-semibold"
                                    :class="computedRows[index].netUsd === null ? 'text-base-content/60'
                                        : computedRows[index].netUsd >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(computedRows[index].netUsd, 2) }}
                                </td>
                                <td
                                    class="text-right font-semibold"
                                    :class="computedRows[index].netUah === null ? 'text-base-content/60'
                                        : computedRows[index].netUah >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(computedRows[index].netUah, 0) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="text-[11px] bg-base-200 font-semibold">
                            <tr>
                                <td colspan="9" class="text-right pr-2">Заг. проф і круг.</td>
                                <td
                                    class="text-right"
                                    :class="totals.profit === null ? ''
                                        : totals.profit >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(totals.profit, 2) }}
                                </td>
                                <td></td>
                                <td></td>
                                <td
                                    class="text-right"
                                    :class="totals.expenseUsd === null ? ''
                                        : 'text-base-content'"
                                >
                                    {{ formatMoney(totals.expenseUsd, 2) }}
                                </td>
                                <td
                                    class="text-right"
                                    :class="totals.netUsd === null ? ''
                                        : totals.netUsd >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(totals.netUsd, 2) }}
                                </td>
                                <td
                                    class="text-right"
                                    :class="totals.netUah === null ? ''
                                        : totals.netUah >= 0 ? 'text-success' : 'text-error'"
                                >
                                    {{ formatMoney(totals.netUah, 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-xs text-base-content/60">
                    Изменения сохраняются автоматически. Курс задаётся на каждый день отдельно.
                </p>
            </div>
        </div>

        <dialog ref="createModalRef" class="modal">
            <div class="modal-box">
                <h3 class="font-semibold text-lg mb-4">Новый месяц</h3>
                <form @submit.prevent="submitCreate" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="form-control w-full">
                            <div class="label py-1">
                                <span class="label-text text-sm">Год</span>
                            </div>
                            <select v-model.number="createForm.year" class="select select-bordered select-sm">
                                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </label>
                        <label class="form-control w-full">
                            <div class="label py-1">
                                <span class="label-text text-sm">Месяц</span>
                            </div>
                            <select v-model.number="createForm.month" class="select select-bordered select-sm">
                                <option v-for="(name, index) in MONTH_NAMES" :key="index" :value="index + 1">
                                    {{ name }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="modal-action">
                        <button type="button" class="btn btn-sm btn-ghost" @click="createModalRef?.close()">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary" :disabled="createProcessing">
                            Создать
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <dialog ref="deleteModalRef" class="modal">
            <div class="modal-box">
                <h3 class="font-semibold text-lg mb-2">Удалить месяц?</h3>
                <p class="text-sm text-base-content/70 mb-4">
                    Все данные за
                    <strong v-if="selectedMonth">{{ monthLabel(selectedMonth) }}</strong>
                    будут удалены безвозвратно.
                </p>
                <div class="modal-action">
                    <button type="button" class="btn btn-sm btn-ghost" @click="deleteModalRef?.close()">
                        Отмена
                    </button>
                    <button type="button" class="btn btn-sm btn-error" :disabled="deleteProcessing" @click="submitDelete">
                        Удалить
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </div>
</template>
