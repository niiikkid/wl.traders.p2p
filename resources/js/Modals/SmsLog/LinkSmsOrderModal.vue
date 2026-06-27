<script setup>
import axios from 'axios';
import {computed, ref, watch} from 'vue';
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import ModalFooterNext from '@/Components/Modals/Next/ModalFooterNext.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import PaymentDetail from '@/Components/PaymentDetail.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import DateTime from '@/Components/DateTime.vue';
import {useViewStore} from '@/store/view.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    smsLog: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'linked']);

const viewStore = useViewStore();

const orders = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});
const isLoading = ref(false);
const loadError = ref('');
const searchAmount = ref('');
const searchPaymentDetail = ref('');
const selectedOrderId = ref(null);
const isConfirming = ref(false);
const isLinking = ref(false);
const linkError = ref('');

const selectedOrder = computed(() => {
    return orders.value.find((item) => item.id === selectedOrderId.value) ?? null;
});

const modalTitle = computed(() => 'Привязать сделку');

const modalSubtitle = computed(() => {
    if (!props.smsLog) {
        return null;
    }

    const amount = props.smsLog?.parsing_result?.amount;

    if (amount) {
        return `Поступление ${amount}`;
    }

    return `Сообщение #${props.smsLog.id}`;
});

const orderCardClass = (orderId) => {
    return selectedOrderId.value === orderId
        ? 'border-primary bg-primary/10 shadow-sm'
        : 'border-base-300/80 bg-base-100 hover:border-primary/40 hover:bg-base-200/40';
};

const hasPagination = computed(() => pagination.value.last_page > 1);

const resetState = () => {
    orders.value = [];
    pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
    };
    selectedOrderId.value = null;
    isConfirming.value = false;
    isLinking.value = false;
    loadError.value = '';
    linkError.value = '';
};

const close = () => {
    if (isLinking.value) {
        return;
    }

    emit('close');
};

const loadOrders = async (pageNumber = 1) => {
    if (!props.smsLog?.id) {
        return;
    }

    isLoading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(route('sms-logs.unlinked-orders.index', props.smsLog.id), {
            params: {
                page: pageNumber,
                per_page: pagination.value.per_page,
                amount: searchAmount.value.trim() || undefined,
                payment_detail: searchPaymentDetail.value.trim() || undefined,
            },
        });

        if (response.data.success) {
            orders.value = response.data.data ?? [];
            pagination.value = {
                current_page: response.data.meta?.current_page ?? 1,
                last_page: response.data.meta?.last_page ?? 1,
                per_page: response.data.meta?.per_page ?? 10,
                total: response.data.meta?.total ?? orders.value.length,
            };

            if (!orders.value.some((order) => order.id === selectedOrderId.value)) {
                selectedOrderId.value = orders.value[0]?.id ?? null;
            }
        }
    } catch (error) {
        loadError.value = error?.response?.data?.message ?? 'Не удалось загрузить сделки.';
    } finally {
        isLoading.value = false;
    }
};

const applySearch = () => {
    if (isLinking.value) {
        return;
    }

    selectedOrderId.value = null;
    isConfirming.value = false;
    linkError.value = '';
    loadOrders(1);
};

const goToPage = (pageNumber) => {
    if (isLinking.value || pageNumber < 1 || pageNumber > pagination.value.last_page) {
        return;
    }

    loadOrders(pageNumber);
};

const selectOrder = (orderId) => {
    if (isLinking.value) {
        return;
    }

    selectedOrderId.value = orderId;
    isConfirming.value = false;
    linkError.value = '';
};

const startLink = () => {
    if (!selectedOrderId.value || isLinking.value) {
        return;
    }

    isConfirming.value = true;
    linkError.value = '';
};

const cancelLink = () => {
    if (isLinking.value) {
        return;
    }

    isConfirming.value = false;
    linkError.value = '';
};

const confirmLink = async () => {
    if (!props.smsLog?.id || !selectedOrderId.value || isLinking.value) {
        return;
    }

    isLinking.value = true;
    linkError.value = '';

    try {
        const response = await axios.post(route('sms-logs.link-order.store', props.smsLog.id), {
            order_id: selectedOrderId.value,
        });

        if (response.data.success) {
            emit('linked', response.data.data.sms_log);
            emit('close');
        }
    } catch (error) {
        linkError.value = error?.response?.data?.message ?? 'Не удалось привязать сделку.';
        isConfirming.value = false;
    } finally {
        isLinking.value = false;
    }
};

watch(
    () => props.show,
    (showed) => {
        if (showed) {
            resetState();
            searchAmount.value = '';
            searchPaymentDetail.value = '';
            loadOrders(1);
        }
    },
);
</script>

<template>
    <ModalNext :show="show" max-width="5xl" @close="close">
        <ModalHeaderNext
            :title="modalTitle"
            @close="close"
        />

        <ModalBodyNext>
            <p
                v-if="modalSubtitle"
                class="mb-3 text-xs text-base-content/60 sm:text-sm"
            >
                {{ modalSubtitle }}
            </p>

            <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-end">
                <label class="form-control w-full">
                    <div class="label py-1">
                        <span class="label-text text-xs">Сумма</span>
                    </div>
                    <input
                        v-model="searchAmount"
                        type="text"
                        inputmode="decimal"
                        placeholder="Точное совпадение"
                        class="input input-bordered input-sm w-full"
                        :disabled="isLoading || isLinking"
                        @keydown.enter.prevent="applySearch"
                    >
                </label>
                <label class="form-control min-w-0 w-full">
                    <div class="label py-1">
                        <span class="label-text text-xs">Реквизит</span>
                    </div>
                    <input
                        v-model="searchPaymentDetail"
                        type="text"
                        placeholder="Поиск по реквизиту"
                        class="input input-bordered input-sm w-full"
                        :disabled="isLoading || isLinking"
                        @keydown.enter.prevent="applySearch"
                    >
                </label>
                <button
                    type="button"
                    class="btn btn-primary btn-sm w-full sm:w-auto sm:min-w-24"
                    :disabled="isLoading || isLinking"
                    @click="applySearch"
                >
                    Найти
                </button>
            </div>

            <div v-if="isLoading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <div
                v-else-if="loadError"
                role="alert"
                class="alert alert-error alert-soft text-sm"
            >
                <span>{{ loadError }}</span>
            </div>

            <div
                v-else-if="orders.length === 0"
                role="alert"
                class="alert alert-dash text-sm"
            >
                <span>Нет сделок без привязанного сообщения для этого устройства.</span>
            </div>

            <div v-else class="space-y-3">
                <p class="text-xs text-base-content/60">
                    Выберите сделку и привяжите её к поступлению.
                </p>

                <div class="hidden overflow-x-auto rounded-box border border-base-300 xl:block">
                    <table class="table table-sm">
                        <thead class="bg-base-300 text-xs uppercase">
                            <tr>
                                <th scope="col">UUID</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Реквизит</th>
                                <th v-if="viewStore.isAdminViewMode" scope="col">Профиль</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Создан</th>
                                <th scope="col" class="text-right" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in orders"
                                :key="`desktop-${order.id}`"
                                class="cursor-pointer"
                                :class="selectedOrderId === order.id ? 'bg-primary/10' : 'hover:bg-base-200/60'"
                                @click="selectOrder(order.id)"
                            >
                                <th scope="row" class="whitespace-nowrap font-medium">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span
                                            v-if="viewStore.isAdminViewMode && order.manual_control_acquiring"
                                            class="badge badge-primary badge-xs"
                                            title="Manual Control Acquiring"
                                        >
                                            MC
                                        </span>
                                        <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                    </div>
                                </th>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <MoneyValue :value="order.amount" :currency="order.currency" block />
                                        <span
                                            v-if="order.amount_matches_sms"
                                            class="badge badge-success badge-xs"
                                        >
                                            Сумма совпадает
                                        </span>
                                    </div>
                                    <MoneyValue
                                        v-if="viewStore.isAdminViewMode"
                                        :value="order.total_profit"
                                        :currency="order.base_currency"
                                        secondary
                                        block
                                    />
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <GatewayLogo
                                            :img_path="order.payment_gateway_logo_path"
                                            :name="order.payment_gateway_name"
                                            class="h-8 w-8 text-base-content/50"
                                        />
                                        <PaymentDetail
                                            :detail="order.payment_detail"
                                            :type="order.payment_detail_type"
                                            :name="order.payment_detail_name"
                                        />
                                    </div>
                                </td>
                                <td v-if="viewStore.isAdminViewMode" class="text-nowrap">
                                    <div class="text-base-content">{{ order.trader_email }}</div>
                                    <div class="truncate text-xs text-base-content/70">
                                        {{ order.device_name ?? 'Без устройства' }}
                                    </div>
                                </td>
                                <td>
                                    <OrderStatus :status="order.status" :status_name="order.status_name" />
                                </td>
                                <td class="text-nowrap">
                                    <DateTime class="justify-start" :data="order.created_at" />
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-xs"
                                        :class="{ 'btn-outline': selectedOrderId !== order.id }"
                                        :disabled="isLinking"
                                        @click.stop="selectOrder(order.id)"
                                    >
                                        Выбрать
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2 xl:hidden">
                    <button
                        v-for="order in orders"
                        :key="`mobile-${order.id}`"
                        type="button"
                        class="w-full rounded-box border p-3 text-left transition"
                        :class="orderCardClass(order.id)"
                        :disabled="isLinking"
                        @click="selectOrder(order.id)"
                    >
                        <div class="flex items-start justify-between gap-3 border-b border-base-300/50 pb-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        v-if="viewStore.isAdminViewMode && order.manual_control_acquiring"
                                        class="badge badge-primary badge-xs"
                                        title="Manual Control Acquiring"
                                    >
                                        MC
                                    </span>
                                    <CopyableOrderUid :uuid="order.uuid ?? ''" />
                                </div>
                                <div class="mt-1">
                                    <DateTime
                                        class="text-xs text-base-content/60"
                                        :data="order.created_at"
                                        :simple="true"
                                    />
                                </div>
                            </div>
                            <OrderStatus :status="order.status" :status_name="order.status_name" />
                        </div>

                        <div class="mt-2 flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <GatewayLogo
                                    :img_path="order.payment_gateway_logo_path"
                                    :name="order.payment_gateway_name"
                                    class="h-9 w-9 shrink-0 text-base-content/50"
                                />
                                <PaymentDetail
                                    :detail="order.payment_detail"
                                    :type="order.payment_detail_type"
                                    :name="order.payment_detail_name"
                                />
                            </div>
                            <div class="shrink-0 text-right">
                                <MoneyValue :value="order.amount" :currency="order.currency" block />
                                <MoneyValue
                                    v-if="viewStore.isAdminViewMode"
                                    :value="order.total_profit"
                                    :currency="order.base_currency"
                                    secondary
                                    block
                                />
                            </div>
                        </div>

                        <div
                            v-if="order.amount_matches_sms || (viewStore.isAdminViewMode && order.trader_email)"
                            class="mt-2 flex flex-wrap items-center gap-2 border-t border-base-300/50 pt-2 text-xs text-base-content/70"
                        >
                            <span
                                v-if="order.amount_matches_sms"
                                class="badge badge-success badge-xs"
                            >
                                Сумма совпадает
                            </span>
                            <span v-if="viewStore.isAdminViewMode && order.trader_email" class="truncate">
                                {{ order.trader_email }}
                            </span>
                            <span
                                v-if="viewStore.isAdminViewMode && order.device_name"
                                class="truncate text-base-content/60"
                            >
                                {{ order.device_name }}
                            </span>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <span
                                class="btn btn-primary btn-xs pointer-events-none"
                                :class="{ 'btn-outline': selectedOrderId !== order.id }"
                            >
                                {{ selectedOrderId === order.id ? 'Выбрано' : 'Выбрать' }}
                            </span>
                        </div>
                    </button>
                </div>

                <div
                    v-if="hasPagination"
                    class="flex flex-col gap-2 text-xs text-base-content/70 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="text-center sm:text-left">
                        Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                        · всего {{ pagination.total }}
                    </span>
                    <div class="join">
                        <button
                            type="button"
                            class="btn btn-xs join-item"
                            :disabled="pagination.current_page <= 1 || isLoading || isLinking"
                            @click="goToPage(pagination.current_page - 1)"
                        >
                            Назад
                        </button>
                        <button
                            type="button"
                            class="btn btn-xs join-item"
                            :disabled="pagination.current_page >= pagination.last_page || isLoading || isLinking"
                            @click="goToPage(pagination.current_page + 1)"
                        >
                            Вперёд
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="linkError" class="mt-3 text-xs text-error">
                {{ linkError }}
            </p>
        </ModalBodyNext>

        <ModalFooterNext v-if="!isLoading && !loadError && orders.length > 0">
            <div
                v-if="isConfirming && selectedOrder"
                role="alert"
                class="alert alert-warning alert-soft mb-3 w-full text-sm"
            >
                <span class="text-pretty">
                    Привязать сообщение к сделке
                    <span class="font-medium">{{ selectedOrder.uuid?.slice(0, 8) }}</span>
                    на сумму {{ selectedOrder.amount }} {{ selectedOrder.currency?.toUpperCase() }}?
                </span>
            </div>

            <div
                v-if="isConfirming"
                class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 sm:join"
            >
                <button
                    type="button"
                    class="btn btn-sm w-full sm:join-item sm:flex-1"
                    :disabled="isLinking"
                    @click="cancelLink"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary w-full sm:join-item sm:flex-1"
                    :disabled="isLinking"
                    @click="confirmLink"
                >
                    <span v-if="isLinking" class="loading loading-spinner loading-xs" />
                    <span v-else>Да, привязать</span>
                </button>
            </div>
            <button
                v-else
                type="button"
                class="btn btn-primary btn-sm w-full"
                :disabled="!selectedOrderId || isLinking"
                @click="startLink"
            >
                Привязать
            </button>
        </ModalFooterNext>
    </ModalNext>
</template>
