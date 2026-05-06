<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import Modal from "@/Components/Modals/Modal.vue";
import {computed, onMounted, ref} from "vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const modalStore = useModalStore();
const viewStore = useViewStore();
const page = usePage();
/** Реактивные пропсы страницы — после POST/редиректа таблица обновляется (как Order/Index, PaymentDetail). */
const smsLogs = computed(() => page.props.smsLogs);
const smsLogsTotalCount = computed(() => page.props.smsLogsTotalCount);
const senderStopList = computed(() => page.props.senderStopList);
const smsStopWords = computed(() => page.props.smsStopWords);
const paymentGateways = computed(() => page.props.paymentGateways ?? []);
const recentPaymentGateways = computed(() => page.props.recentPaymentGateways ?? []);
const expandedCards = ref({});
const currentTab = ref('logs');
const newStopWord = ref('');
const tableFiltersStore = useTableFiltersStore();
const isAddSenderModalOpen = ref(false);
const selectedSmsLog = ref(null);
const selectedPaymentGatewayId = ref(null);
const gatewaySearch = ref('');
const pendingConfirmGatewayId = ref(null);
const addSenderToGatewayForm = useForm({
    payment_gateway_id: null,
});

const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const confirmAddSenderToStopLost = (smsLog) => {

    modalStore.openConfirmModal({
        title: `Добавить отправителя ${smsLog.sender} в стоп лист?`,
        body: `Все сообщения отправителя ${smsLog.sender} будут удалены, а новые сообщения будут игнорироваться.`,
        confirm_button_name: 'Подтвердить',
        confirm: () => {
            useForm({}).post(route('admin.sender-stop-list.store', smsLog.id), {
                preserveScroll: true,
                onFinish: () => {
                    router.visit(route('admin.sms-logs.index'))
                },
            });
        }
    });
};

const openAddSenderModal = (smsLog) => {
    selectedSmsLog.value = smsLog;
    selectedPaymentGatewayId.value = null;
    gatewaySearch.value = '';
    pendingConfirmGatewayId.value = null;
    addSenderToGatewayForm.reset();
    addSenderToGatewayForm.clearErrors();
    isAddSenderModalOpen.value = true;
};

const closeAddSenderModal = () => {
    isAddSenderModalOpen.value = false;
    selectedSmsLog.value = null;
    selectedPaymentGatewayId.value = null;
    gatewaySearch.value = '';
    pendingConfirmGatewayId.value = null;
    addSenderToGatewayForm.reset();
    addSenderToGatewayForm.clearErrors();
};

const filteredPaymentGateways = computed(() => {
    const query = gatewaySearch.value.trim().toLowerCase();
    if (!query) {
        return recentPaymentGateways.value;
    }

    return paymentGateways.value.filter((paymentGateway) => {
        return paymentGateway.name.toLowerCase().includes(query);
    });
});

const selectedPaymentGateway = computed(() => {
    if (!selectedPaymentGatewayId.value) {
        return null;
    }

    return paymentGateways.value.find((paymentGateway) => paymentGateway.id === selectedPaymentGatewayId.value) ?? null;
});

/** Шаг подтверждения только после «Добавить», не при пустом выборе (null === null давало ложное совпадение). */
const isAddSenderConfirmStep = computed(() => {
    return (
        selectedPaymentGatewayId.value != null
        && pendingConfirmGatewayId.value != null
        && pendingConfirmGatewayId.value === selectedPaymentGatewayId.value
    );
});

const requestSenderAddingConfirmation = () => {
    if (!selectedPaymentGatewayId.value) {
        return;
    }

    pendingConfirmGatewayId.value = selectedPaymentGatewayId.value;
};

const addSenderToPaymentGateway = () => {
    if (!selectedSmsLog.value || !selectedPaymentGatewayId.value) {
        return;
    }

    addSenderToGatewayForm.payment_gateway_id = selectedPaymentGatewayId.value;
    addSenderToGatewayForm.post(route('admin.sender-payment-gateway.store', selectedSmsLog.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddSenderModal();
            router.reload({
                preserveScroll: true,
                only: ['smsLogs', 'smsLogsTotalCount', 'filters', 'paymentGateways', 'recentPaymentGateways'],
            });
        },
    });
};

const openPage = (tab) => {
    tableFiltersStore.setTab(tab);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route(route().current()), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    })
}

const deleteSenderFromStopList = (senderStopList) => {
    useForm({}).delete(route('admin.sender-stop-list.destroy', senderStopList.id), {
        preserveScroll: true,
        onFinish: () => {
            router.visit(route('admin.sms-logs.index'), {
                data: tableFiltersStore.getQueryData,
            })
        },
    });
}

const deleteSmsStopWord = (smsStopWord) => {
    useForm({}).delete(route('admin.sms-stop-word.destroy', smsStopWord.id), {
        preserveScroll: true,
        onFinish: () => {
            router.visit(route('admin.sms-logs.index'), {
                data: tableFiltersStore.getQueryData,
            })
        },
    });
}

const addSmsStopWord = () => {
    if (!newStopWord.value.trim()) return;

    useForm({
        word: newStopWord.value.trim()
    }).post(route('admin.sms-stop-word.store'), {
        preserveScroll: true,
        onFinish: () => {
            newStopWord.value = '';
            router.visit(route('admin.sms-logs.index'), {
                data: tableFiltersStore.getQueryData,
            })
        },
    });
}

onMounted(() => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('logs');
    }

    currentTab.value = tableFiltersStore.getTab
})

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Сообщения" />

        <MainTableSection
            title="Сообщения"
            :data="smsLogs"
            :display-pagination="currentTab === 'logs'"
        >
            <template #button>
                <button
                    v-if="viewStore.isAdminViewMode"
                    type="button"
                    class="btn btn-outline btn-sm shrink-0"
                    @click="router.visit(route('admin.app.index'), { preserveScroll: true })"
                >
                    Приложение
                </button>
                <button
                    v-else
                    type="button"
                    class="btn btn-outline btn-sm shrink-0"
                    @click="router.visit(route('trader.devices.index'), { preserveScroll: true })"
                >
                    Устройства
                </button>
            </template>
            <template v-slot:header>
                <ul v-if="viewStore.isAdminViewMode" class="flex flex-wrap text-sm font-medium text-center">
                    <li class="me-2">
                        <a @click.prevent="openPage('logs')" href="#" :class="currentTab === 'logs' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" aria-current="page">
                            <svg class="w-4 h-4 sm:mr-2 mr-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.556 8.5h8m-8 3.5H12m7.111-7H4.89a.896.896 0 0 0-.629.256.868.868 0 0 0-.26.619v9.25c0 .232.094.455.26.619A.896.896 0 0 0 4.89 16H9l3 4 3-4h4.111a.896.896 0 0 0 .629-.256.868.868 0 0 0 .26-.619v-9.25a.868.868 0 0 0-.26-.619.896.896 0 0 0-.63-.256Z"/>
                            </svg>
                            <span class="sm:block hidden">Сообщения</span>
                        </a>
                    </li>
                    <li class="me-2">
                        <a @click.prevent="openPage('stop-list')" href="#" :class="currentTab === 'stop-list' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" aria-current="page">
                            <svg class="w-4 h-4 sm:mr-2 mr-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m6 6 12 12m3-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            <span class="sm:block hidden">Стоп-лист (отправители)</span>
                        </a>
                    </li>
                    <li class="me-2">
                        <a @click.prevent="openPage('stop-words')" href="#" :class="currentTab === 'stop-words' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'" aria-current="page">
                            <svg class="w-4 h-4 sm:mr-2 mr-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 6H6m12 4H6m12 4H6m12 4H6"/>
                            </svg>
                            <span class="sm:block hidden">Стоп-слова</span>
                        </a>
                    </li>
                </ul>
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="sms-logs" v-if="currentTab === 'logs'">
                    <InputFilter
                        name="search"
                        placeholder="Поиск"
                        class="w-64"
                    />
                    <FilterCheckbox
                        v-if="viewStore.isAdminViewMode"
                        name="onlySuccessParsing"
                        title="Только зачисления"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <template v-if="currentTab === 'logs'">
                    <div v-if="viewStore.isAdminViewMode" class="flex gap-5">
                        <div class="text-base text-base-content/70 mb-3">
                            Всего логов:
                            <span class="font-semibold text-base-content mr-1">
                            {{ smsLogsTotalCount}}
                        </span>
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
                                            Отправитель
                                        </th>
                                        <th scope="col">
                                            Сообщение
                                        </th>
                                        <th scope="col" v-if="viewStore.isAdminViewMode">
                                            Парсинг
                                        </th>
                                        <th scope="col">
                                            Тип
                                        </th>
                                        <th scope="col" class="text-nowrap">
                                            UUID сделки
                                        </th>
                                        <th scope="col">
                                            Профиль
                                        </th>
                                        <th scope="col">
                                            Время
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="sms_log in smsLogs.data" :key="sms_log.id" class="hover">
                                        <th scope="row" class="font-medium whitespace-nowrap">
                                            {{ sms_log.id }}
                                        </th>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <template v-if="!viewStore.isAdminViewMode">
                                                    <div class="flex items-center gap-3">
                                                        <GatewayLogo v-if="sms_log.payment_gateway" :img_path="sms_log.payment_gateway.logo_path" class="w-10 h-10"/>
                                                        <div v-if="sms_log.payment_gateway" class="text-nowrap text-xs">
                                                            {{ sms_log.payment_gateway.name }}
                                                        </div>
                                                        <div v-else>
                                                            Неизвестный банк
                                                        </div>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div class="flex items-center gap-3">
                                                        <GatewayLogo v-if="sms_log.payment_gateway" :img_path="sms_log.payment_gateway.logo_path" class="w-10 h-10"/>
                                                        <div>
                                                            <div v-if="!sms_log.payment_gateway">
                                                                {{ sms_log.sender }}
                                                            </div>
                                                            <div v-else class="text-nowrap text-xs">
                                                                {{ sms_log.payment_gateway.name }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-if="!sms_log.sender_exists">
                                                        <div class="flex items-center gap-0.5">
                                                            <button
                                                                @click.prevent="openAddSenderModal(sms_log)"
                                                                class="btn btn-ghost btn-xs text-success"
                                                                aria-label="Добавить отправителя в банк"
                                                            >
                                                                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4.7 4.5L19 7"/>
                                                                </svg>
                                                            </button>
                                                            <button
                                                                @click.prevent="confirmAddSenderToStopLost(sms_log)"
                                                                class="btn btn-ghost btn-xs text-error"
                                                                aria-label="Добавить в стоп-лист"
                                                            >
                                                                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="min-width: 100px; max-width: 150px">{{ sms_log.message }}</div>
                                        </td>
                                        <td v-if="viewStore.isAdminViewMode">
                                            <div v-if="sms_log.parsing_result">
                                                <div v-if="sms_log.parsing_result.amount" class="flex gap-1">
                                                    <div>{{sms_log.parsing_result.amount}} {{sms_log.payment_gateway?.currency?.toUpperCase()}}</div>
                                                </div>
                                                <div v-if="sms_log.parsing_result.card" class="flex gap-1">
                                                    <div>*{{sms_log.parsing_result.card}}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {{ sms_log.type }}
                                        </td>
                                        <td>
                                            <DisplayUUID v-if="sms_log.order?.uuid" :uuid="sms_log.order?.uuid"/>
                                        </td>
                                        <td class="text-nowrap">
                                            <div>
                                                <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-2 text-nowrap">
                                                    <svg class="w-5 h-5 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-width="1.5" d="M7 17v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3Zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    </svg>
                                                    <span class="text-base-content">{{ sms_log.user.email }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-nowrap">
                                                    <svg class="w-4 h-4 ml-0.5 mr-0.5 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                    </svg>
                                                    <span class="text-base-content w-30 truncate">{{ sms_log.device?.name }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <DateTime :data="sms_log.created_at"></DateTime>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile view (cards list) -->
                        <div class="xl:hidden space-y-3">
                            <div
                                v-for="sms_log in smsLogs.data"
                                :key="sms_log.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-3 pb-3">
                                    <div class="flex items-center justify-between border-b border-base-content/10 pb-2 mb-2">
                                        <div class="text-xs text-base-content/70">ID: <span class="font-medium text-base-content">{{ sms_log.id }}</span></div>
                                        <DateTime class="justify-start" :data="sms_log.created_at"/>
                                    </div>

                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <GatewayLogo v-if="sms_log.payment_gateway" :img_path="sms_log.payment_gateway.logo_path" class="w-10 h-10"/>
                                            <div class="min-w-0">
                                                <!-- Не админ: показываем только банк/логотип или 'Неизвестный банк' -->
                                                <template v-if="!viewStore.isAdminViewMode">
                                                    <div v-if="sms_log.payment_gateway" class="text-nowrap text-xs opacity-70">
                                                        <span class="block sm:hidden truncate w-25">{{ sms_log.payment_gateway.name }}</span>
                                                        <span class="hidden sm:block">{{ sms_log.payment_gateway.name }}</span>
                                                    </div>
                                                    <div v-else class="text-xs opacity-70">
                                                        Неизвестный банк
                                                    </div>
                                                </template>
                                                <!-- Админ: если банк не определен, показываем sender; иначе банк -->
                                                <template v-else>
                                                    <div v-if="!sms_log.payment_gateway" class="flex items-center">
                                                        <div class="font-medium">
                                                            {{ sms_log.sender }}
                                                        </div>
                                                        <div v-if="viewStore.isAdminViewMode && !sms_log.sender_exists">
                                                            <div class="flex items-center gap-0.5">
                                                                <button
                                                                    @click.prevent="openAddSenderModal(sms_log)"
                                                                    class="btn btn-ghost btn-xs text-success"
                                                                    aria-label="Добавить отправителя в банк"
                                                                >
                                                                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4.7 4.5L19 7"/>
                                                                    </svg>
                                                                </button>
                                                                <button
                                                                    @click.prevent="confirmAddSenderToStopLost(sms_log)"
                                                                    class="btn btn-ghost btn-xs text-error"
                                                                    aria-label="Добавить в стоп-лист"
                                                                >
                                                                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-else class="text-nowrap text-xs opacity-70">
                                                        <span class="block sm:hidden truncate w-25">{{ sms_log.payment_gateway.name }}</span>
                                                        <span class="hidden sm:block">{{ sms_log.payment_gateway.name }}</span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="text-sm">
                                            <div class="font-medium">{{ sms_log.type.toUpperCase() }}</div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(sms_log.id)"
                                                :aria-expanded="!!expandedCards[sms_log.id]"
                                                :aria-label="!!expandedCards[sms_log.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[sms_log.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="bg-base-300/40 rounded-box p-2">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-info">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                                </svg>
                                            </div>
                                            <span class=" break-words">
                                                {{ sms_log.message }}
                                            </span>
                                        </div>
                                    </div>

                                    <div v-if="!!expandedCards[sms_log.id] && sms_log.parsing_result && viewStore.isAdminViewMode" class="bg-base-300/40 rounded-box p-2">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-info">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                            </svg>
                                            <div v-if="sms_log.parsing_result?.amount">
                                                Сумма: {{ sms_log.parsing_result.amount }} {{ sms_log.payment_gateway?.currency?.toUpperCase() }}
                                            </div>
                                            <div v-if="sms_log.parsing_result?.card">
                                                Карта: *{{ sms_log.parsing_result.card }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-show="!!expandedCards[sms_log.id]" class="space-y-2">
                                        <div v-if="viewStore.isAdminViewMode" class="bg-base-300/40 rounded-box p-2">
                                            <div class="flex items-center gap-2">
                                                <svg class="size-4 text-info" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                <span class="text-base-content break-words">{{ sms_log.user.email }}</span>
                                            </div>
                                        </div>
                                        <div class="bg-base-300/40 rounded-box p-2">
                                            <div class="flex items-center gap-2">
                                                <svg class="size-4 text-info" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                                </svg>
                                                <span class="text-base-content truncate">{{ sms_log.device?.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="sms_log.order?.uuid" class="bg-base-300/40 rounded-box p-2">
                                            <div class="flex items-center gap-2">
                                                <svg class="size-4 text-info" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 17.345a4.76 4.76 0 0 0 2.558 1.618c2.274.589 4.512-.446 4.999-2.31.487-1.866-1.273-3.9-3.546-4.49-2.273-.59-4.034-2.623-3.547-4.488.486-1.865 2.724-2.899 4.998-2.31.982.236 1.87.793 2.538 1.592m-3.879 12.171V21m0-18v2.2"/>
                                                </svg>
                                                <DisplayUUID v-if="sms_log.order?.uuid" :uuid="sms_log.order?.uuid"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else-if="currentTab === 'stop-list'">
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(item, key) in senderStopList" :id="`sender-stop-list-${key}`" class="badge badge-primary gap-1">
                            {{ item.sender }}
                            <button @click.prevent="deleteSenderFromStopList(item)" type="button" class="btn btn-ghost btn-xs" :data-dismiss-target="`#sender-stop-list-${key}`" aria-label="Remove">
                                ✕
                            </button>
                        </span>
                    </div>
                </template>
                <template v-else-if="currentTab === 'stop-words'">
                    <div class="mb-5">
                        <div class="flex items-center gap-2 mb-4">
                            <input
                                type="text"
                                v-model="newStopWord"
                                placeholder="Добавить стоп-слово"
                                class="input input-bordered w-52"
                            >
                            <button @click="addSmsStopWord" class="btn btn-primary">Добавить</button>
                        </div>
                        <p class="text-sm opacity-70 mb-4">
                            Стоп-слова используются для фильтрации SMS сообщений. Сообщения, содержащие эти слова, будут игнорироваться при парсинге.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(item, key) in smsStopWords" :id="`sms-stop-word-${key}`" class="badge badge-success gap-1">
                            {{ item.word }}
                            <button @click.prevent="deleteSmsStopWord(item)" type="button" class="btn btn-ghost btn-xs" :data-dismiss-target="`#sms-stop-word-${key}`" aria-label="Remove">✕</button>
                        </span>
                    </div>
                </template>
            </template>
        </MainTableSection>

        <Modal :show="isAddSenderModalOpen" @close="closeAddSenderModal" maxWidth="md">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">Добавить отправителя</h3>
                        <p class="text-sm text-base-content/70">
                            Отправитель: <span class="font-medium">{{ selectedSmsLog?.sender }}</span>
                        </p>
                    </div>
                    <button class="btn btn-sm btn-ghost" @click="closeAddSenderModal" type="button">✕</button>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Поиск банка</span>
                    </label>
                    <input
                        v-model="gatewaySearch"
                        type="text"
                        class="input input-bordered w-full"
                        placeholder="Введите название банка"
                    >
                </div>

                <div
                    class="mx-auto w-full h-52 shrink-0 overflow-y-auto overflow-x-hidden border border-base-300 rounded-box"
                >
                    <div v-if="filteredPaymentGateways.length === 0" class="flex h-full items-center justify-center p-4 text-sm text-base-content/70">
                        Банки не найдены.
                    </div>
                    <button
                        v-for="paymentGateway in filteredPaymentGateways"
                        :key="paymentGateway.id"
                        type="button"
                        class="w-full border-b border-base-300 py-2.5 pl-2.5 pr-3 text-left transition last:border-b-0"
                        :class="selectedPaymentGatewayId === paymentGateway.id
                            ? 'border-l-[3px] border-l-base-content/20 bg-base-200/60'
                            : 'border-l-[3px] border-l-transparent hover:bg-base-200/50'"
                        @click="selectedPaymentGatewayId = paymentGateway.id; pendingConfirmGatewayId = null"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <img
                                v-if="paymentGateway.logo_path"
                                :src="paymentGateway.logo_path"
                                class="size-8 shrink-0 rounded opacity-90"
                                alt="Логотип банка"
                                loading="lazy"
                                decoding="async"
                            >
                            <div
                                class="min-w-0 truncate text-sm"
                                :class="selectedPaymentGatewayId === paymentGateway.id ? 'font-medium text-base-content' : 'text-base-content/80'"
                            >
                                {{ paymentGateway.name }}
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="selectedPaymentGateway"
                    class="rounded-box border border-base-300/80 bg-base-200/30 p-3"
                >
                    <div class="text-xs font-medium text-base-content/55">
                        Выбран банк
                    </div>
                    <div class="mt-1.5 flex min-w-0 items-center gap-3">
                        <img
                            v-if="selectedPaymentGateway.logo_path"
                            :src="selectedPaymentGateway.logo_path"
                            class="size-9 shrink-0 rounded-md opacity-95"
                            alt=""
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="min-w-0 text-base font-semibold text-base-content">
                            {{ selectedPaymentGateway.name }}
                        </div>
                    </div>
                </div>

                <div v-if="addSenderToGatewayForm.errors.payment_gateway_id" class="alert alert-error alert-soft text-sm">
                    {{ addSenderToGatewayForm.errors.payment_gateway_id }}
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-sm" @click="closeAddSenderModal">Отмена</button>
                    <button
                        v-if="!isAddSenderConfirmStep"
                        type="button"
                        class="btn btn-sm btn-success"
                        :disabled="!selectedPaymentGatewayId || addSenderToGatewayForm.processing"
                        @click="requestSenderAddingConfirmation"
                    >
                        Добавить
                    </button>
                    <button
                        v-if="isAddSenderConfirmStep"
                        type="button"
                        class="btn btn-sm btn-warning"
                        :disabled="addSenderToGatewayForm.processing"
                        @click="addSenderToPaymentGateway"
                    >
                        Вы уверены?
                    </button>
                </div>
            </div>
        </Modal>

        <ConfirmModal/>
    </div>
</template>
