<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import {ref} from "vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import DisplayID from "@/Components/DisplayID.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal";

const modalStore = useModalStore();
const logs = usePage().props.logs;
const expandedRows = ref({}); // Для отслеживания развернутых строк (desktop)
const expandedCards = ref({}); // Для отслеживания развернутых карточек (mobile)

// Получение статистических данных из props
const failedTotal = usePage().props.failedTotal;
const failedToday = usePage().props.failedToday;
const successTotal = usePage().props.successTotal;
const successToday = usePage().props.successToday;
const sumBySuccessCurrencyToday = usePage().props.sumBySuccessCurrencyToday;
const sumByFailedCurrencyToday = usePage().props.sumByFailedCurrencyToday;
const sumBySuccessCurrencyTotal = usePage().props.sumBySuccessCurrencyTotal;
const sumByFailedCurrencyTotal = usePage().props.sumByFailedCurrencyTotal;

// Данные для удаления логов по периоду
const startDate = ref('');
const endDate = ref('');
const processing = ref(false);

// Функция для проверки, выбраны ли обе даты
const areBothDatesSelected = () => {
    return startDate.value && endDate.value;
};

// Функция для удаления логов
const deleteLogsByDateRange = () => {
    processing.value = true;
    router.post(route('admin.merchant-api-logs.delete'), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        onSuccess: () => {
            processing.value = false;
            startDate.value = '';
            endDate.value = '';
        },
        onError: () => {
            processing.value = false;
        }
    });
};

// Функция для подтверждения удаления
const confirmDelete = () => {
    if (!areBothDatesSelected()) return;

    modalStore.openConfirmModal({
        title: 'Подтверждение удаления',
        body: `Вы уверены, что хотите удалить все логи API запросов за период с ${startDate.value} по ${endDate.value}? Это действие нельзя отменить.`,
        confirm_button_name: 'Удалить',
        confirm: deleteLogsByDateRange
    });
};

// Функция для форматирования чисел
const formatNumber = (num) => {
    if (num === undefined || num === null) return '0';
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// Функция для форматирования времени выполнения в секунды
const formatExecutionTime = (timeMs) => {
    if (timeMs === undefined || timeMs === null) return '-';
    const seconds = timeMs / 1000;
    return seconds.toLocaleString('ru-RU', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }) + ' сек';
}

// Функция для переключения состояния развернутой строки (desktop)
const toggleRow = (logId) => {
    expandedRows.value[logId] = !expandedRows.value[logId];
};

// Функция для переключения состояния развернутой карточки (mobile)
const toggleExpand = (logId) => {
    expandedCards.value[logId] = !expandedCards.value[logId];
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Логи API-запросов" />

        <MainTableSection
            title="Логи API-запросов"
            :data="logs"
        >
            <template v-slot:table-filters>
                <div>
                    <FiltersPanel name="merchant-api-logs">
                        <InputFilter
                            name="merchant"
                            placeholder="Мерчант (имя или uuid)"
                        />
                        <InputFilter
                            name="externalID"
                            placeholder="Внешний ID"
                        />
                        <InputFilter
                            name="minAmount"
                            placeholder="Мин. сумма"
                        />
                        <InputFilter
                            name="maxAmount"
                            placeholder="Макс. сумма"
                        />
                        <InputFilter
                            name="currency"
                            placeholder="Валюта"
                        />
                        <InputFilter
                            name="method"
                            placeholder="Метод (код)"
                        />
                        <DropdownFilter
                            name="apiLogStatuses"
                            title="Статусы"
                        />
                    </FiltersPanel>
                </div>
            </template>

            <template v-slot:body>
                <!-- Панель статистики -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">Статистика запросов</h2>

                    <!-- Карточки статистики -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Успешные запросы сегодня -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Успешно сегодня</p>
                                    <p class="text-2xl font-bold">{{ successToday }}</p>
                                </div>
                                <div class="bg-success/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Неудачные запросы сегодня -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Ошибок сегодня</p>
                                    <p class="text-2xl font-bold">{{ failedToday }}</p>
                                </div>
                                <div class="bg-error/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Успешные запросы всего -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Успешно всего</p>
                                    <p class="text-2xl font-bold">{{ successTotal }}</p>
                                </div>
                                <div class="bg-success/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Неудачные запросы всего -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="opacity-70">Ошибок всего</p>
                                    <p class="text-2xl font-bold">{{ failedTotal }}</p>
                                </div>
                                <div class="bg-error/10 p-3 rounded-full">
                                    <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Суммы по валютам -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <!-- Суммы успешных запросов -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h3 class="text-lg font-semibold mb-3">Суммы успешных запросов</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Сегодня</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumBySuccessCurrencyToday" :key="'success-today-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumBySuccessCurrencyToday).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Всего</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumBySuccessCurrencyTotal" :key="'success-total-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumBySuccessCurrencyTotal).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Суммы неудачных запросов -->
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h3 class="text-lg font-semibold mb-3">Суммы неудачных запросов</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Сегодня</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumByFailedCurrencyToday" :key="'failed-today-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumByFailedCurrencyToday).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium opacity-70 mb-2">Всего</h4>
                                    <div class="space-y-2">
                                        <div v-for="(amount, currency) in sumByFailedCurrencyTotal" :key="'failed-total-' + currency" class="flex justify-between">
                                            <span>{{ currency.toUpperCase() }}</span>
                                            <span class="font-medium">{{ formatNumber(amount) }}</span>
                                        </div>
                                        <div v-if="Object.keys(sumByFailedCurrencyTotal).length === 0" class="opacity-70">
                                            Нет данных
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Панель управления логами -->
                    <div class="mt-6">
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                            <h4 class="text-md font-medium mb-2">Управление логами</h4>
                            <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                <div class="w-full md:flex-grow grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="form-control w-full">
                                        <div class="label"><span class="label-text">Начальная дата</span></div>
                                        <input type="date" v-model="startDate" class="input input-bordered w-full" />
                                    </label>
                                    <label class="form-control w-full">
                                        <div class="label"><span class="label-text">Конечная дата</span></div>
                                        <input type="date" v-model="endDate" class="input input-bordered w-full" />
                                    </label>
                                </div>
                                <button
                                    @click="confirmDelete"
                                    class="btn btn-error rounded-xl"
                                    :disabled="!areBothDatesSelected() || processing"
                                >
                                    <span v-if="!processing">Удалить</span>
                                    <span v-else>Удаление...</span>
                                </button>
                            </div>
                            <p class="mt-2 text-sm opacity-70">
                                Выберите период, за который нужно удалить логи. Будут удалены все логи, созданные в указанный период.
                            </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col">
                                            Мерчант
                                        </th>
                                        <th scope="col">
                                            Сделка
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Внешний ID
                                        </th>
                                        <th scope="col">
                                            Сумма
                                        </th>
<!--                                <th scope="col">
                                    Метод
                                </th>-->
                                        <th scope="col" class="text-nowrap">
                                            Реквизит
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            Время
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col">
                                            Создан
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="log in logs.data" :key="log.id">
                                        <tr
                                            class="hover cursor-pointer"
                                            @click.stop="toggleRow(log.id)"
                                        >
                                            <th scope="row" class="font-medium whitespace-nowrap">
                                                {{ log.id }}
                                            </th>
                                            <td>
                                                {{ log.merchant.name }}
                                            </td>
                                            <td>
                                                <DisplayUUID v-if="log.order" :uuid="log.order.uuid"/>
                                            </td>
                                            <td>
                                                <DisplayID v-if="log.external_id" :id="log.external_id"/>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                <div v-if="log.amount" class="text-nowrap">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else>-</div>
                                            </td>
<!--                                    <td>
                                        {{ log.payment_gateway || '-' }}
                                    </td>-->
                                            <td>
                                                {{ log.payment_detail_type || '-' }}
                                            </td>
                                            <td>
                                                <span
                                                    :class="log.execution_time
                                                        ? (log.execution_time < 1000 ? 'badge badge-success'
                                                        : log.execution_time < 3000 ? 'badge badge-warning'
                                                        : 'badge badge-error')
                                                        : 'badge'"
                                                    class="text-nowrap badge-xs"
                                                >
                                                    {{ formatExecutionTime(log.execution_time) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                    class="rounded-full flex items-center justify-center badge-xs"
                                                >
                                                    <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </span>
                                            </td>
                                            <td>
                                                <DateTime :data="log.created_at"></DateTime>
                                            </td>
                                        </tr>
                                        <!-- Развернутая информация -->
                                        <tr v-if="expandedRows[log.id]" class="bg-base-200">
                                            <td colspan="10" class="px-6 py-4">
                                                <h4 class="font-semibold mb-2">Детали</h4>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div v-if="log.request_data" class="mb-4">
                                                        <div class="opacity-70 mb-1">Данные запроса:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                                    </div>

                                                    <div v-if="log.response_data">
                                                        <div class="opacity-70 mb-1">Данные ответа:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                                    </div>
                                                </div>
                                                <div class="mt-4 grid grid-cols-2 gap-4">
                                                    <div v-if="log.user_agent">
                                                        <div class="opacity-70 mb-1">User Agent:</div>
                                                        <div class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ log.user_agent }}</div>
                                                    </div>
                                                    <div v-if="log.ip_address">
                                                        <div class="opacity-70 mb-1">IP адрес:</div>
                                                        <div class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ log.ip_address }}</div>
                                                    </div>
                                                </div>
                                                <div v-if="log.execution_time" class="mt-4">
                                                    <div class="opacity-70 mb-1">Время выполнения:</div>
                                                    <div>{{ formatExecutionTime(log.execution_time) }}</div>
                                                </div>
                                                <div v-if="log.error_message" class="mt-4">
                                                    <div class="opacity-70 mb-1">Сообщение об ошибке:</div>
                                                    <div class="text-error">{{ log.error_message }}</div>
                                                </div>
                                                <div v-if="log.exception_class" class="mt-4">
                                                    <div class="opacity-70 mb-1">Класс исключения:</div>
                                                    <div class="text-error">{{ log.exception_class }}</div>
                                                </div>
                                                <div v-if="log.exception_message" class="mt-4">
                                                    <div class="opacity-70 mb-1">Сообщение исключения:</div>
                                                    <div class="text-error">{{ log.exception_message }}</div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="log in logs.data"
                                :key="log.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Компактная шапка: ID и дата -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content">{{ log.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="log.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0 inline-flex items-center gap-5">
                                            <div class="w-30">
                                                <div class="text-xs text-base-content/70 mb-1">Мерчант</div>
                                                <div class="text-sm text-base-content truncate">{{ log.merchant.name }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-base-content/70 mb-1">Сумма</div>
                                                <div v-if="log.amount" class="text-sm text-nowrap text-base-content">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else class="text-sm text-base-content/60">-</div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span
                                                :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                class="rounded-full flex items-center justify-center badge-xs"
                                            >
                                                <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </span>
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(log.id)"
                                                :aria-expanded="!!expandedCards[log.id]"
                                                :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs text-base-content/70 mb-1">Мерчант</div>
                                                <div class="text-sm text-base-content truncate">{{ log.merchant.name }}</div>
                                            </div>
                                            <div>
                                                <span
                                                    :class="log.is_successful ? 'badge badge-success' : 'badge badge-error'"
                                                    class="rounded-full flex items-center justify-center badge-xs"
                                                >
                                                    <svg v-if="log.is_successful" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-xs text-base-content/70 mb-1">Сумма</div>
                                                <div v-if="log.amount" class="text-sm text-nowrap text-base-content">
                                                    {{ log.amount }} {{ log.currency?.toUpperCase() }}
                                                </div>
                                                <div v-else class="text-sm text-base-content/60">-</div>
                                            </div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(log.id)"
                                                    :aria-expanded="!!expandedCards[log.id]"
                                                    :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[log.id]" class="mt-3 space-y-2 bg-base-300/50 rounded-box p-2">
                                        <div v-if="log.order" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Сделка:</span>
                                            <DisplayUUID :uuid="log.order.uuid"/>
                                        </div>
                                        <div v-if="log.external_id" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Внешний ID:</span>
                                            <DisplayID :id="log.external_id"/>
                                        </div>
                                        <div v-if="log.payment_detail_type" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Тип реквизита:</span>
                                            <span class="text-base-content/60">{{ log.payment_detail_type }}</span>
                                        </div>
                                        <div v-if="log.execution_time" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Время выполнения:</span>
                                            <span
                                                :class="log.execution_time
                                                    ? (log.execution_time < 1000 ? 'text-success'
                                                    : log.execution_time < 3000 ? 'text-warning'
                                                    : 'text-error')
                                                    : ''"
                                                class="text-sm font-medium"
                                            >
                                                {{ formatExecutionTime(log.execution_time) }}
                                            </span>
                                        </div>
                                        <div v-if="log.user_agent" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">User Agent:</div>
                                            <div class="bg-base-100 p-2 rounded overflow-auto max-h-32 text-xs break-words">{{ log.user_agent }}</div>
                                        </div>
                                        <div v-if="log.ip_address" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">IP адрес:</div>
                                            <div class="bg-base-100 p-2 rounded text-xs">{{ log.ip_address }}</div>
                                        </div>
                                        <div v-if="log.request_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные запроса:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                        </div>
                                        <div v-if="log.response_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные ответа:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                        </div>
                                        <div v-if="log.error_message" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Сообщение об ошибке:</div>
                                            <div class="text-error text-sm break-words">{{ log.error_message }}</div>
                                        </div>
                                        <div v-if="log.exception_class" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Класс исключения:</div>
                                            <div class="text-error text-sm break-words">{{ log.exception_class }}</div>
                                        </div>
                                        <div v-if="log.exception_message" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Сообщение исключения:</div>
                                            <div class="text-error text-sm break-words">{{ log.exception_message }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal />
    </div>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
