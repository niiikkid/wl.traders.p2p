<script setup>
import {Head, router, useForm, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import DisputeStatus from "@/Components/DisputeStatus.vue";
import {useModalStore} from "@/store/modal.js";
import DisputeModal from "@/Modals/DisputeModal.vue";
import CancelDisputeModal from "@/Modals/CancelDisputeModal.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import {useViewStore} from "@/store/view.js";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import {ref, watch} from "vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const disputes = usePage().props.disputes;
const oldestDisputeCreatedAt = usePage().props.oldestDisputeCreatedAt;

const displayShortDetail = ref(getCookieValue('displayShortDetail', true));

function getCookieValue(name, defaultValue) {
    const currentRoute = route().current();
    const cookieName = `${name}_${currentRoute}`;
    const match = document.cookie.match(new RegExp('(^| )' + cookieName + '=([^;]+)'));
    return match ? match[2] === 'true' : defaultValue;
}

function updateDisplayShortDetailCookie() {
    const currentRoute = route().current();
    const cookieName = `displayShortDetail_${currentRoute}`;
    document.cookie = `${cookieName}=${displayShortDetail.value}; path=/; max-age=31536000`; // 1 год
}

// Следим за изменениями и обновляем cookie
watch(displayShortDetail, () => {
    updateDisplayShortDetailCookie();
});

const confirmAcceptDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите принять спор #' + dispute?.id + '?',
        body: 'В таком случае, сделка будет закрыта как оплаченная.',
        confirm_button_name: 'Принять спор',
        confirm: () => {
            useForm({}).patch(route('disputes.accept', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route(viewStore.adminPrefix + 'disputes.index'), {
                        only: ['disputes'],
                    })
                },
            });
        }
    });
}

const openDisputeReceipt = (receipt_url) => {
    if (!receipt_url) {
        return;
    }
    window.open(receipt_url, '_blank')?.focus();
};

const confirmRollbackDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите открыть спор #' + dispute?.id + '?',
        body: 'Референтная сделка не изменит свой статус.',
        confirm_button_name: 'Открыть спор',
        confirm: () => {
            useForm({}).patch(route('disputes.rollback', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route(viewStore.adminPrefix + 'disputes.index'), {
                        only: ['disputes'],
                    })
                },
            });
        }
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Споры" />

        <MainTableSection
            title="Споры по сделкам"
            :data="disputes"
        >
            <template v-slot:header>
                <div>
                    <FiltersPanel name="orders">
                        <InputFilter
                            name="uuid"
                            placeholder="UUID"
                        />
                        <InputFilter
                            name="externalID"
                            placeholder="Внешний ID"
                        />
                        <InputFilter
                            name="amount"
                            placeholder="Сумма"
                        />
                        <InputFilter
                            name="paymentDetail"
                            placeholder="Реквизит"
                        />
                        <InputFilter
                            v-if="viewStore.isAdminViewMode"
                            name="user"
                            placeholder="Пользователь"
                        />
                        <DropdownFilter
                            name="disputeStatuses"
                            title="Статусы"
                        />
                    </FiltersPanel>
                </div>
            </template>
            <template v-slot:body>
                <div v-if="viewStore.isAdminViewMode && oldestDisputeCreatedAt" class="flex gap-5">
                    <div class="flex text-sm text-base-content/70 mb-3 gap-3">
                        <div>Самый старый:</div>
                        <div>
                            <DateTime :data="oldestDisputeCreatedAt" :plural="true"></DateTime>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <div class="relative">
                        <!-- Desktop/tablet view (table) -->
                        <div class="hidden xl:block shadow-md rounded-table relative">
                            <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Сумма</th>
                                        <th scope="col" class="flex items-center">
                                            Реквизит
                                            <div class="inline-flex items-center ml-2">
                                                <label class="swap swap-rotate cursor-pointer inline-grid place-items-center w-6 h-6">
                                                    <input type="checkbox" v-model="displayShortDetail" class="sr-only" />
                                                    <svg class="swap-on w-5 h-5 text-base-content/70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                    </svg>
                                                    <svg class="swap-off w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                </label>
                                            </div>
                                        </th>
                                        <th scope="col" class="text-nowrap">Сделка</th>
                                        <th scope="col" v-if="viewStore.isAdminViewMode">Трейдер</th>
                                        <th scope="col">Статус</th>
                                        <th scope="col">Создан</th>
                                        <th scope="col"><span class="sr-only">Действия</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="dispute in disputes.data" class="bg-base-100 border-b last:border-none border-base-200">
                                        <th scope="row" class="font-medium whitespace-nowrap text-base-content">
                                            {{ dispute.id }}
                                        </th>
                                        <td>
                                            <div class="text-nowrap text-base-content">{{ dispute.order.amount }} {{dispute.order.currency.toUpperCase()}}</div>
                                            <div class="text-nowrap text-base-content/70 text-xs">{{ dispute.order.total_profit }} {{dispute.order.base_currency.toUpperCase()}}</div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <GatewayLogo :img_path="dispute.payment_gateway.logo_path" :name="dispute.payment_gateway.name" class="w-10 h-10 text-base-content/50"/>
                                                <PaymentDetail
                                                    :detail="dispute.payment_detail.detail"
                                                    :type="dispute.payment_detail.type"
                                                    :name="dispute.payment_detail.name"
                                                    :short="displayShortDetail"
                                                ></PaymentDetail>
                                            </div>
                                        </td>
                                        <td>
                                            <DisplayUUID :uuid="dispute.order.uuid"/>
                                        </td>
                                        <td v-if="viewStore.isAdminViewMode">
                                            <div class="flex items-center gap-1 text-nowrap">
                                                <svg class="w-5 h-5 text-info" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="1.5" d="M7 17v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3Zm8-9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                                <span class="text-base-content">{{ dispute.user.email }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <DisputeStatus :status="dispute.status"></DisputeStatus>
                                        </td>
                                        <td>
                                            <DateTime :data="dispute.created_at"></DateTime>
                                        </td>
                                        <td class="text-right flex justify-end gap-2">
                                            <button
                                                v-if="dispute.receipt_url"
                                                type="button"
                                                class="btn btn-xs btn-outline btn-info"
                                                @click.prevent="openDisputeReceipt(dispute.receipt_url)"
                                            >
                                                Квитанция
                                                <svg class="w-3 h-3 ml-2 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4"/>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-outline btn-xs"
                                                @click.prevent="modalStore.openDisputeModal({dispute})"
                                                aria-label="Открыть спор"
                                            >
                                                <svg class="w-3.5 h-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                    <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                            </button>
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
                                    v-for="dispute in disputes.data"
                                    :key="dispute.id"
                                    class="card bg-base-100 shadow-sm"
                                >
                                    <div class="card-body p-4 pt-2 pb-3">
                                        <!-- Компактная шапка: ID и дата -->
                                        <div class="flex justify-between items-center border-b border-base-content/10 p-1.5 mb-2">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="text-base-content/70">ID:</span>
                                                <span class="font-medium text-base-content">{{ dispute.id }}</span>
                                            </div>
                                            <div class="inline-flex items-center">
                                                <DateTime class="justify-start" :data="dispute.created_at"/>
                                            </div>
                                        </div>
                                        <!-- Для >= sm -->
                                        <div class="hidden sm:flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <GatewayLogo :img_path="dispute.payment_gateway.logo_path" :name="dispute.payment_gateway.name" class="w-10 h-10 text-base-content/50"/>
                                                <PaymentDetail
                                                    :detail="dispute.payment_detail.detail"
                                                    :type="dispute.payment_detail.type"
                                                    :name="dispute.payment_detail.name"
                                                    class="-mt-2"
                                                ></PaymentDetail>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-nowrap text-base-content">{{ dispute.order.amount }} {{ dispute.order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ dispute.order.total_profit }} {{ dispute.order.base_currency.toUpperCase() }}</div>
                                            </div>
                                            <div>
                                                <DisputeStatus :status="dispute.status"></DisputeStatus>
                                            </div>
                                            <div class="inline-flex shrink-0 items-center justify-end gap-2 w-15">
                                                <button
                                                    v-if="dispute.receipt_url"
                                                    type="button"
                                                    class="btn btn-square btn-xs btn-outline btn-info"
                                                    @click.prevent="openDisputeReceipt(dispute.receipt_url)"
                                                    aria-label="Квитанция"
                                                >
                                                    <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4"/>
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-square btn-primary btn-outline btn-xs"
                                                    @click.prevent="modalStore.openDisputeModal({dispute})"
                                                    aria-label="Открыть спор"
                                                >
                                                    <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                        <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                        <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Для xs -->
                                        <div class="sm:hidden">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <GatewayLogo :img_path="dispute.payment_gateway.logo_path" :name="dispute.payment_gateway.name" class="w-10 h-10 text-base-content/50"/>
                                                    <PaymentDetail
                                                        :detail="dispute.payment_detail.detail"
                                                        :type="dispute.payment_detail.type"
                                                        :name="dispute.payment_detail.name"
                                                        class="-mt-2"
                                                    ></PaymentDetail>
                                                </div>
                                       
                                                <div>
                                                    <DisputeStatus :status="dispute.status"></DisputeStatus>
                                                </div>
                                            </div>
                                            <div class="border-b border-base-content/10 my-2"></div>
                                            <div class="flex items-center justify-between">
                                                <div class="inline-flex gap-3">
                                                    <div class="text-nowrap text-xs text-base-content">
                                                        {{ dispute.order.amount }} {{ dispute.order.currency.toUpperCase() }}
                                                    </div>
                                                    <div class="text-nowrap text-xs opacity-70">
                                                        {{ dispute.order.total_profit }} {{ dispute.order.base_currency.toUpperCase() }}
                                                    </div>
                                                </div>
                                                <div class="inline-flex shrink-0 items-center gap-2">
                                                    <button
                                                        v-if="dispute.receipt_url"
                                                        type="button"
                                                        class="btn btn-square btn-xs btn-outline btn-info"
                                                        @click.prevent="openDisputeReceipt(dispute.receipt_url)"
                                                        aria-label="Квитанция"
                                                    >
                                                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4"/>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-square btn-primary btn-outline btn-xs"
                                                        @click.prevent="modalStore.openDisputeModal({dispute})"
                                                        aria-label="Открыть спор"
                                                    >
                                                        <svg class="h-3.5 w-3.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                            <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                            <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <DisputeModal
            @accept="confirmAcceptDispute"
            @cancel="modalStore.openDisputeCancelModal({dispute:$event})"
            @rollback="confirmRollbackDispute"
        />

        <CancelDisputeModal/>
        <ConfirmModal/>
    </div>
</template>

<style scoped>

</style>
