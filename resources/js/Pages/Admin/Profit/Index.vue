<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    currencies: {
        type: Array,
        default: () => [],
    },
    defaults: {
        type: Object,
        default: () => ({}),
    },
});

const form = ref({
    logic: props.defaults.logic ?? 'in_body',
    amount_currency: props.defaults.amount_currency ?? 'rub',
    amount: props.defaults.amount ?? '',
    exchange_rate: props.defaults.exchange_rate ?? '',
    total_commission_rate: props.defaults.total_commission_rate ?? '',
    trader_commission_rate: props.defaults.trader_commission_rate ?? '',
    teamleader_commission_rate: props.defaults.teamleader_commission_rate ?? '',
    teamleader_split_from_trader_percent: props.defaults.teamleader_split_from_trader_percent ?? 0,
});

const errors = ref({});
const processing = ref(false);
const result = ref(null);
const serverError = ref(null);

const logicOptions = [
    { value: 'in_body', label: 'IN_BODY (сделки)' },
    { value: 'out_body', label: 'OUT_BODY (выплаты)' },
];

const isPayout = computed(() => form.value.logic === 'out_body');
const amountLabel = computed(() => (isPayout.value ? 'Сумма (фиат)' : 'Сумма'));
const exchangeLabel = computed(() => (isPayout.value ? 'Цена конверсии' : 'Курс'));
const merchantLabel = computed(() => {
    if (form.value.logic === 'out_body') {
        return 'Списано у мерчанта';
    }
    return 'Получит мерчант';
});
const splitFromTraderPercent = computed(() => {
    const numeric = Number(form.value.teamleader_split_from_trader_percent ?? 0);
    if (Number.isNaN(numeric)) {
        return 0;
    }

    return Math.min(Math.max(numeric, 0), 100);
});
const splitFromServicePercent = computed(() => Math.max(0, 100 - splitFromTraderPercent.value));

const getError = (field) => errors.value?.[field]?.[0] ?? null;

const formatMoney = (money) => {
    if (!money) {
        return '—';
    }

    return `${money.value} ${money.currency}`.trim();
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object' && value.value !== undefined && value.currency !== undefined) {
        return `${value.value} ${value.currency}`.trim();
    }

    return String(value);
};

const outputSections = computed(() => {
    if (!result.value) {
        return [];
    }

    const outputs = result.value.outputs ?? {};

    const rows = [
        { label: 'Тело', value: formatMoney(outputs.convertedAmount) },
        { label: 'Комиссия всего', value: formatMoney(outputs.totalFee) },
        { label: 'Комиссия сервиса', value: formatMoney(outputs.serviceFee) },
        { label: 'Комиссия трейдера', value: formatMoney(outputs.traderFee) },
        { label: 'Комиссия тимлида', value: formatMoney(outputs.teamLeaderFee) },
        ...(isPayout.value
            ? [{ label: 'К списанию у мерчанта', value: formatMoney(outputs.merchantDebit) }]
            : [{ label: 'К списанию у трейдера', value: formatMoney(outputs.traderDebit) }]),
        ...(isPayout.value ? [{ label: 'К зачислению трейдеру', value: formatMoney(outputs.traderCredit) }] : []),
        ...(!isPayout.value ? [{ label: 'К зачислению мерчанту', value: formatMoney(outputs.merchantCredit) }] : []),
    ];

    const usedServiceKeys = new Set();
    const serviceFields = result.value.service ?? {};
    const markIfExists = (keys) => {
        keys.forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(serviceFields, key)) {
                usedServiceKeys.add(key);
            }
        });
    };

    markIfExists([
        'convertedAmount',
        'totalFee',
        'serviceFee',
        'traderFee',
        'teamLeaderFee',
        'merchantCredit',
        'merchantDebit',
        'traderDebit',
        'traderCredit',
    ]);

    const extraRows = Object.entries(serviceFields)
        .filter(([key]) => !usedServiceKeys.has(key))
        .map(([key, value]) => ({
            label: key,
            value: formatValue(value),
        }));

    return [
        { title: 'Результат', rows },
        ...(extraRows.length ? [{ title: 'Все поля сервиса', rows: extraRows }] : []),
    ];
});

const submit = async () => {
    processing.value = true;
    errors.value = {};
    serverError.value = null;

    try {
        const payload = {
            ...form.value,
            teamleader_split_from_service_percent: splitFromServicePercent.value,
        };
        const response = await axios.post(route('admin.profit-calculator.calculate'), payload);
        if (response?.data?.success) {
            result.value = response.data.data ?? null;
        } else {
            serverError.value = response?.data?.message ?? 'Не удалось выполнить расчет.';
        }
    } catch (error) {
        const response = error.response;
        if (response?.status === 422) {
            errors.value = response?.data?.errors ?? {};
            serverError.value = response?.data?.message ?? null;
        } else {
            serverError.value = 'Не удалось выполнить расчет.';
        }
    } finally {
        processing.value = false;
    }
};

const resetForm = () => {
    form.value = {
        logic: props.defaults.logic ?? 'in_body',
        amount_currency: props.defaults.amount_currency ?? 'rub',
        amount: props.defaults.amount ?? '',
        exchange_rate: props.defaults.exchange_rate ?? '',
        total_commission_rate: props.defaults.total_commission_rate ?? '',
        trader_commission_rate: props.defaults.trader_commission_rate ?? '',
        teamleader_commission_rate: props.defaults.teamleader_commission_rate ?? '',
        teamleader_split_from_trader_percent: props.defaults.teamleader_split_from_trader_percent ?? 0,
    };
    errors.value = {};
    serverError.value = null;
    result.value = null;
};
</script>

<template>
    <Head title="Калькулятор прибыли" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-base-content">Калькулятор прибыли</h1>
                <p class="text-sm text-base-content/70">Проверка расчетов ProfitService</p>
            </div>
            <div class="badge badge-outline text-sm px-3 py-2">
                {{ logicOptions.find((option) => option.value === form.logic)?.label }}
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid grid-cols-1 gap-4">
                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Логика</span>
                                </div>
                                <select v-model="form.logic" class="select select-bordered select-sm w-full">
                                    <option v-for="option in logicOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Валюта</span>
                                </div>
                                <select v-model="form.amount_currency" class="select select-bordered select-sm w-full">
                                    <option v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency.toUpperCase() }}
                                    </option>
                                </select>
                                <div v-if="getError('amount_currency')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('amount_currency') }}</span>
                                </div>
                            </label>

                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">{{ amountLabel }}</span>
                                </div>
                                <input
                                    v-model="form.amount"
                                    type="number"
                                    step="0.00000001"
                                    inputmode="decimal"
                                    class="input input-bordered input-sm w-full"
                                    :class="{ 'input-error': getError('amount') }"
                                />
                                <div v-if="getError('amount')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('amount') }}</span>
                                </div>
                            </label>

                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">{{ exchangeLabel }}</span>
                                </div>
                                <input
                                    v-model="form.exchange_rate"
                                    type="number"
                                    step="0.00000001"
                                    inputmode="decimal"
                                    class="input input-bordered input-sm w-full"
                                    :class="{ 'input-error': getError('exchange_rate') }"
                                />
                                <div v-if="getError('exchange_rate')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('exchange_rate') }}</span>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Комиссия всего, %</span>
                                </div>
                                <input
                                    v-model="form.total_commission_rate"
                                    type="number"
                                    step="0.0001"
                                    inputmode="decimal"
                                    class="input input-bordered input-sm w-full"
                                    :class="{ 'input-error': getError('total_commission_rate') }"
                                />
                                <div v-if="getError('total_commission_rate')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('total_commission_rate') }}</span>
                                </div>
                            </label>
                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Комиссия трейдера, %</span>
                                </div>
                                <input
                                    v-model="form.trader_commission_rate"
                                    type="number"
                                    step="0.0001"
                                    inputmode="decimal"
                                    class="input input-bordered input-sm w-full"
                                    :class="{ 'input-error': getError('trader_commission_rate') }"
                                />
                                <div v-if="getError('trader_commission_rate')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('trader_commission_rate') }}</span>
                                </div>
                            </label>
                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Комиссия тимлида, %</span>
                                </div>
                                <input
                                    v-model="form.teamleader_commission_rate"
                                    type="number"
                                    step="0.0001"
                                    inputmode="decimal"
                                    class="input input-bordered input-sm w-full"
                                    :class="{ 'input-error': getError('teamleader_commission_rate') }"
                                />
                                <div v-if="getError('teamleader_commission_rate')" class="label">
                                    <span class="label-text-alt text-error">{{ getError('teamleader_commission_rate') }}</span>
                                </div>
                            </label>
                            <label class="form-control w-full">
                                <div class="label">
                                    <span class="label-text">Сплит тимлида: платит трейдер, %</span>
                                </div>
                                <input
                                    v-model.number="form.teamleader_split_from_trader_percent"
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="range range-primary"
                                />
                                <div class="label">
                                    <span class="label-text-alt text-base-content/60">0 — платит сервис, 100 — платит трейдер</span>
                                    <span class="label-text-alt text-base-content/60">Сервис: {{ splitFromServicePercent }}%</span>
                                </div>
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :class="{ loading: processing }"
                                :disabled="processing"
                            >
                                Рассчитать
                            </button>
                            <button type="button" class="btn btn-ghost" :disabled="processing" @click="resetForm">
                                Сбросить
                            </button>
                        </div>

                        <div v-if="serverError" class="alert alert-error shadow-sm">
                            <span class="text-sm">{{ serverError }}</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-base-content">Результаты</h2>
                        <div class="text-xs text-base-content/60">Комиссии и распределение</div>
                    </div>

                    <div v-if="!result" class="text-sm text-base-content/60">
                        Введите параметры и нажмите «Рассчитать».
                    </div>

                    <div v-else class="space-y-5">
                        <div v-for="section in outputSections" :key="section.title" class="space-y-2">
                            <div class="text-xs uppercase text-base-content/50">{{ section.title }}</div>
                            <div class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm">
                                <div v-for="row in section.rows" :key="row.label" class="flex items-center justify-between">
                                    <span class="text-base-content/70">{{ row.label }}</span>
                                    <span class="font-semibold">{{ row.value }}</span>
                                </div>
                            </div>
                            <div class="divider my-1"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
