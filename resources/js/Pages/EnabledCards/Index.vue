<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import FiltersSection from './Components/FiltersSection.vue';

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
    statistics: Object,
    filters: Object
});

// Имя для куки
const CURRENCY_COOKIE_NAME = 'selected_currency';

// Функция для получения значения из куки
const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

// Функция для установки куки
const setCookie = (name, value, days = 30) => {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = `expires=${date.toUTCString()}`;
    document.cookie = `${name}=${value};${expires};path=/;SameSite=Lax`;
};

// Получаем сохраненную валюту из куки или устанавливаем первую доступную
const getInitialCurrency = () => {
    const savedCurrency = getCookie(CURRENCY_COOKIE_NAME);

    // Проверяем, существует ли сохраненная валюта в списке доступных
    if (savedCurrency && props.statistics.availableCurrencies.some(c => c.code === savedCurrency)) {
        return savedCurrency;
    }

    // Иначе возвращаем первую валюту из списка
    return props.statistics.availableCurrencies.length > 0
        ? props.statistics.availableCurrencies[0].code
        : null;
};

// Устанавливаем начальное значение валюты
const selectedCurrency = ref(getInitialCurrency());

// Сохраняем выбранную валюту в куки при изменении
watch(selectedCurrency, (newValue) => {
    if (newValue) {
        setCookie(CURRENCY_COOKIE_NAME, newValue);
    }
});

// Находим данные о свободном лимите для выбранной валюты
const selectedCurrencyLimit = computed(() => {
    if (!selectedCurrency.value) return null;

    return props.statistics.currencyLimits.find(item => item.code === selectedCurrency.value) || {
        code: selectedCurrency.value,
        symbol: props.statistics.availableCurrencies.find(c => c.code === selectedCurrency.value)?.symbol || '',
        total_free_limit: '0.00'
    };
});

// Находим данные о потенциальном лимите для выбранной валюты
const selectedPotentialLimit = computed(() => {
    if (!selectedCurrency.value) return null;

    return props.statistics.potentialLimits.find(item => item.code === selectedCurrency.value) || {
        code: selectedCurrency.value,
        symbol: props.statistics.availableCurrencies.find(c => c.code === selectedCurrency.value)?.symbol || '',
        total_potential_limit: '0.00'
    };
});

// Находим полную информацию о выбранной валюте
const selectedCurrencyInfo = computed(() => {
    return props.statistics.availableCurrencies.find(c => c.code === selectedCurrency.value) || null;
});

// Получаем группы статистики по минимальным лимитам для выбранной валюты
const minAmountStatsByGroups = computed(() => {
    if (!selectedCurrency.value || !props.statistics.minAmountStats) return [];

    return props.statistics.minAmountStats[selectedCurrency.value] || [];
});
</script>

<template>
    <div>
        <Head title="Включенные реквизиты" />

        <div class="mx-auto space-y-6">
            <div class="grid gap-3 md:flex md:justify-between md:items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Включенные реквизиты</h2>

                <!-- Селект валют -->
                <div class="flex items-center justify-end gap-3">
                    <label for="currency-select" class="label p-0 cursor-pointer">
                        <span class="label-text">Валюта:</span>
                    </label>
                    <select
                        id="currency-select"
                        v-model="selectedCurrency"
                        class="select select-bordered select-sm w-48"
                    >
                        <option
                            v-for="currency in statistics.availableCurrencies"
                            :key="currency.code"
                            :value="currency.code"
                        >
                            {{ currency.name }} ({{ currency.symbol }})
                        </option>
                    </select>
                </div>
            </div>

            <!-- Фильтры -->
            <FiltersSection :initial-filters="filters" />

            <!-- Статистика: 4 отдельных блока -->
            <div class="grid grid-cols-1 3xl:grid-cols-4 xl:grid-cols-2 gap-6 mt-6">
                <!-- Общее количество включенных реквизитов -->
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base-content/60">Количество реквизитов</p>
                                <p class="text-xl font-bold">{{ statistics.totalPaymentDetails }}</p>
                            </div>
                            <div class="p-3 rounded-full bg-primary/10 text-primary">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Свободный лимит по выбранной валюте -->
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base-content/60">
                                    Свободный лимит ({{ selectedCurrencyInfo?.symbol || 'Не выбрано' }})
                                </p>
                                <p class="text-xl font-bold">
                                    {{ selectedCurrencyLimit?.symbol }} {{ selectedCurrencyLimit?.total_free_limit || '0.00' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-success/10 text-success">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Потенциальный лимит по выбранной валюте -->
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base-content/60">
                                    Потенциальный лимит ({{ selectedCurrencyInfo?.symbol || 'Не выбрано' }})
                                </p>
                                <p class="text-xl font-bold">
                                    {{ selectedPotentialLimit?.symbol }} {{ selectedPotentialLimit?.total_potential_limit || '0.00' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-secondary/10 text-secondary">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Баланс трейдеров -->
                <div class="card bg-base-100 shadow">
                    <div class="card-body p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-base-content/60">
                                    Баланс трейдеров ({{ statistics.tradersBalance.symbol }})
                                </p>
                                <p class="md:flex grid gap-x-4">
                                    <span class="flex items-center">
                                        <span class="text-base-content/60 text-sm mr-2">Всего:</span>
                                        <span class="font-bold">
                                            {{ statistics.tradersBalance.symbol }} {{ statistics.tradersBalance.total }}
                                        </span>
                                    </span>
                                    <span class="flex items-center">
                                        <span class="text-base-content/60 text-sm mr-2">Онлайн:</span>
                                        <span class="font-bold text-success">
                                            {{ statistics.tradersBalance.symbol }} {{ statistics.tradersBalance.online }}
                                        </span>
                                    </span>
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-warning/10 text-warning">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Таблица статистики по группам минимальных лимитов -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-base-content mb-4">
                    Статистика по минимальным лимитам ({{ selectedCurrencyInfo?.symbol || 'Не выбрано' }})
                </h3>

                <!-- Desktop/tablet view (table) -->
                <div class="hidden xl:block">
                    <div class="overflow-x-auto card bg-base-100 shadow">
                        <table class="table table-sm">
                            <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Минимальный лимит
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Количество реквизитов
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Свободный лимит
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Потенциальный лимит
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(stats, key) in minAmountStatsByGroups" :key="key">
                                    <th scope="row" class="font-medium whitespace-nowrap px-6 py-3">
                                        {{ stats.title }}
                                    </th>
                                    <td class="px-6 py-3">
                                        {{ stats.count }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ selectedCurrencyInfo?.symbol }} {{ stats.free_limit }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ selectedCurrencyInfo?.symbol }} {{ stats.potential_limit }}
                                    </td>
                                </tr>
                                <!-- Если нет данных -->
                                <tr v-if="Object.keys(minAmountStatsByGroups).length === 0" class="text-center px-6 py-3">
                                    <td colspan="4" class="text-base-content/60">
                                        Нет данных для выбранной валюты
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile view (cards list) -->
                <div class="xl:hidden space-y-3">
                    <div class="space-y-2">
                        <div
                            v-for="(stats, key) in minAmountStatsByGroups"
                            :key="key"
                            class="card bg-base-100 shadow-sm"
                        >
                            <div class="card-body p-4 pt-2 pb-3">
                                <!-- Заголовок карточки -->
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                    <div class="font-medium text-base-content">
                                        {{ stats.title }}
                                    </div>
                                </div>

                                <!-- Основная информация -->
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center justify-between">
                                        <div class="text-base-content/70 text-sm">Количество реквизитов</div>
                                        <div class="text-base-content font-medium">
                                            {{ stats.count }}
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                        <div class="text-base-content/70 text-sm">Свободный лимит</div>
                                        <div class="text-base-content font-medium">
                                            {{ selectedCurrencyInfo?.symbol }} {{ stats.free_limit }}
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-base-content/70 text-sm">Потенциальный лимит</div>
                                        <div class="text-base-content font-medium">
                                            {{ selectedCurrencyInfo?.symbol }} {{ stats.potential_limit }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Если нет данных -->
                        <div
                            v-if="Object.keys(minAmountStatsByGroups).length === 0"
                            class="card bg-base-100 shadow-sm"
                        >
                            <div class="card-body p-4">
                                <div class="text-center text-base-content/60">
                                    Нет данных для выбранной валюты
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
