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
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";
import MoneyValue from "@/Components/MoneyValue.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

const viewStore = useViewStore();
const modalStore = useModalStore();

const disputes = usePage().props.disputes;
const oldestDisputeCreatedAt = usePage().props.oldestDisputeCreatedAt;

const confirmAcceptDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите принять спор #' + dispute?.id + '?',
        body: 'В таком случае, сделка будет закрыта как оплаченная.',
        confirm_button_name: 'Принять спор',
        confirm: () => {
            useForm({}).patch(route('disputes.accept', dispute.uuid), {
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
            useForm({}).patch(route('disputes.rollback', dispute.uuid), {
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
                        <DataTable>
                            <template #head>
                                <th scope="col">ID</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Реквизит</th>
                                <th scope="col" v-if="viewStore.isAdminViewMode">Трейдер</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Создан</th>
                                <th scope="col"><span class="sr-only">Действия</span></th>
                            </template>
                                    <tr v-for="dispute in disputes.data" class="bg-base-100 border-b last:border-none border-base-200">
                                        <th scope="row" class="font-medium whitespace-nowrap text-base-content">
                                            {{ dispute.id }}
                                        </th>
                                        <td>
                                            <MoneyValue :value="dispute.order.amount" :currency="dispute.order.currency" block />
                                            <MoneyValue
                                                v-if="viewStore.isAdminViewMode"
                                                :value="dispute.order.total_profit"
                                                :currency="dispute.order.base_currency"
                                                secondary
                                                block
                                            />
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <GatewayLogo :img_path="dispute.payment_gateway.logo_path" :name="dispute.payment_gateway.name" class="w-10 h-10 text-base-content/50"/>
                                                <PaymentDetail
                                                    :detail="dispute.payment_detail.detail"
                                                    :type="dispute.payment_detail.type"
                                                    :name="dispute.payment_detail.name"
                                                ></PaymentDetail>
                                            </div>
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
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <button
                                                    v-if="dispute.receipt_url"
                                                    type="button"
                                                    class="btn btn-square btn-xs btn-outline btn-info"
                                                    @click.prevent="openDisputeReceipt(dispute.receipt_url)"
                                                    aria-label="Квитанция"
                                                >
                                                    <svg
                                                        class="h-3.5 w-3.5 shrink-0"
                                                        aria-hidden="true"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            d="M9 11H15M9 7H13M9 15H15M5 6.2V21L7.5 19L10 21L12 19L14 21L16.5 19L19 21V6.2C19 5.0799 19 4.51984 18.782 4.09202C18.5903 3.71569 18.2843 3.40973 17.908 3.21799C17.4802 3 16.9201 3 15.8 3H8.2C7.0799 3 6.51984 3 6.09202 3.21799C5.71569 3.40973 5.40973 3.71569 5.21799 4.09202C5 4.51984 5 5.0799 5 6.2Z"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                        />
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
                                            </div>
                                        </td>
                                    </tr>
                        </DataTable>

                        <!-- Mobile view (cards list) -->
                        <DataCardList>
                            <DataCard
                                v-for="dispute in disputes.data"
                                :key="dispute.id"
                            >
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
                                                <MoneyValue :value="dispute.order.amount" :currency="dispute.order.currency" block />
                                                <MoneyValue
                                                    v-if="viewStore.isAdminViewMode"
                                                    :value="dispute.order.total_profit"
                                                    :currency="dispute.order.base_currency"
                                                    secondary
                                                    block
                                                />
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
                                                    <svg
                                                        class="h-3.5 w-3.5"
                                                        aria-hidden="true"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            d="M9 11H15M9 7H13M9 15H15M5 6.2V21L7.5 19L10 21L12 19L14 21L16.5 19L19 21V6.2C19 5.0799 19 4.51984 18.782 4.09202C18.5903 3.71569 18.2843 3.40973 17.908 3.21799C17.4802 3 16.9201 3 15.8 3H8.2C7.0799 3 6.51984 3 6.09202 3.21799C5.71569 3.40973 5.40973 3.71569 5.21799 4.09202C5 4.51984 5 5.0799 5 6.2Z"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                        />
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
                                                    <MoneyValue :value="dispute.order.amount" :currency="dispute.order.currency" compact />
                                                    <MoneyValue
                                                        v-if="viewStore.isAdminViewMode"
                                                        :value="dispute.order.total_profit"
                                                        :currency="dispute.order.base_currency"
                                                        secondary
                                                    />
                                                </div>
                                                <div class="inline-flex shrink-0 items-center gap-2">
                                                    <button
                                                        v-if="dispute.receipt_url"
                                                        type="button"
                                                        class="btn btn-square btn-xs btn-outline btn-info"
                                                        @click.prevent="openDisputeReceipt(dispute.receipt_url)"
                                                        aria-label="Квитанция"
                                                    >
                                                        <svg
                                                            class="h-3.5 w-3.5"
                                                            aria-hidden="true"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                d="M9 11H15M9 7H13M9 15H15M5 6.2V21L7.5 19L10 21L12 19L14 21L16.5 19L19 21V6.2C19 5.0799 19 4.51984 18.782 4.09202C18.5903 3.71569 18.2843 3.40973 17.908 3.21799C17.4802 3 16.9201 3 15.8 3H8.2C7.0799 3 6.51984 3 6.09202 3.21799C5.71569 3.40973 5.40973 3.71569 5.21799 4.09202C5 4.51984 5 5.0799 5 6.2Z"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                            />
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
                            </DataCard>
                        </DataCardList>
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
