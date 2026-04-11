<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import Modal from "@/Components/Modals/Modal.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import {Link, router, useForm, usePage} from "@inertiajs/vue3";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import {useModalStore} from "@/store/modal.js";
import {storeToRefs} from "pinia";
import {useViewStore} from "@/store/view.js";
import {computed, ref} from "vue";
import DateTime from "@/Components/DateTime.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import DUUID from "@/Components/DUUID.vue";
import EditOrderAmountModal from "@/Modals/Order/EditOrderAmountModal.vue";

const viewStore = useViewStore();
const modalStore = useModalStore();
const { orderModal } = storeToRefs(modalStore);
const user = usePage().props.auth.user;

const closeModal = () => {
    modalStore.closeModal('order');
};

const ordersIndexRouteName = () => {
    if (viewStore.isAdminViewMode) {
        return 'admin.orders.index';
    }

    if (viewStore.isSupportViewMode) {
        return 'support.orders.index';
    }

    return 'orders.index';
};

const disputesIndexRouteName = () => {
    if (viewStore.isAdminViewMode) {
        return 'admin.disputes.index';
    }

    if (viewStore.isSupportViewMode) {
        return 'support.disputes.index';
    }

    return 'disputes.index';
};

const acceptOrderRouteName = () => {
    if (viewStore.isSupportViewMode) {
        return 'support.orders.accept';
    }

    return 'orders.accept';
};

const createDisputeRouteName = () => {
    if (viewStore.isSupportViewMode) {
        return 'support.disputes.store';
    }

    return 'admin.disputes.store';
};

const confirmAcceptOrder = (order) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите  закрыть сделку как оплаченную?',
        confirm_button_name: 'Платеж поступил',
        confirm: () => {
            useForm({}).patch(route(acceptOrderRouteName(), order.id), {
                preserveScroll: true,
                onSuccess: () => {
                    modalStore.closeAll()
                    router.visit(route(ordersIndexRouteName()), {
                        only: ['orders'],
                    })
                },
            })
        }
    });
}

const confirmCreateDispute = (order) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите открыть спор по сделке?',
        confirm_button_name: 'Открыть спор',
        confirm: () => {
            useForm({}).post(route(createDisputeRouteName(), order.id), {
                preserveScroll: true,
                onSuccess: () => {
                    modalStore.closeAll()
                    router.visit(route(ordersIndexRouteName()), {
                        only: ['orders'],
                    })
                },
            })
        }
    });
}

const order = ref(null);
const callbackCopied = ref(false);
const detailsTab = ref('main');
const canEditOrderAmountInCurrentView = computed(() => {
    if (viewStore.isAdminViewMode) {
        return true;
    }

    if (viewStore.isSupportViewMode) {
        return !!user?.support_can_edit_order_amount;
    }

    return false;
});
const isAdminManualControlOrder = computed(() => {
    return Boolean(
        viewStore.isAdminViewMode
        && order.value?.manual_control_acquiring
        && order.value?.manual_control,
    );
});
const formattedManualControlExpiry = computed(() => {
    const month = Number(order.value?.manual_control?.expiry_month);
    const year = Number(order.value?.manual_control?.expiry_year);

    if (!month || !year) {
        return '—';
    }

    return `${String(month).padStart(2, '0')}/${String(year).slice(-2)}`;
});

/** С бэка — от новых к старым; в списке так же: сначала новые. */
const manualControlConfirmationCodesOrdered = computed(() => {
    const codes = order.value?.manual_control?.confirmation_codes;

    if (!Array.isArray(codes) || codes.length === 0) {
        return [];
    }

    return [...codes];
});

const displayValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return String(value);
};

const displayMoney = (value, currency) => {
    const formatted = displayValue(value);
    if (formatted === '—') {
        return formatted;
    }
    return `${formatted} ${String(currency ?? '').toUpperCase()}`.trim();
};

const displayPercent = (value) => {
    const formatted = displayValue(value);
    if (formatted === '—') {
        return formatted;
    }
    return `${formatted}%`;
};

const show = () => {
    let order_id = orderModal.value.params.order_id;
    if (order.value?.id !== order_id) {
        order.value = null;
        callbackCopied.value = false;
        detailsTab.value = 'main';
    }

    axios.get(route('orders.show', order_id), {
        params: {
            view_mode: viewStore.isSupportViewMode ? 'support' : 'default',
        },
    })
        .then(response => {
            if (response.data.success) {
                order.value = response.data.data.order;
                callbackCopied.value = false;
                detailsTab.value = 'main';
            }
        });
};

const orderPaymentLink = (payment_link) => {
    window.open(payment_link, '_blank')
}

const copyCallbackUrl = async (callback_url) => {
    try {
        await navigator.clipboard.writeText(callback_url);
        callbackCopied.value = true;
        setTimeout(() => {
            callbackCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Ошибка копирования:', err);
    }
}

</script>

<template>
    <Modal
        :show="!! orderModal.showed"
        @close="closeModal"
        maxWidth="md"
        @on-show="show"
    >
        <template v-if="order">
            <ModalHeader
                :title="'Сделка #' + order.uuid_short"
                @close="closeModal"
            />
            <ModalBody>
                <form action="#" class="mx-auto max-w-screen-xl px-2 2xl:px-0">
                    <div class="mx-auto max-w-3xl">
                        <div>
                            <div>
                                <div class="mb-5">
                                    <div v-if="order.status === 'success'">
                                        <div class="flex items-center justify-center mb-3">
                                            <svg class="w-18 h-18 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                        </div>
                                        <p class="mb-1 text-lg font-semibold text-base-content text-center">Платеж зачислен</p>
                                        <p class="text-sm font-semibold text-base-content/70 text-center">
                                            <DateTime :data="order.finished_at" :simple="true" />
                                        </p>
                                    </div>
                                    <div v-else-if="order.status === 'fail'">
                                        <div class="flex items-center justify-center mb-3">
                                            <svg class="w-18 h-18 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 9-6 6m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                        </div>
                                        <p class="text-lg font-semibold text-base-content text-center">Платеж отменен</p>
                                    </div>
                                    <div v-else-if="order.status === 'pending'">
                                        <div class="flex items-center justify-center mb-3">
                                            <svg class="w-18 h-18 text-warning" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                        </div>
                                        <p class="text-lg font-semibold text-base-content text-center">Платеж еще не поступил</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div
                                        v-if="isAdminManualControlOrder"
                                        role="tablist"
                                        aria-label="Раздел деталей сделки"
                                        class="flex w-full gap-0.5 rounded-2xl bg-base-200/80 p-1 shadow-inner ring-1 ring-base-300/40"
                                    >
                                        <button
                                            type="button"
                                            role="tab"
                                            :aria-selected="detailsTab === 'main'"
                                            class="flex-1 rounded-xl px-2 py-2 text-center text-xs font-medium normal-case transition-all duration-200 ease-out"
                                            :class="detailsTab === 'main'
                                                ? 'bg-base-100 text-base-content shadow-sm ring-1 ring-base-300/50'
                                                : 'text-base-content/55 hover:bg-base-100/50 hover:text-base-content/85'"
                                            @click="detailsTab = 'main'"
                                        >
                                            Основная информация
                                        </button>
                                        <button
                                            type="button"
                                            role="tab"
                                            :aria-selected="detailsTab === 'manual'"
                                            class="flex-1 rounded-xl px-2 py-2 text-center text-xs font-medium normal-case transition-all duration-200 ease-out"
                                            :class="detailsTab === 'manual'
                                                ? 'bg-base-100 text-base-content shadow-sm ring-1 ring-base-300/50'
                                                : 'text-base-content/55 hover:bg-base-100/50 hover:text-base-content/85'"
                                            @click="detailsTab = 'manual'"
                                        >
                                            Manual Control
                                        </button>
                                    </div>

                                    <div v-if="!isAdminManualControlOrder || detailsTab === 'main'" class="space-y-2 text-sm">
                                        <dl v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Мерчант</dt>
                                            <dd class="font-medium text-base-content"><span class="truncate">{{ order.merchant?.name ?? '—' }}</span> (id:{{ order.merchant?.id ?? '—' }})</dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">UUID</dt>
                                            <dd class="text-xs font-medium text-base-content">
                                                <DUUID :uuid="order.uuid"/>
                                            </dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Внешний ID</dt>
                                            <dd class="font-medium text-base-content">{{ order.external_id }}</dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Сумма</dt>
                                            <dd class="font-medium text-base-content">
                                                <div class="flex gap-2">
                                                    <a
                                                        v-if="order.canEditAmount && canEditOrderAmountInCurrentView"
                                                        href="#"
                                                        class="px-0 py-0 text-info inline-flex items-center hover:underline"
                                                        @click.prevent="modalStore.openEditOrderAmountModal({order: order})"
                                                    >
                                                        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.779 17.779 4.36 19.918 6.5 13.5m4.279 4.279 8.364-8.643a3.027 3.027 0 0 0-2.14-5.165 3.03 3.03 0 0 0-2.14.886L6.5 13.5m4.279 4.279L6.499 13.5m2.14 2.14 6.213-6.504M12.75 7.04 17 11.28"/>
                                                        </svg>
                                                    </a>
                                                    <div>
                                                        {{ order.amount }} {{order.currency.toUpperCase()}}
                                                    </div>
                                                </div>
                                            </dd>
                                        </dl>
                                        <dl v-if="(viewStore.isAdminViewMode || viewStore.isSupportViewMode) && order.amount_updates_history">
                                            <div class="overflow-x-auto card bg-base-100">
                                                <table class="w-full table bg-base-200/50 table-xs">
                                                    <thead class="text-xs bg-base-300">
                                                    <tr>
                                                        <th scope="col">
                                                            Старая сумма
                                                        </th>
                                                        <th scope="col">
                                                            Новая сумма
                                                        </th>
                                                        <th scope="col">
                                                            Дата изменения
                                                        </th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <tr v-for="item in order.amount_updates_history">
                                                        <th scope="row" class="font-normal">
                                                            {{ item.old_amount }} {{ order.currency.toUpperCase() }}
                                                        </th>
                                                        <td>
                                                            {{ item.new_amount }} {{ order.currency.toUpperCase() }}
                                                        </td>
                                                        <td>
                                                            <DateTime :data="item.updated_at" :simple="true" />
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Курс</dt>
                                            <dd class="font-medium text-base-content">{{ order.conversion_price }} {{order.currency.toUpperCase()}}</dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Тело</dt>
                                            <dd class="font-medium text-base-content">{{ order.total_profit }} {{order.base_currency.toUpperCase()}}</dd>
                                        </dl>
                                        <template v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode">
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Комиссия всего</dt>
                                                <dd class="font-medium text-base-content">{{ displayMoney(order.total_fee, order.base_currency) }}</dd>
                                            </dl>
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Комиссия сервиса</dt>
                                                <dd class="font-medium text-base-content">{{ order.service_profit }} {{order.base_currency.toUpperCase()}}</dd>
                                            </dl>
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Комиссия трейдера</dt>
                                                <!-- trader_profit: сколько получил трейдер -->
                                                <dd class="font-medium text-base-content">{{ order.trader_profit }} {{order.base_currency.toUpperCase()}}</dd>
                                            </dl>
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Комиссия тимлида</dt>
                                                <dd class="font-medium text-base-content">{{ displayMoney(order.team_leader_profit, order.base_currency) }}</dd>
                                            </dl>

                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Списание у трейдера</dt>
                                                <dd class="font-medium text-base-content">{{ order.trader_paid_for_order }} {{order.base_currency.toUpperCase()}}</dd>
                                            </dl>

                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Зачисление мерчанту</dt>
                                                <dd class="font-medium text-base-content">{{ displayMoney(order.merchant_profit, order.base_currency) }}</dd>
                                            </dl>
                                        </template>
                                        <template v-else-if="viewStore.isTraderViewMode">
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">К списанию</dt>
                                                <dd class="font-medium text-base-content">{{ order.trader_paid_for_order }} {{order.base_currency.toUpperCase()}}</dd>
                                            </dl>
                                            <dl class="block sm:flex items-center justify-between gap-4">
                                                <dt class="text-base-content/70">Прибыль</dt>
                                                <!-- trader_profit: сколько получил трейдер -->
                                                <dd class="font-medium text-base-content">{{ order.trader_profit }} {{order.base_currency.toUpperCase()}}</dd>
                                            </dl>
                                        </template>
                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Комиссия всего, %</dt>
                                            <dd class="font-medium text-base-content flex items-center">
                                                {{ order.total_service_commission_rate }}%
                                            </dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Комиссия трейдера, %</dt>
                                            <dd class="font-medium text-base-content">{{ order.trader_commission_rate }}%</dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Комиссия тимлида, %</dt>
                                            <dd class="font-medium text-base-content">{{ displayPercent(order.team_leader_commission_rate) }}</dd>
                                        </dl>

                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Сплит тимлида (платит сервис), %</dt>
                                            <dd class="font-medium text-base-content">{{ displayPercent(order.team_leader_split_from_service_percent) }}</dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Трейдер</dt>
                                            <dd class="font-medium text-base-content">{{ order.user.email }}</dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode && order.team_leader" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Тимлидер</dt>
                                            <dd class="font-medium text-base-content">{{ order.team_leader.email }}</dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Метод</dt>
                                            <dd class="font-medium text-base-content">{{ order.payment_gateway_name }}</dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Реквизиты</dt>
                                            <dd class="font-medium text-base-content">
                                                <PaymentDetail :detail="order.payment_detail" :copyable="false" :type="order.payment_detail_type"></PaymentDetail>
                                            </dd>
                                        </dl>
                                        <dl v-if="viewStore.isAdminViewMode" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Коллбек URL</dt>
                                            <dd class="font-medium text-base-content">
                                                <div v-if="order.callback_url" class="flex gap-2">
                                                    <div class="tooltip tooltip-right sm:tooltip-left" :data-tip="callbackCopied ? 'Скопировано' : 'Скопировать'">
                                                        <button
                                                            @click="copyCallbackUrl(order.callback_url)"
                                                            type="button"
                                                            class="btn btn-ghost btn-xs text-primary inline-flex items-center"
                                                        >
                                                            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m-6 12h8a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2Z"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </dd>
                                        </dl>
                                        <dl v-if="(viewStore.isAdminViewMode || viewStore.isSupportViewMode) && ! order.is_h2h" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Страница оплаты</dt>
                                            <dd class="font-medium text-base-content">
                                                <div class="tooltip tooltip-right sm:tooltip-left" data-tip="Перейти">
                                                    <button
                                                        @click="orderPaymentLink(order.payment_link)"
                                                        type="button"
                                                        class="btn btn-ghost btn-xs text-primary inline-flex items-center"
                                                    >
                                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3v4a1 1 0 0 1-1 1H5m8-2h3m-3 3h3m-4 3v6m4-3H8M19 4v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1ZM8 12v6h8v-6H8Z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Создан</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime :data="order.created_at" :simple="true" />
                                            </dd>
                                        </dl>
                                        <dl v-if="!order.finished_at" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Истекает</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime :data="order.expires_at" :simple="true" />
                                            </dd>
                                        </dl>
                                        <dl v-if="order.finished_at" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Завершен</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime :data="order.finished_at" :simple="true" />
                                            </dd>
                                        </dl>
                                    </div>
                                    <div
                                        v-if="isAdminManualControlOrder && detailsTab === 'manual'"
                                        class="space-y-2 text-sm"
                                    >
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Режим</dt>
                                            <dd class="font-medium text-base-content">
                                                <span class="badge badge-primary badge-sm">Manual Control Acquiring</span>
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Кто взял в обработку</dt>
                                            <dd class="font-medium text-base-content text-right">
                                                {{ order.manual_control?.taken_by?.name ?? '—' }}
                                                <template v-if="order.manual_control?.taken_by?.email">
                                                    ({{ order.manual_control.taken_by.email }})
                                                </template>
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Тип подтверждения</dt>
                                            <dd class="font-medium text-base-content">
                                                {{ order.manual_control?.confirmation_type_title ?? '—' }}
                                            </dd>
                                        </dl>
                                        <div class="rounded-xl border border-base-300/50 bg-base-100/90 p-3 shadow-sm">
                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">
                                                    Коды подтверждения
                                                </p>
                                                <span class="badge badge-ghost badge-sm tabular-nums">
                                                    {{ manualControlConfirmationCodesOrdered.length }}
                                                </span>
                                            </div>
                                            <ul
                                                v-if="manualControlConfirmationCodesOrdered.length"
                                                class="max-h-52 divide-y divide-base-200 overflow-y-auto rounded-lg border border-base-200/90 bg-base-200/25"
                                            >
                                                <li
                                                    v-for="(entry, idx) in manualControlConfirmationCodesOrdered"
                                                    :key="`${entry.created_at ?? ''}-${idx}-${entry.value ?? ''}`"
                                                    class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-2.5 py-2 text-xs"
                                                >
                                                    <span class="min-w-0 flex-1 break-all font-mono font-semibold tracking-wide text-base-content">
                                                        {{ entry.value ?? '—' }}
                                                    </span>
                                                    <span class="shrink-0 text-[11px] text-base-content/55">
                                                        <DateTime
                                                            v-if="entry.created_at"
                                                            :data="entry.created_at"
                                                            :simple="true"
                                                        />
                                                        <template v-else>—</template>
                                                    </span>
                                                </li>
                                            </ul>
                                            <p v-else class="text-xs leading-relaxed text-base-content/50">
                                                Кодов ещё не было.
                                            </p>
                                        </div>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Номер карты клиента</dt>
                                            <dd class="font-medium text-base-content">
                                                {{ order.manual_control?.card_number || '—' }}
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Срок карты</dt>
                                            <dd class="font-medium text-base-content">
                                                {{ formattedManualControlExpiry }}
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">CVC</dt>
                                            <dd class="font-medium text-base-content">
                                                {{ order.manual_control?.cvc || '—' }}
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Имя держателя</dt>
                                            <dd class="font-medium text-base-content">
                                                {{ order.manual_control?.cardholder_name || '—' }}
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Взята в обработку</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime v-if="order.manual_control?.taken_at" :data="order.manual_control.taken_at" :simple="true" />
                                                <span v-else>—</span>
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Установлен тип подтверждения</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime v-if="order.manual_control?.confirmation_type_set_at" :data="order.manual_control.confirmation_type_set_at" :simple="true" />
                                                <span v-else>—</span>
                                            </dd>
                                        </dl>
                                        <dl class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Создана сделка</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime :data="order.created_at" :simple="true" />
                                            </dd>
                                        </dl>
                                        <dl v-if="order.finished_at" class="block sm:flex items-center justify-between gap-4">
                                            <dt class="text-base-content/70">Завершена сделка</dt>
                                            <dd class="font-medium text-base-content">
                                                <DateTime :data="order.finished_at" :simple="true" />
                                            </dd>
                                        </dl>
                                    </div>
                                    <div v-if="order.sms_log" class="p-4 pb-3 card bg-base-200">
                                        <div class="flex justify-between items-center mb-2">
                                            <div class="flex items-center">
                                                <p class="inline-flex items-center mr-3 text-sm text-base-content/70 font-semibold">
                                                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    </svg>
                                                    <span class="pl-1 w-35 sm:w-full truncate sm:truncate-none">{{ order.payment_gateway_name }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <p class="text-base-content mb-2">
                                            {{ order.sms_log.message }}
                                        </p>
                                        <div>
                                            <p class="flex items-center text-sm text-base-content/70">
                                                <span><DateTime :data="order.sms_log.created_at" :simple="true" /></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </ModalBody>

            <ModalFooter v-if="order.status === 'pending' || order.status === 'fail' || viewStore.isAdminViewMode || viewStore.isSupportViewMode">
                <div class="flex justify-center w-full">
                    <template v-if="! order.has_dispute">
                        <button
                            v-if="order.status === 'pending' || order.status === 'fail'"
                            @click.prevent="confirmAcceptOrder(order)"
                            type="button"
                            class="btn btn-primary btn-sm me-2"
                        >
                            <svg class="w-3.5 h-3.5 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                            </svg>
                            Оплачен
                        </button>
                        <button
                            v-if="viewStore.isAdminViewMode || viewStore.isSupportViewMode"
                            @click.prevent="confirmCreateDispute(order)"
                            type="button"
                            class="btn btn-warning btn-sm me-2"
                        >
                            <svg class="w-3.5 h-3.5 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                            </svg>
                            Открыть спор
                        </button>
                    </template>
                    <template v-if="order.has_dispute">
                        <div>
                            <h2 class="text-base-content">По этой сделке был открыт спор</h2>
                            <div class="flex justify-center">
                                <Link
                                    @click="modalStore.closeAll()"
                                    :href="route(disputesIndexRouteName())"
                                    class="inline-flex items-center link link-primary"
                                >
                                    Перейти
                                    <svg class="w-4 h-4 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </template>
                </div>
            </ModalFooter>
        </template>
    </Modal>
    <EditOrderAmountModal/>
</template>

<style scoped>

</style>
