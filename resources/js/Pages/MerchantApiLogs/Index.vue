<script setup>
import {Head, router, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import {computed, ref} from "vue";
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import DisplayID from "@/Components/DisplayID.vue";
import CallbackLogsTable from "@/Pages/MerchantApiLogs/Partials/CallbackLogsTable.vue";
import LogsNav from '@/Components/Admin/LogsNav.vue';
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import PageToolbar from "@/Components/Table/PageToolbar.vue";
import PageToolbarAction from "@/Components/Table/PageToolbarAction.vue";
const page = usePage();

const isAdminLogsPage = computed(() => route().current('admin.merchant-api-logs.*'));
const isMerchantLogsPage = computed(() => route().current('merchant.merchant-api-logs.*'));
const isScopedLogsPage = computed(() => isAdminLogsPage.value || isMerchantLogsPage.value);
const isRefreshingPage = ref(false);
const activeApiLogTab = computed(() => page.props.activeApiLogTab || 'orders');
const isCallbackLogsTab = computed(() => activeApiLogTab.value === 'callbacks');
const isPayoutLogsTab = computed(() => activeApiLogTab.value === 'payouts');
const isMerchantApiLogsTab = computed(() => !isCallbackLogsTab.value);
const entityColumnLabel = computed(() => isPayoutLogsTab.value ? 'Выплата' : 'Сделка');
const entityUuidPlaceholder = computed(() => isPayoutLogsTab.value ? 'UUID выплаты' : 'UUID сделки');
const detailColumnLabel = computed(() => isPayoutLogsTab.value ? 'Метод' : 'Реквизит');
const detailFieldLabel = computed(() => isPayoutLogsTab.value ? 'Метод выплаты:' : 'Тип реквизита:');

const refreshMerchantApiLogsPage = () => {
    if (isRefreshingPage.value) {
        return;
    }

    isRefreshingPage.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshingPage.value = false;
        },
    });
};
const logs = computed(() => page.props.logs);
const expandedRows = ref({}); // Для отслеживания развернутых строк (desktop)
const expandedCards = ref({}); // Для отслеживания развернутых карточек (mobile)

const switchApiLogTab = (tab) => {
    if (activeApiLogTab.value === tab) {
        return;
    }

    expandedRows.value = {};
    expandedCards.value = {};

    router.visit(page.url?.split('?')[0] || window.location.pathname, {
        data: {
            ...route().params,
            tab,
            page: 1,
            per_page: logs.value?.meta?.per_page ?? 10,
            filters: page.props.filters,
        },
        preserveScroll: true,
    });
};

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
        <Head title="Логи" />

        <MainTableSection
            title="Логи"
            :data="logs"
            :visit-extra-data="{ tab: activeApiLogTab }"
        >
            <template v-if="isScopedLogsPage && isMerchantApiLogsTab" #button>
                <PageToolbar>
                    <PageToolbarAction
                        icon="refresh"
                        title="Обновить страницу"
                        :loading="isRefreshingPage"
                        @click="refreshMerchantApiLogsPage"
                    />
                </PageToolbar>
            </template>

            <template #header>
                <div class="space-y-4">
                    <LogsNav
                        :current="activeApiLogTab"
                        :show-callbacks="isScopedLogsPage"
                        @switch="switchApiLogTab"
                    />

                    <FiltersPanel
                        :name="isCallbackLogsTab ? 'callback-logs' : 'merchant-api-logs'"
                        :query="{ tab: activeApiLogTab }"
                    >
                    <template v-if="isCallbackLogsTab">
                        <InputFilter
                            name="uuid"
                            placeholder="UUID сущности"
                        />
                        <InputFilter
                            name="merchant"
                            placeholder="Мерчант (имя или uuid)"
                        />
                    </template>
                    <template v-else>
                    <InputFilter
                        name="merchant"
                        placeholder="Мерчант (имя или uuid)"
                    />
                    <InputFilter
                        name="externalID"
                        placeholder="Внешний ID"
                    />
                    <InputFilter
                        name="uuid"
                        :placeholder="entityUuidPlaceholder"
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
                    </template>
                    </FiltersPanel>

                </div>
            </template>

            <template v-slot:body>
                <CallbackLogsTable v-if="isCallbackLogsTab" :logs="logs" />
                <div v-else class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col">
                                            Мерчант
                                        </th>
                                        <th scope="col">
                                            {{ entityColumnLabel }}
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
                                            {{ detailColumnLabel }}
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
                        </template>
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
                                                <CopyableOrderUid
                                                    v-if="isPayoutLogsTab ? log.payout : log.order"
                                                    :uuid="(isPayoutLogsTab ? log.payout?.uuid : log.order?.uuid) ?? ''"
                                                />
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
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="log in logs.data"
                                :key="log.id"
                            >
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
                                        <div v-if="isPayoutLogsTab ? log.payout : log.order" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">{{ entityColumnLabel }}:</span>
                                            <CopyableOrderUid :uuid="(isPayoutLogsTab ? log.payout?.uuid : log.order?.uuid) ?? ''" />
                                        </div>
                                        <div v-if="log.external_id" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">Внешний ID:</span>
                                            <DisplayID :id="log.external_id"/>
                                        </div>
                                        <div v-if="log.payment_detail_type" class="flex items-center gap-2 text-sm">
                                            <span class="text-base-content/80 truncate">{{ detailFieldLabel }}</span>
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
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
