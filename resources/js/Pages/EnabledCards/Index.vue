<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed, watch } from 'vue';
import FiltersSection from './Components/FiltersSection.vue';

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
    statistics: Object,
    filters: Object
});

const page = usePage();
const routePrefix = computed(() => route().current('support.*') ? 'support' : 'admin');
const filtersBasePath = computed(() => routePrefix.value === 'support' ? '/support/filters' : '/admin/filters');

/** Список реквизитов есть только в админке; support приходит к этой странице отдельным пунктом меню. */
const showPaymentDetailsLink = computed(() => route().current('admin.enabled-cards.*'));

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

    // Если сохраненной валюты нет — по умолчанию выбираем гривну
    const defaultCurrency = props.statistics.availableCurrencies.find(c => c.code === 'uah');
    if (defaultCurrency) {
        return defaultCurrency.code;
    }

    // Фоллбек: первая валюта из списка
    return props.statistics.availableCurrencies.length > 0 ? props.statistics.availableCurrencies[0].code : null;
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

const selectedCustomLimitLevels = computed(() => {
    return minAmountStatsByGroups.value.filter(group => group.min_amount !== null);
});

const limitLevelError = computed(() => {
    return page.props.errors?.limit_level || page.props.errors?.amount || page.props.errors?.currency || null;
});

const addLevelForm = useForm({
    currency: selectedCurrency.value || '',
    amount: '',
});

watch(selectedCurrency, (newCurrency) => {
    addLevelForm.currency = newCurrency || '';
});

const addLimitLevel = () => {
    if (!selectedCurrency.value) return;

    addLevelForm.currency = selectedCurrency.value;
    addLevelForm.post(route(`${routePrefix.value}.enabled-cards.limit-levels.store`), {
        preserveState: true,
        preserveScroll: true,
        only: ['statistics', 'filters', 'errors'],
        onSuccess: () => {
            addLevelForm.reset('amount');
        },
    });
};

const removeLimitLevel = (amount) => {
    if (!selectedCurrency.value) return;

    router.delete(route(`${routePrefix.value}.enabled-cards.limit-levels.destroy`), {
        data: {
            currency: selectedCurrency.value,
            amount,
        },
        preserveState: true,
        preserveScroll: true,
        only: ['statistics', 'filters', 'errors'],
    });
};
</script>

<template>
    <div>
        <Head title="Включенные реквизиты" />

        <div class="mx-auto space-y-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content shrink-0">Включенные реквизиты</h2>

                <div class="flex w-full flex-wrap items-center justify-end gap-3 md:w-auto md:min-w-0 md:shrink">
                    <select
                        id="currency-select"
                        v-model="selectedCurrency"
                        aria-label="Валюта"
                        class="select select-bordered select-sm w-full min-w-[12rem] sm:w-48 max-w-full"
                    >
                        <option
                            v-for="currency in statistics.availableCurrencies"
                            :key="currency.code"
                            :value="currency.code"
                        >
                            {{ currency.name }} ({{ currency.symbol }})
                        </option>
                    </select>
                    <button
                        v-if="showPaymentDetailsLink"
                        type="button"
                        class="btn btn-outline btn-sm shrink-0"
                        @click="router.visit(route('admin.payment-details.index'), { preserveScroll: true })"
                    >
                        Реквизиты
                    </button>
                </div>
            </div>

            <!-- Фильтры -->
            <FiltersSection :initial-filters="filters" :filters-base-path="filtersBasePath" />

            <details class="collapse collapse-arrow bg-base-100 shadow">
                <summary class="collapse-title flex items-center gap-3 text-base font-semibold">
                    <span class="badge badge-primary badge-sm">?</span>
                    Как это работает?
                </summary>
                <div class="collapse-content text-sm text-base-content/80 space-y-3">
                    <p>
                        Страница показывает текущую пропускную способность реквизитов по выбранной валюте и заданным фильтрам.
                        Учитываются только активные, неархивные реквизиты онлайн-трейдеров.
                    </p>
                    <div class="space-y-1">
                        <p class="font-medium text-base-content">Что означают показатели:</p>
                        <p>
                            <span class="font-medium">Свободный лимит</span> — это сумма, которую реквизиты этой группы
                            могут принять прямо сейчас до упора в дневной лимит.
                        </p>
                        <p>
                            <span class="font-medium">Потенциальный лимит</span> — это свободный лимит плюс сумма сделок,
                            которые сейчас находятся в ожидании подтверждения.
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="font-medium text-base-content">Как считаются уровни:</p>
                        <p>
                            Группа <span class="font-medium">«Не указан»</span> включает реквизиты, у которых минимальный лимит не задан.
                        </p>
                        <p>
                            Каждый уровень работает как верхний порог. Например, уровень <span class="font-medium">«От 5 000»</span>
                            учитывает реквизиты с минимальным лимитом до 5 000 включительно.
                        </p>
                        <p>
                            Поэтому уровни нарастающие и могут пересекаться: если у реквизита минимальный лимит 2 000,
                            он попадет и в уровень «От 2 000», и в «От 5 000», и в более высокие уровни.
                        </p>
                    </div>
                    <p>
                        Для каждого уровня отдельно считаются: количество реквизитов, свободный лимит и потенциальный лимит.
                    </p>
                </div>
            </details>

            <div class="card bg-base-100 shadow">
                <div class="card-body p-4 sm:p-5 gap-4">
                    <div class="flex flex-col gap-1">
                        <h3 class="card-title text-lg">Уровни минимального лимита</h3>
                        <p class="text-sm text-base-content/70">
                            Для каждой валюты можно задать свои уровни. Группа "Не указан" всегда остается фиксированной.
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
                            <label for="new-limit-level-input" class="label py-1">
                                <span class="label-text font-medium">Сумма уровня (от)</span>
                            </label>
                            <input
                                id="new-limit-level-input"
                                v-model="addLevelForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="input input-bordered w-full"
                                placeholder="Например, 1000"
                            >
                        </div>
                        <button
                            type="submit"
                            class="btn btn-primary sm:w-auto"
                            :disabled="addLevelForm.processing || !selectedCurrency"
                        >
                            Добавить уровень
                        </button>
                    </form>
                    <p v-if="limitLevelError" class="text-sm text-error">
                        {{ limitLevelError }}
                    </p>
                </div>
            </div>

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
