<script setup>
import axios from 'axios';
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import DateTime from '@/Components/DateTime.vue';
import SmsLogLinkedOrderCell from '@/Components/SmsLog/SmsLogLinkedOrderCell.vue';
import LinkSmsOrderModal from '@/Modals/SmsLog/LinkSmsOrderModal.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import {useModalStore} from '@/store/modal.js';
import {useViewStore} from '@/store/view.js';
import {
    isSmsLogLinkable,
    isSmsLogRejectable,
    isSmsLogRejected,
    rejectSmsLogRequest,
} from '@/composables/useSmsLogOrderLinkActions.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'count-updated']);

const modalStore = useModalStore();
const viewStore = useViewStore();

const smsLogs = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});
const unlinkedCount = ref(0);
const isLoading = ref(false);
const loadError = ref('');
const search = ref('');
const linkFilter = ref('unlinked');
const expandedCards = ref({});
const linkSmsOrderModalOpen = ref(false);
const selectedSmsLogForLink = ref(null);

const incomingSmsLogsRoute = computed(() => (
    viewStore.isAdminViewMode
        ? 'admin.incoming-sms-logs.index'
        : 'incoming-sms-logs.index'
));

const hasPagination = computed(() => pagination.value.last_page > 1);

const messageTypeBadgeClass = (type) => {
    return type === 'push' ? 'badge-info' : 'badge-accent';
};

const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const resetState = () => {
    smsLogs.value = [];
    pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
    };
    unlinkedCount.value = 0;
    loadError.value = '';
    search.value = '';
    linkFilter.value = 'unlinked';
    expandedCards.value = {};
};

const close = () => {
    emit('close');
};

const loadSmsLogs = async (pageNumber = 1) => {
    isLoading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(route(incomingSmsLogsRoute.value), {
            params: {
                page: pageNumber,
                per_page: pagination.value.per_page,
                link_filter: linkFilter.value,
                search: viewStore.isAdminViewMode ? (search.value.trim() || undefined) : undefined,
            },
        });

        if (response.data.success) {
            smsLogs.value = response.data.data ?? [];
            pagination.value = {
                current_page: response.data.meta?.current_page ?? 1,
                last_page: response.data.meta?.last_page ?? 1,
                per_page: response.data.meta?.per_page ?? 10,
                total: response.data.meta?.total ?? smsLogs.value.length,
            };
            unlinkedCount.value = response.data.unlinked_count ?? 0;
        }
    } catch (error) {
        loadError.value = error?.response?.data?.message ?? 'Не удалось загрузить сообщения.';
    } finally {
        isLoading.value = false;
    }
};

const applySearch = () => {
    loadSmsLogs(1);
};

const setLinkFilter = (value) => {
    if (linkFilter.value === value || isLoading.value) {
        return;
    }

    linkFilter.value = value;
    loadSmsLogs(1);
};

const goToPage = (pageNumber) => {
    if (pageNumber < 1 || pageNumber > pagination.value.last_page || pageNumber === pagination.value.current_page) {
        return;
    }

    loadSmsLogs(pageNumber);
};

const openLinkSmsOrderModal = (smsLog) => {
    selectedSmsLogForLink.value = smsLog;
    linkSmsOrderModalOpen.value = true;
};

const closeLinkSmsOrderModal = () => {
    linkSmsOrderModalOpen.value = false;
    selectedSmsLogForLink.value = null;
};

const handleSmsOrderLinked = async () => {
    closeLinkSmsOrderModal();
    await loadSmsLogs(pagination.value.current_page);
    emit('count-updated', unlinkedCount.value);
};

const confirmRejectSmsLog = (smsLog) => {
    modalStore.openConfirmModal({
        title: 'Отклонить сообщение?',
        body: 'Сообщение не будет привязано к сделке и исчезнет из списка ожидающих обработки.',
        confirm_button_name: 'Отклонить',
        confirm: async () => {
            try {
                await rejectSmsLogRequest(smsLog.id);
                await loadSmsLogs(pagination.value.current_page);
                emit('count-updated', unlinkedCount.value);
            } catch (error) {
                loadError.value = error?.response?.data?.message ?? 'Не удалось отклонить сообщение.';
            }
        },
    });
};

const confirmAddSenderToStopList = (smsLog) => {
    modalStore.openConfirmModal({
        title: `Добавить отправителя ${smsLog.sender} в стоп лист?`,
        body: `Все сообщения отправителя ${smsLog.sender} будут удалены, а новые сообщения будут игнорироваться.`,
        confirm_button_name: 'Подтвердить',
        confirm: () => {
            useForm({}).post(route('admin.sender-stop-list.store', smsLog.id), {
                preserveScroll: true,
                onFinish: () => {
                    loadSmsLogs(pagination.value.current_page);
                },
            });
        },
    });
};

watch(
    () => props.show,
    (isShown) => {
        if (isShown) {
            resetState();
            loadSmsLogs();
        }
    },
);
</script>

<template>
    <ModalNext :show="show" max-width="6xl" @close="close">
        <ModalHeaderNext
            title="Сообщения"
            @close="close"
        />

        <ModalBodyNext class="min-h-0 flex-1 space-y-3 overflow-y-auto">
            <div
                v-if="unlinkedCount > 0"
                class="text-sm text-base-content/70"
            >
                Без сделки:
                <span class="font-semibold text-base-content">{{ unlinkedCount }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="join">
                    <button
                        type="button"
                        class="btn btn-sm join-item"
                        :class="linkFilter === 'unlinked' ? 'btn-primary' : 'btn-outline'"
                        :disabled="isLoading"
                        @click="setLinkFilter('unlinked')"
                    >
                        Без сделки
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm join-item"
                        :class="linkFilter === 'all' ? 'btn-primary' : 'btn-outline'"
                        :disabled="isLoading"
                        @click="setLinkFilter('all')"
                    >
                        Все
                    </button>
                </div>
            </div>

            <div
                v-if="viewStore.isAdminViewMode"
                class="flex flex-wrap items-center gap-2"
            >
                <input
                    v-model="search"
                    type="text"
                    class="input input-bordered input-sm w-full max-w-xs"
                    placeholder="Поиск по сообщению"
                    @keydown.enter.prevent="applySearch"
                >
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="isLoading"
                    @click="applySearch"
                >
                    Найти
                </button>
            </div>

            <div v-if="isLoading" class="flex items-center justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <div v-else-if="loadError" class="alert alert-error">
                <span>{{ loadError }}</span>
            </div>

            <div v-else-if="smsLogs.length === 0" class="rounded-box border border-base-300/70 bg-base-200/30 px-4 py-8 text-center text-sm text-base-content/70">
                Поступлений пока нет.
            </div>

            <template v-else>
                <div class="hidden xl:block overflow-x-auto rounded-box border border-base-300/70">
                    <table class="table table-sm">
                        <thead class="bg-base-300 text-xs uppercase">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Сообщение</th>
                                <th scope="col">Данные</th>
                                <th scope="col">Сделка</th>
                                <th scope="col">Приложение</th>
                                <th scope="col">Время</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sms_log in smsLogs" :key="sms_log.id" class="hover">
                                <th scope="row" class="whitespace-nowrap font-medium">
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
                                                    type="button"
                                                    class="btn btn-ghost btn-xs text-error"
                                                    aria-label="Добавить в стоп-лист"
                                                    @click.prevent="confirmAddSenderToStopList(sms_log)"
                                                >
                                                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="max-w-[220px] min-w-[100px] text-base-content">
                                            {{ sms_log.message }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="space-y-0.5 text-xs">
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
                                </td>
                                <td>
                                    <SmsLogLinkedOrderCell
                                        :order="sms_log.order"
                                        :linkable="isSmsLogLinkable(sms_log)"
                                        :rejectable="isSmsLogRejectable(sms_log)"
                                        :rejected="isSmsLogRejected(sms_log)"
                                        :show-order-details-button="false"
                                        @link="openLinkSmsOrderModal(sms_log)"
                                        @reject="confirmRejectSmsLog(sms_log)"
                                    />
                                </td>
                                <td class="text-nowrap">
                                    <div>
                                        <div v-if="viewStore.isAdminViewMode" class="flex items-center gap-2 text-nowrap">
                                            <svg class="h-5 w-5 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-width="1.5" d="M7 17v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3Zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                            </svg>
                                            <span class="text-base-content">{{ sms_log.user?.email }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-nowrap">
                                            <svg class="mr-0.5 ml-0.5 h-4 w-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                                            </svg>
                                            <span class="w-30 truncate text-base-content">{{ sms_log.device?.name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <DateTime :data="sms_log.created_at" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 xl:hidden">
                    <div
                        v-for="sms_log in smsLogs"
                        :key="sms_log.id"
                        class="card bg-base-100 shadow-sm"
                    >
                        <div class="card-body p-4 pt-3 pb-3">
                            <div class="mb-2 flex items-center justify-between border-b border-base-content/10 pb-2">
                                <div class="text-xs text-base-content/70">
                                    ID: <span class="font-medium text-base-content">{{ sms_log.id }}</span>
                                </div>
                                <DateTime class="justify-start" :data="sms_log.created_at" />
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <div class="text-primary text-sm font-medium text-nowrap">
                                        {{ sms_log.sender }}
                                    </div>
                                    <span class="badge badge-outline badge-xs shrink-0" :class="messageTypeBadgeClass(sms_log.type)">
                                        {{ sms_log.type.toUpperCase() }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-xs"
                                    :aria-expanded="!!expandedCards[sms_log.id]"
                                    @click.stop="toggleExpand(sms_log.id)"
                                >
                                    <svg
                                        :class="['h-4 w-4 transition-transform', {'rotate-180': !!expandedCards[sms_log.id]}]"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="rounded-box bg-base-300/40 p-2 text-sm break-words">
                                {{ sms_log.message }}
                            </div>

                            <div class="space-y-0.5 text-xs text-base-content/90">
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

                            <div class="rounded-box border border-base-300/60 bg-base-200/30 p-2">
                                <div class="mb-1 text-[0.65rem] tracking-wide text-base-content/50 uppercase">Сделка</div>
                                <SmsLogLinkedOrderCell
                                    :order="sms_log.order"
                                    :linkable="isSmsLogLinkable(sms_log)"
                                    :rejectable="isSmsLogRejectable(sms_log)"
                                    :rejected="isSmsLogRejected(sms_log)"
                                    :show-order-details-button="false"
                                    @link="openLinkSmsOrderModal(sms_log)"
                                    @reject="confirmRejectSmsLog(sms_log)"
                                />
                            </div>

                            <div v-show="!!expandedCards[sms_log.id]" class="space-y-2">
                                <div v-if="viewStore.isAdminViewMode" class="rounded-box bg-base-300/40 p-2 text-sm">
                                    {{ sms_log.user?.email }}
                                </div>
                                <div class="rounded-box bg-base-300/40 p-2 text-sm">
                                    {{ sms_log.device?.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="hasPagination" class="flex justify-center pt-2">
                    <Pagination
                        :model-value="pagination.current_page"
                        :total-items="pagination.total"
                        :per-page="pagination.per_page"
                        :enable-first-and-last-buttons="false"
                        :show-icons="true"
                        :show-labels="false"
                        @update:model-value="goToPage"
                    />
                </div>
            </template>
        </ModalBodyNext>
    </ModalNext>

    <LinkSmsOrderModal
        :show="linkSmsOrderModalOpen"
        :sms-log="selectedSmsLogForLink"
        @close="closeLinkSmsOrderModal"
        @linked="handleSmsOrderLinked"
    />
</template>
