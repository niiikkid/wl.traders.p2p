<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import WidgetHeader from '@/Components/MainPage/WidgetHeader.vue';
import StatCard from '@/Components/MainPage/StatCard.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import DetailTypeFilter from '@/Pages/EnabledCards/Components/DetailTypeFilter.vue';
import PaymentGatewayFilter from '@/Pages/EnabledCards/Components/PaymentGatewayFilter.vue';
import UserFilter from '@/Pages/EnabledCards/Components/UserFilter.vue';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';

const filtersBasePath = '/admin/filters';
const CURRENCY_COOKIE_NAME = 'selected_currency';
const CURRENCY_FILTER_ORDER = ['uah', 'rub', 'kzt', 'usd', 'eur'];

const loading = ref(false);
const loaded = ref(false);
const errored = ref(false);
const statistics = ref(null);

const filters = ref({
    detail_type: '',
    payment_gateway_id: '',
    user_id: '',
});

const selectedCurrency = ref(null);
const newLevelAmount = ref('');
const levelProcessing = ref(false);
const limitLevelError = ref(null);

const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

const setCookie = (name, value, days = 30) => {
    const date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/;SameSite=Lax`;
};

const resolveInitialCurrency = () => {
    const currencies = statistics.value?.availableCurrencies || [];
    const saved = getCookie(CURRENCY_COOKIE_NAME);
    if (saved && currencies.some((c) => c.code === saved)) {
        return saved;
    }
    const uah = currencies.find((c) => c.code === 'uah');
    if (uah) {
        return uah.code;
    }
    return currencies.length > 0 ? currencies[0].code : null;
};

watch(selectedCurrency, (value) => {
    if (value) {
        setCookie(CURRENCY_COOKIE_NAME, value);
    }
});

const applyStatistics = (data) => {
    statistics.value = data.statistics;
    if (!selectedCurrency.value || !(statistics.value.availableCurrencies || []).some((c) => c.code === selectedCurrency.value)) {
        selectedCurrency.value = resolveInitialCurrency();
    }
};

const load = async () => {
    if (loading.value) {
        return;
    }
    loading.value = true;
    errored.value = false;

    try {
        const { data } = await axios.get(route('admin.main.enabled-cards-stats'), { params: filters.value });
        applyStatistics(data);
        loaded.value = true;
    } catch (error) {
        errored.value = true;
    } finally {
        loading.value = false;
    }
};

watch(filters, () => {
    if (!loaded.value) {
        return;
    }
    load();
}, { deep: true });

onMounted(() => {
    load();
});

const availableCurrencies = computed(() => {
    const currencies = statistics.value?.availableCurrencies || [];

    return [...currencies].sort((a, b) => {
        const indexA = CURRENCY_FILTER_ORDER.indexOf(a.code);
        const indexB = CURRENCY_FILTER_ORDER.indexOf(b.code);
        const orderA = indexA === -1 ? CURRENCY_FILTER_ORDER.length : indexA;
        const orderB = indexB === -1 ? CURRENCY_FILTER_ORDER.length : indexB;

        if (orderA !== orderB) {
            return orderA - orderB;
        }

        return a.code.localeCompare(b.code);
    });
});

const selectedCurrencyInfo = computed(() => {
    if (!statistics.value) return null;
    return availableCurrencies.value.find((c) => c.code === selectedCurrency.value) || null;
});

const selectedCurrencyLimit = computed(() => {
    if (!statistics.value || !selectedCurrency.value) return null;
    return (statistics.value.currencyLimits || []).find((item) => item.code === selectedCurrency.value) || {
        code: selectedCurrency.value,
        symbol: selectedCurrencyInfo.value?.symbol || '',
        total_free_limit: '0.00',
    };
});

const selectedPotentialLimit = computed(() => {
    if (!statistics.value || !selectedCurrency.value) return null;
    return (statistics.value.potentialLimits || []).find((item) => item.code === selectedCurrency.value) || {
        code: selectedCurrency.value,
        symbol: selectedCurrencyInfo.value?.symbol || '',
        total_potential_limit: '0.00',
    };
});

const minAmountStatsByGroups = computed(() => {
    if (!statistics.value || !selectedCurrency.value || !statistics.value.minAmountStats) return [];
    return statistics.value.minAmountStats[selectedCurrency.value] || [];
});

const selectedCustomLimitLevels = computed(() => minAmountStatsByGroups.value.filter((group) => group.min_amount !== null));

const addLimitLevel = async () => {
    if (!selectedCurrency.value || levelProcessing.value) return;
    levelProcessing.value = true;
    limitLevelError.value = null;

    try {
        const { data } = await axios.post(route('admin.enabled-cards.limit-levels.store'), {
            currency: selectedCurrency.value,
            amount: newLevelAmount.value,
            ...filters.value,
        });
        applyStatistics(data);
        newLevelAmount.value = '';
    } catch (error) {
        const errors = error?.response?.data?.errors;
        limitLevelError.value = errors?.amount?.[0] || errors?.currency?.[0] || 'Не удалось добавить уровень.';
    } finally {
        levelProcessing.value = false;
    }
};

const removeLimitLevel = async (amount) => {
    if (!selectedCurrency.value || levelProcessing.value) return;
    levelProcessing.value = true;
    limitLevelError.value = null;

    try {
        const { data } = await axios.delete(route('admin.enabled-cards.limit-levels.destroy'), {
            data: {
                currency: selectedCurrency.value,
                amount,
                ...filters.value,
            },
        });
        applyStatistics(data);
    } catch (error) {
        limitLevelError.value = 'Не удалось удалить уровень.';
    } finally {
        levelProcessing.value = false;
    }
};

const resetFilters = () => {
    filters.value = { detail_type: '', payment_gateway_id: '', user_id: '' };
};
</script>

<template>
    <div class="min-w-0 space-y-4">
        <WidgetHeader title="Включенные реквизиты" :loading="loading" @refresh="load" />

        <div class="space-y-5">
                <div v-if="loading && !loaded" class="space-y-4">
                    <div class="skeleton h-16 w-full"></div>
                    <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                        <div v-for="n in 4" :key="n" class="skeleton h-16 w-full"></div>
                    </div>
                    <div class="skeleton h-40 w-full"></div>
                </div>

                <div v-else-if="errored" class="flex h-40 items-center justify-center text-sm text-error">
                    Не удалось загрузить статистику
                </div>

                <template v-else-if="statistics">
                    <div class="rounded-box border border-base-300/60 bg-base-100 p-3 min-w-0">
                        <div class="flex flex-wrap items-end gap-3 min-w-0">
                            <div class="form-control min-w-0 w-full gap-1 sm:w-auto sm:max-w-full">
                                <span class="text-xs font-medium text-base-content/60">Валюта</span>
                                <div
                                    v-if="availableCurrencies.length > 1"
                                    class="max-w-full overflow-x-auto overscroll-x-contain scroll-smooth [scrollbar-width:thin] [-webkit-overflow-scrolling:touch]"
                                >
                                    <div
                                        role="tablist"
                                        aria-label="Валюта"
                                        class="tabs tabs-box w-fit max-w-none gap-0.5 p-0.5"
                                    >
                                        <button
                                            v-for="currency in availableCurrencies"
                                            :key="currency.code"
                                            type="button"
                                            role="tab"
                                            class="tab h-8 min-h-8 shrink-0 whitespace-nowrap px-2.5 text-xs font-medium"
                                            :class="{ 'tab-active': selectedCurrency === currency.code }"
                                            :title="`${currency.name} (${currency.symbol})`"
                                            :aria-selected="selectedCurrency === currency.code"
                                            @click="selectedCurrency = currency.code"
                                        >
                                            <CurrencyDisplay
                                                :currency="currency.code"
                                                :show-label="true"
                                                size="sm"
                                                :icon-size="16"
                                            />
                                        </button>
                                    </div>
                                </div>
                                <span v-else-if="selectedCurrencyInfo" class="badge badge-outline badge-sm h-8 gap-2">
                                    <CurrencyDisplay
                                        :currency="selectedCurrencyInfo.code"
                                        :show-label="true"
                                        size="sm"
                                        :icon-size="16"
                                    />
                                </span>
                            </div>

                            <DetailTypeFilter v-model="filters.detail_type" :filters-base-path="filtersBasePath" />
                            <PaymentGatewayFilter v-model="filters.payment_gateway_id" :filters-base-path="filtersBasePath" />
                            <UserFilter v-model="filters.user_id" :filters-base-path="filtersBasePath" />

                            <div class="form-control shrink-0 gap-1 sm:ml-auto">
                                <span class="invisible text-xs font-medium select-none" aria-hidden="true">&nbsp;</span>
                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm h-8 min-h-8 gap-1.5"
                                    @click="resetFilters"
                                >
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Сбросить фильтры
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                        <StatCard label="Количество реквизитов" :value="statistics.totalPaymentDetails" color="primary">
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            :label="`Свободный лимит (${selectedCurrencyInfo?.symbol || '—'})`"
                            :prefix="`${selectedCurrencyLimit?.symbol || ''} `"
                            :value="selectedCurrencyLimit?.total_free_limit || '0.00'"
                            color="success"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </template>
                        </StatCard>

                        <StatCard
                            :label="`Потенциальный лимит (${selectedCurrencyInfo?.symbol || '—'})`"
                            :prefix="`${selectedPotentialLimit?.symbol || ''} `"
                            :value="selectedPotentialLimit?.total_potential_limit || '0.00'"
                            color="secondary"
                        >
                            <template #icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </template>
                        </StatCard>

                        <div class="col-span-2 lg:col-span-1">
                            <div class="rounded-box border border-base-300/60 bg-base-100 transition-colors hover:border-base-300">
                                <div class="flex items-start justify-between gap-2 px-3 py-3 sm:gap-3 sm:px-4 sm:py-3.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-medium text-base-content/55">
                                            Баланс трейдеров ({{ statistics.tradersBalance.symbol }})
                                        </p>
                                        <dl class="mt-1.5 flex flex-col gap-1 text-sm leading-tight">
                                            <div class="flex min-w-0 items-baseline gap-2">
                                                <dt class="shrink-0 text-xs text-base-content/55">Всего</dt>
                                                <dd class="truncate font-semibold tabular-nums text-base-content">
                                                    {{ statistics.tradersBalance.symbol }} {{ statistics.tradersBalance.total }}
                                                </dd>
                                            </div>
                                            <div class="flex min-w-0 items-baseline gap-2">
                                                <dt class="shrink-0 text-xs text-base-content/55">Онлайн</dt>
                                                <dd class="truncate font-semibold tabular-nums text-success">
                                                    {{ statistics.tradersBalance.symbol }} {{ statistics.tradersBalance.online }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                    <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-warning/10 text-warning sm:size-9 [&>svg]:size-4 sm:[&>svg]:size-5">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300/60 bg-base-100">
                        <div class="card-body p-4 sm:p-5 gap-4">
                            <div class="flex flex-col gap-1">
                                <h3 class="card-title text-base">Уровни минимального лимита</h3>
                                <p class="text-sm text-base-content/70">
                                    Для каждой валюты можно задать свои уровни. Группа «Не указан» всегда остаётся фиксированной.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-outline">Не указан</span>
                                <template v-for="level in selectedCustomLimitLevels" :key="level.min_amount">
                                    <div class="badge badge-primary badge-soft gap-2 py-3">
                                        <span>{{ level.title }}</span>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs btn-circle"
                                            :disabled="levelProcessing"
                                            @click="removeLimitLevel(level.min_amount)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </template>
                                <span v-if="selectedCustomLimitLevels.length === 0" class="text-sm text-base-content/60">
                                    Дополнительные уровни не добавлены
                                </span>
                            </div>

                            <form class="flex flex-col sm:flex-row sm:items-end gap-3" @submit.prevent="addLimitLevel">
                                <div class="w-full sm:max-w-sm">
                                    <label for="ec-new-level" class="label py-1">
                                        <span class="label-text font-medium">Сумма уровня (от)</span>
                                    </label>
                                    <input
                                        id="ec-new-level"
                                        v-model="newLevelAmount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="input input-bordered w-full"
                                        placeholder="Например, 1000"
                                    >
                                </div>
                                <button type="submit" class="btn btn-primary sm:w-auto" :disabled="levelProcessing || !selectedCurrency">
                                    Добавить уровень
                                </button>
                            </form>
                            <p v-if="limitLevelError" class="text-sm text-error">{{ limitLevelError }}</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-base-content mb-4">
                            Статистика по минимальным лимитам ({{ selectedCurrencyInfo?.symbol || '—' }})
                        </h3>

                        <DataTable>
                            <template #head>
                                <th scope="col" class="px-6 py-3">Минимальный лимит</th>
                                <th scope="col" class="px-6 py-3">Количество реквизитов</th>
                                <th scope="col" class="px-6 py-3">Свободный лимит</th>
                                <th scope="col" class="px-6 py-3">Потенциальный лимит</th>
                            </template>
                            <tr v-for="(stats, key) in minAmountStatsByGroups" :key="key">
                                <th scope="row" class="font-medium whitespace-nowrap px-6 py-3">{{ stats.title }}</th>
                                <td class="px-6 py-3">{{ stats.count }}</td>
                                <td class="px-6 py-3">{{ selectedCurrencyInfo?.symbol }} {{ stats.free_limit }}</td>
                                <td class="px-6 py-3">{{ selectedCurrencyInfo?.symbol }} {{ stats.potential_limit }}</td>
                            </tr>
                            <tr v-if="minAmountStatsByGroups.length === 0" class="text-center px-6 py-3">
                                <td colspan="4" class="text-base-content/60">Нет данных для выбранной валюты</td>
                            </tr>
                        </DataTable>

                        <DataCardList>
                            <DataCard v-for="(stats, key) in minAmountStatsByGroups" :key="key">
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                    <div class="font-medium text-base-content">{{ stats.title }}</div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center justify-between">
                                        <div class="text-base-content/70 text-sm">Количество реквизитов</div>
                                        <div class="text-base-content font-medium">{{ stats.count }}</div>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                        <div class="text-base-content/70 text-sm">Свободный лимит</div>
                                        <div class="text-base-content font-medium">{{ selectedCurrencyInfo?.symbol }} {{ stats.free_limit }}</div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-base-content/70 text-sm">Потенциальный лимит</div>
                                        <div class="text-base-content font-medium">{{ selectedCurrencyInfo?.symbol }} {{ stats.potential_limit }}</div>
                                    </div>
                                </div>
                            </DataCard>
                            <DataCard v-if="minAmountStatsByGroups.length === 0" body-class="p-4">
                                <div class="text-center text-base-content/60">Нет данных для выбранной валюты</div>
                            </DataCard>
                        </DataCardList>
                    </div>
                </template>
        </div>
    </div>
</template>
