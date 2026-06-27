<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AutomationNav from '@/Components/Admin/AutomationNav.vue';
import AutomationMessagesSubNav from '@/Components/Admin/AutomationMessagesSubNav.vue';
import TraderAutomationNav from '@/Components/Trader/AutomationNav.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import {computed, onMounted, ref} from "vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Partials/FilterCheckbox.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import DateTime from "@/Components/DateTime.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import SmsLogLinkedOrderCell from "@/Components/SmsLog/SmsLogLinkedOrderCell.vue";
import OrderModal from "@/Modals/OrderModal.vue";
import LinkSmsOrderModal from "@/Modals/SmsLog/LinkSmsOrderModal.vue";
import {
    isSmsLogLinkable,
    isSmsLogRejectable,
    isSmsLogRejected,
    rejectSmsLogRequest,
} from '@/composables/useSmsLogOrderLinkActions.js';

const modalStore = useModalStore();
const viewStore = useViewStore();
const page = usePage();
/** Реактивные пропсы страницы — после POST/редиректа таблица обновляется (как Order/Index, PaymentDetail). */
const smsLogs = computed(() => page.props.smsLogs);
const smsLogsTotalCount = computed(() => page.props.smsLogsTotalCount);
const senderStopList = computed(() => page.props.senderStopList);
const smsStopWords = computed(() => page.props.smsStopWords);
const expandedCards = ref({});
const currentTab = computed(() => tableFiltersStore.getTab || 'logs');
const newStopWord = ref('');
const tableFiltersStore = useTableFiltersStore();
const linkSmsOrderModalOpen = ref(false);
const selectedSmsLogForLink = ref(null);

const showSmsLogFilters = computed(() => {
    if (viewStore.isAdminViewMode) {
        return currentTab.value === 'logs';
    }

    return true;
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

const paymentDirectionLabel = (smsLog) => {
    const operationType = smsLog?.parsing_result?.operation_type;

    return operationType === 'in'
        ? 'Поступление'
        : (operationType === 'out' ? 'списание' : 'неопределено');
}

const paymentDirectionBadgeClass = (smsLog) => {
    const operationType = smsLog?.parsing_result?.operation_type;

    if (operationType === 'in') {
        return 'badge-success';
    }

    if (operationType === 'out') {
        return 'badge-error';
    }

    return 'badge-neutral';
}

const messageTypeBadgeClass = (type) => {
    return type === 'push' ? 'badge-info' : 'badge-accent';
}

const openOrderModal = (order) => {
    modalStore.openOrderModal({order_id: order.id});
};

const openLinkSmsOrderModal = (smsLog) => {
    selectedSmsLogForLink.value = smsLog;
    linkSmsOrderModalOpen.value = true;
};

const closeLinkSmsOrderModal = () => {
    linkSmsOrderModalOpen.value = false;
    selectedSmsLogForLink.value = null;
};

const handleSmsOrderLinked = () => {
    closeLinkSmsOrderModal();
    router.reload({
        only: viewStore.isAdminViewMode ? ['smsLogs', 'smsLogsTotalCount'] : ['smsLogs'],
        preserveScroll: true,
    });
};

const confirmRejectSmsLog = (smsLog) => {
    modalStore.openConfirmModal({
        title: 'Отклонить сообщение?',
        body: 'Сообщение не будет привязано к сделке и исчезнет из списка ожидающих обработки.',
        confirm_button_name: 'Отклонить',
        confirm: async () => {
            try {
                await rejectSmsLogRequest(smsLog.id);
                router.reload({
                    only: viewStore.isAdminViewMode ? ['smsLogs', 'smsLogsTotalCount'] : ['smsLogs'],
                    preserveScroll: true,
                });
            } catch (error) {
                modalStore.openConfirmModal({
                    title: 'Не удалось отклонить',
                    body: error?.response?.data?.message ?? 'Попробуйте ещё раз позже.',
                    confirm_button_name: 'Понятно',
                    confirm: () => {},
                });
            }
        },
    });
};

onMounted(() => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('logs');
    }
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
            <template v-slot:header>
                <div class="space-y-4">
                    <AutomationNav v-if="viewStore.isAdminViewMode" current="messages" />
                    <TraderAutomationNav v-if="viewStore.isTraderViewMode" current="messages" />

                    <AutomationMessagesSubNav
                        v-if="viewStore.isAdminViewMode"
                        :current="currentTab"
                        @change="openPage"
                    />

                    <FiltersPanel
                        v-if="showSmsLogFilters"
                        name="sms-logs"
                    >
                        <template v-if="viewStore.isAdminViewMode">
                            <InputFilter
                                name="search"
                                placeholder="Поиск"
                                class="w-64"
                            />
                            <FilterCheckbox
                                name="onlySuccessParsing"
                                title="Только зачисления"
                            />
                        </template>
                        <DropdownFilter
                            name="smsOperationTypes"
                            title="Операция"
                        />
                        <FilterCheckbox
                            v-if="viewStore.isTraderViewMode"
                            name="onlyUnlinkedIncoming"
                            title="Поступление без сделки"
                        />
                    </FiltersPanel>
                </div>
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
                        <DataTable>
                                    <template #head>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col">
                                            Сообщение
                                        </th>
                                        <th scope="col">
                                            Операции
                                        </th>
                                        <th scope="col">
                                            Сделка
                                        </th>
                                        <th scope="col">
                                            Приложение
                                        </th>
                                        <th scope="col">
                                            Время
                                        </th>
                                    </template>
                                    <tr v-for="sms_log in smsLogs.data" :key="sms_log.id" class="hover">
                                        <th scope="row" class="font-medium whitespace-nowrap">
                                            {{ sms_log.id }}
                                        </th>
                                        <td>
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="text-primary text-xs text-nowrap">
                                                        {{ sms_log.sender }}
                                                    </div>
                                                    <span class="badge badge-outline badge-xs" :class="messageTypeBadgeClass(sms_log.type)">
                                                        {{ sms_log.type.toUpperCase() }}
                                                    </span>
                                                    <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-0.5">
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
                                                <div class="text-base-content" style="min-width: 100px; max-width: 220px">
                                                    {{ sms_log.message }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-y-1">
                                                <span class="badge badge-sm whitespace-nowrap" :class="paymentDirectionBadgeClass(sms_log)">
                                                    {{ paymentDirectionLabel(sms_log) }}
                                                </span>
                                                <div v-if="['in', 'out'].includes(sms_log?.parsing_result?.operation_type)" class="text-xs space-y-0.5">
                                                    <div v-if="sms_log.parsing_result?.bank">
                                                        Банк: {{ sms_log.parsing_result.bank }}
                                                    </div>
                                                    <div v-if="sms_log.parsing_result?.amount">
                                                        Сумма: {{ sms_log.parsing_result.amount }}
                                                    </div>
                                                    <div v-if="sms_log.parsing_result?.card">
                                                        Карта: *{{ sms_log.parsing_result.card }}
                                                    </div>
                                                    <div v-if="sms_log.parsing_result?.balance">
                                                        Баланс: {{ sms_log.parsing_result.balance }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <SmsLogLinkedOrderCell
                                                :order="sms_log.order"
                                                :linkable="isSmsLogLinkable(sms_log)"
                                                :rejectable="isSmsLogRejectable(sms_log)"
                                                :rejected="isSmsLogRejected(sms_log)"
                                                @open-order="openOrderModal"
                                                @link="openLinkSmsOrderModal(sms_log)"
                                                @reject="confirmRejectSmsLog(sms_log)"
                                            />
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
                        </DataTable>

                        <!-- Mobile view (cards list) -->
                        <DataCardList>
                            <DataCard
                                v-for="sms_log in smsLogs.data"
                                :key="sms_log.id"
                                body-class="p-4 pt-3 pb-3"
                            >
                                    <div class="flex items-center justify-between border-b border-base-content/10 pb-2 mb-2">
                                        <div class="text-xs text-base-content/70">ID: <span class="font-medium text-base-content">{{ sms_log.id }}</span></div>
                                        <DateTime class="justify-start" :data="sms_log.created_at"/>
                                    </div>

                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="text-primary text-sm font-medium text-nowrap">
                                                        {{ sms_log.sender }}
                                                    </div>
                                                    <span class="badge badge-outline badge-xs shrink-0" :class="messageTypeBadgeClass(sms_log.type)">
                                                        {{ sms_log.type.toUpperCase() }}
                                                    </span>
                                                    <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-0.5">
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
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
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

                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <span class="badge badge-sm whitespace-nowrap" :class="paymentDirectionBadgeClass(sms_log)">
                                            {{ paymentDirectionLabel(sms_log) }}
                                        </span>
                                    </div>

                                    <div class="rounded-box border border-base-300/60 bg-base-200/30 p-2">
                                        <div class="mb-1 text-[0.65rem] uppercase tracking-wide text-base-content/50">Сделка</div>
                                        <SmsLogLinkedOrderCell
                                            :order="sms_log.order"
                                            :linkable="isSmsLogLinkable(sms_log)"
                                            :rejectable="isSmsLogRejectable(sms_log)"
                                            :rejected="isSmsLogRejected(sms_log)"
                                            @open-order="openOrderModal"
                                            @link="openLinkSmsOrderModal(sms_log)"
                                            @reject="confirmRejectSmsLog(sms_log)"
                                        />
                                    </div>

                                    <div v-if="['in', 'out'].includes(sms_log?.parsing_result?.operation_type)" class="text-xs space-y-0.5 pt-1 text-base-content/90">
                                        <div v-if="sms_log.parsing_result?.bank">
                                            Банк: {{ sms_log.parsing_result.bank }}
                                        </div>
                                        <div v-if="sms_log.parsing_result?.amount">
                                            Сумма: {{ sms_log.parsing_result.amount }}
                                        </div>
                                        <div v-if="sms_log.parsing_result?.card">
                                            Карта: *{{ sms_log.parsing_result.card }}
                                        </div>
                                        <div v-if="sms_log.parsing_result?.balance">
                                            Баланс: {{ sms_log.parsing_result.balance }}
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
                                    </div>
                            </DataCard>
                        </DataCardList>
                    </div>
                </template>
                <template v-else-if="currentTab === 'stop-list'">
                    <div role="alert" class="alert alert-info alert-soft text-sm mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                        </svg>
                        <div class="space-y-1">
                            <p class="font-medium">Стоп-лист отправителей</p>
                            <p>
                                Здесь перечислены имена отправителей СМС и push-уведомлений, которые система полностью игнорирует.
                                Это помогает отсечь спам, рекламу и служебные сообщения, не связанные с платежами.
                            </p>
                            <p class="text-base-content/70">
                                Добавить отправителя можно на вкладке «Сообщения» — кнопка ✕ рядом с именем.
                                Все его сообщения удалятся, а новые не будут сохраняться.
                                Чтобы снова принимать сообщения, удалите отправителя из списка.
                            </p>
                        </div>
                    </div>
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
                    <div role="alert" class="alert alert-info alert-soft text-sm mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                        </svg>
                        <div class="space-y-1">
                            <p class="font-medium">Стоп-слова</p>
                            <p>
                                Здесь задаются слова и фразы, по которым система отбрасывает сообщения по содержимому текста.
                                Это удобно для рекламы, промо и других уведомлений, которые не относятся к поступлениям.
                            </p>
                            <p class="text-base-content/70">
                                Если в тексте СМС или push есть стоп-слово, сообщение не сохраняется и не участвует в автоматике.
                                Добавьте слово в поле ниже или удалите его кнопкой ✕ в списке.
                            </p>
                        </div>
                    </div>
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

        <ConfirmModal/>
        <OrderModal/>
        <LinkSmsOrderModal
            :show="linkSmsOrderModalOpen"
            :sms-log="selectedSmsLogForLink"
            @close="closeLinkSmsOrderModal"
            @linked="handleSmsOrderLinked"
        />
    </div>
</template>
