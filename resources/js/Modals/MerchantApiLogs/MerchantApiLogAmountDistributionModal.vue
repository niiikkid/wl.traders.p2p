<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    amountDistributionRoute: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const loadError = ref('');
const payload = ref(null);
const selectedPeriod = ref('current_month');
const selectedCurrency = ref('uah');

const periodOptions = computed(() => payload.value?.period_options ?? []);
const currencyOptions = computed(() => payload.value?.currency_options ?? []);
const distribution = computed(() => payload.value?.distribution ?? { buckets: [], total_successful: 0, total_all: 0 });
const currencySymbol = computed(() => payload.value?.currency_symbol ?? '');

const distributionIndicatorColor = (count) => (
    Number(count) > 0 ? 'hsl(142, 71%, 40%)' : 'hsl(0, 0%, 72%)'
);

const formatInteger = (value) => Number(value ?? 0).toLocaleString('ru-RU');

const close = () => {
    emit('close');
};

const loadStatistics = async () => {
    if (!props.amountDistributionRoute) {
        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(props.amountDistributionRoute, {
            params: {
                period: selectedPeriod.value,
                currency: selectedCurrency.value,
            },
        });

        payload.value = response.data;
        selectedPeriod.value = response.data?.period ?? selectedPeriod.value;
        selectedCurrency.value = response.data?.currency ?? selectedCurrency.value;
    } catch (error) {
        loadError.value = error.response?.data?.message ?? 'Не удалось загрузить статистику.';
        payload.value = null;
    } finally {
        loading.value = false;
    }
};

const selectPeriod = (period) => {
    if (selectedPeriod.value === period || loading.value) {
        return;
    }

    selectedPeriod.value = period;
    loadStatistics();
};

const selectCurrency = (currency) => {
    if (selectedCurrency.value === currency || loading.value) {
        return;
    }

    selectedCurrency.value = currency;
    loadStatistics();
};

const resetState = () => {
    loading.value = false;
    loadError.value = '';
    payload.value = null;
    selectedPeriod.value = 'current_month';
    selectedCurrency.value = 'uah';
};

watch(
    () => props.show,
    async (showed) => {
        if (showed) {
            resetState();
            await loadStatistics();
        } else {
            resetState();
        }
    },
);
</script>

<template>
    <Modal
        :show="show"
        max-width="5xl"
        @close="close"
    >
        <ModalHeader
            title="Распределение запросов по сумме"
            @close="close"
        />
        <ModalBody>
            <div
                v-if="!amountDistributionRoute"
                class="py-8 text-center text-sm text-base-content/60"
            >
                Статистика недоступна.
            </div>

            <div
                v-else
                class="space-y-4"
                :class="{ 'pointer-events-none opacity-60': loading && payload }"
            >
                <div class="card bg-base-200/40 border border-base-300">
                    <div class="card-body gap-3 p-4">
                        <p class="text-xs text-base-content/60">
                            Валюта
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="currency in currencyOptions"
                                :key="currency"
                                type="button"
                                class="btn btn-xs"
                                :class="selectedCurrency === currency ? 'btn-primary' : 'btn-outline btn-primary'"
                                :disabled="loading"
                                @click="selectCurrency(currency)"
                            >
                                {{ currency.toUpperCase() }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="periodOptions.length"
                    class="card bg-base-200/40 border border-base-300"
                >
                    <div class="card-body gap-3 p-4">
                        <p class="text-xs text-base-content/60">
                            Период API-запросов на создание сделки
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in periodOptions"
                                :key="option.value"
                                type="button"
                                class="btn btn-xs"
                                :class="selectedPeriod === option.value ? 'btn-primary' : 'btn-outline btn-primary'"
                                :disabled="loading"
                                @click="selectPeriod(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="loading && !payload"
                    class="flex justify-center py-12"
                >
                    <span class="loading loading-spinner loading-md" />
                </div>

                <p
                    v-else-if="loadError"
                    class="py-8 text-center text-sm text-error"
                >
                    {{ loadError }}
                </p>

                <template v-else-if="payload">
                    <div class="card bg-base-200/40 border border-base-300">
                        <div class="card-body gap-4 p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">
                                За выбранный период
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <p class="text-xs text-base-content/50">
                                        Сумма успешных запросов
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                                        {{ payload.successful_total_amount }}
                                        <span class="text-lg text-primary">{{ currencySymbol }}</span>
                                    </p>
                                </div>
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <p class="text-xs text-base-content/50">
                                        Сумма всех запросов
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                                        {{ payload.all_total_amount }}
                                        <span class="text-lg text-base-content/70">{{ currencySymbol }}</span>
                                    </p>
                                </div>
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <p class="text-xs text-base-content/50">
                                        Успешных запросов
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold tabular-nums text-success">
                                        {{ formatInteger(payload.successful_requests_count) }}
                                    </p>
                                </div>
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <p class="text-xs text-base-content/50">
                                        Всего запросов
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                                        {{ formatInteger(payload.all_requests_count) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-200/40 border border-base-300">
                        <div class="card-body gap-3 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-medium text-base-content/80">
                                    Распределение по сумме
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-success badge-outline badge-sm">
                                        Успешных: {{ formatInteger(distribution.total_successful) }}
                                    </span>
                                    <span class="badge badge-outline badge-sm">
                                        Всего: {{ formatInteger(distribution.total_all) }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="(distribution.buckets ?? []).length > 0"
                                class="overflow-x-auto rounded-box border border-base-300 bg-base-100"
                            >
                                <table class="table table-xs table-zebra text-[11px]">
                                    <thead>
                                        <tr class="text-[10px] uppercase text-base-content/60">
                                            <th class="py-2" rowspan="2">
                                                Диапазон
                                            </th>
                                            <th class="py-1 text-center" colspan="2">
                                                Успешные
                                            </th>
                                            <th class="py-1 text-center" colspan="2">
                                                Все
                                            </th>
                                        </tr>
                                        <tr class="text-[10px] uppercase text-base-content/60">
                                            <th class="py-2 text-right">
                                                Запросов
                                            </th>
                                            <th class="py-2 text-right">
                                                %
                                            </th>
                                            <th class="py-2 text-right">
                                                Запросов
                                            </th>
                                            <th class="py-2 text-right">
                                                %
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="bucket in distribution.buckets"
                                            :key="bucket.key"
                                            :class="bucket.all_count > 0 || bucket.successful_count > 0 ? '' : 'text-base-content/50'"
                                        >
                                            <td class="py-1.5">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <span
                                                        class="size-2 shrink-0 rounded-full ring-2 ring-base-100"
                                                        :style="{ backgroundColor: distributionIndicatorColor(bucket.successful_count || bucket.all_count) }"
                                                    />
                                                    <span class="truncate leading-tight">
                                                        {{ bucket.label }}
                                                    </span>
                                                </span>
                                            </td>
                                            <td class="py-1.5 text-right tabular-nums text-success">
                                                {{ formatInteger(bucket.successful_count) }}
                                            </td>
                                            <td class="py-1.5 text-right tabular-nums text-success">
                                                {{ bucket.successful_percent }}%
                                            </td>
                                            <td class="py-1.5 text-right tabular-nums">
                                                {{ formatInteger(bucket.all_count) }}
                                            </td>
                                            <td class="py-1.5 text-right tabular-nums">
                                                {{ bucket.all_percent }}%
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p
                                v-else
                                class="py-8 text-center text-xs text-base-content/60"
                            >
                                Нет данных для распределения.
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </ModalBody>
    </Modal>
</template>
