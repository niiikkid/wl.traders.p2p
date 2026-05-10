<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    statistics: {
        type: Object,
        default: () => ({
            availableCurrencies: [],
            minAmountStats: {},
        }),
    },
});

const selectedCurrency = ref(null);

const availableCurrencies = computed(() => props.statistics?.availableCurrencies || []);
const selectedCurrencyInfo = computed(() => {
    return availableCurrencies.value.find((currency) => currency.code === selectedCurrency.value) || null;
});
const minAmountStatsByGroups = computed(() => {
    if (!selectedCurrency.value) {
        return [];
    }

    return props.statistics?.minAmountStats?.[selectedCurrency.value] || [];
});
const hasStatistics = computed(() => availableCurrencies.value.length > 0);
const selectedCurrencyCode = computed(() => selectedCurrencyInfo.value?.code?.toUpperCase?.() || '');

const formatMoney = (value) => {
    const normalized = String(value || '0.00')
        .replace(/\s/g, '')
        .replace(',', '.');
    const [rawIntegerPart, decimalPart] = normalized.split('.');
    const sign = rawIntegerPart.startsWith('-') ? '-' : '';
    const integerPart = rawIntegerPart.replace('-', '').replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');

    return decimalPart !== undefined
        ? `${sign}${integerPart},${decimalPart}`
        : `${sign}${integerPart}`;
};

const formatMinAmountTitle = (stats) => {
    if (stats.min_amount === null) {
        return stats.title;
    }

    return `От ${formatMoney(String(stats.title).replace(/^От\s+/u, ''))}`;
};

watch(availableCurrencies, (currencies) => {
    if (currencies.length === 0) {
        selectedCurrency.value = null;
        return;
    }

    if (!currencies.some((currency) => currency.code === selectedCurrency.value)) {
        selectedCurrency.value = currencies.find((currency) => currency.code === 'uah')?.code || currencies[0].code;
    }
}, { immediate: true });
</script>

<template>
    <section v-if="hasStatistics" class="card bg-base-100 shadow">
        <div class="card-body gap-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="card-title text-lg">Статистика по минимальным лимитам</h3>
                    <p class="mt-1 max-w-3xl text-sm text-base-content/60">
                        Показывает, сколько реквизитов, в данный момент, готово принимать разные суммы и какой лимит у них еще остался.
                    </p>
                </div>

                <div v-if="availableCurrencies.length > 1" role="tablist" class="tabs tabs-box shrink-0 p-0.5">
                    <button
                        v-for="currency in availableCurrencies"
                        :key="currency.code"
                        type="button"
                        role="tab"
                        class="tab h-7 px-2 text-xs"
                        :class="{ 'tab-active': selectedCurrency === currency.code }"
                        @click="selectedCurrency = currency.code"
                    >
                        {{ currency.code.toUpperCase() }}
                    </button>
                </div>

                <div v-else-if="selectedCurrencyInfo" class="badge badge-outline badge-sm">
                    {{ selectedCurrencyCode }}
                </div>
            </div>

            <div class="hidden overflow-x-auto rounded-box border border-base-300 xl:block">
                <table class="table table-sm">
                    <thead class="bg-base-300 text-xs uppercase">
                        <tr>
                            <th scope="col">Минимальный лимит</th>
                            <th scope="col">Количество реквизитов</th>
                            <th scope="col">Свободный лимит</th>
                            <th scope="col">Потенциальный лимит</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stats in minAmountStatsByGroups" :key="stats.min_amount ?? 'empty'">
                            <th scope="row" class="font-medium whitespace-nowrap">
                                {{ formatMinAmountTitle(stats) }}
                                <span v-if="stats.min_amount !== null" class="text-primary/70">{{ selectedCurrencyCode }}</span>
                            </th>
                            <td>{{ stats.count }}</td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ formatMoney(stats.free_limit) }}
                                <span class="text-primary/70">{{ selectedCurrencyCode }}</span>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ formatMoney(stats.potential_limit) }}
                                <span class="text-primary/70">{{ selectedCurrencyCode }}</span>
                            </td>
                        </tr>
                        <tr v-if="minAmountStatsByGroups.length === 0">
                            <td colspan="4" class="text-center text-base-content/60">
                                Нет данных для выбранной валюты
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 xl:hidden">
                <div
                    v-for="stats in minAmountStatsByGroups"
                    :key="stats.min_amount ?? 'empty-mobile'"
                    class="rounded-box border border-base-300 p-4"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-base-300 pb-2">
                        <div class="font-medium text-base-content">
                            {{ formatMinAmountTitle(stats) }}
                            <span v-if="stats.min_amount !== null" class="text-primary/70">{{ selectedCurrencyCode }}</span>
                        </div>
                        <div class="badge badge-ghost">{{ stats.count }}</div>
                    </div>
                    <div class="mt-3 grid gap-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-base-content/60">Свободный лимит</span>
                            <span class="whitespace-nowrap font-medium tabular-nums">
                                {{ formatMoney(stats.free_limit) }}
                                <span class="text-primary/70">{{ selectedCurrencyCode }}</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-base-content/60">Потенциальный лимит</span>
                            <span class="whitespace-nowrap font-medium tabular-nums">
                                {{ formatMoney(stats.potential_limit) }}
                                <span class="text-primary/70">{{ selectedCurrencyCode }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="minAmountStatsByGroups.length === 0" class="rounded-box border border-base-300 p-4 text-center text-sm text-base-content/60">
                    Нет данных для выбранной валюты
                </div>
            </div>
        </div>
    </section>
</template>
