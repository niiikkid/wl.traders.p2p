<script setup>
import {Head, router, useForm, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import DisputeStatus from "@/Components/DisputeStatus.vue";
import {useModalStore} from "@/store/modal.js";
import DisputeModal from "@/Modals/DisputeModal.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import CancelDisputeModal from "@/Modals/CancelDisputeModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

const modalStore = useModalStore();

const disputes = usePage().props.disputes;
const oldestDisputeCreatedAt = usePage().props.oldestDisputeCreatedAt;

const confirmAcceptDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите принять спор #' + dispute?.id + '?',
        body: 'В таком случае, сделка будет закрыта как оплаченная.',
        confirm_button_name: 'Принять спор',
        confirm: () => {
            useForm({}).patch(route('support.disputes.accept', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route('support.disputes.index'), {
                        only: ['disputes'],
                    })
                },
            });
        }
    });
}

const confirmRollbackDispute = (dispute) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите открыть спор #' + dispute?.id + '?',
        body: 'Референтная сделка не изменит свой статус.',
        confirm_button_name: 'Открыть спор',
        confirm: () => {
            useForm({}).patch(route('support.disputes.rollback', dispute.id), {
                preserveScroll: true,
                onFinish: () => {
                    modalStore.closeAll()
                    router.visit(route('support.disputes.index'), {
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
            <template v-slot:table-filters>
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
                <div v-if="oldestDisputeCreatedAt" class="flex gap-5">
                    <div class="flex text-base text-base-content/70 mb-3 gap-3">
                        <div>Самый старый:</div>
                        <div>
                            <DateTime :data="oldestDisputeCreatedAt" :plural="true"></DateTime>
                        </div>
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
                                Реквизит
                            </th>
                            <th scope="col">
                                Сумма
                            </th>
                            <th scope="col">
                                Трейдер
                            </th>
                            <th scope="col">
                                Статус
                            </th>
                            <th scope="col">
                                Создан
                            </th>
                            <th scope="col" class=" flex justify-center">
                                <span class="sr-only">Действия</span>
                            </th>
                        </template>
                                    <tr v-for="dispute in disputes.data" class="bg-base-100 border-b last:border-none">
                                        <th scope="row" class=" font-medium whitespace-nowrap">
                                            {{ dispute.id }}
                                        </th>
                                        <td>
                                            <PaymentDetail
                                                :detail="dispute.payment_detail.detail"
                                                :type="dispute.payment_detail.type"
                                                :copyable="false"
                                                class=""
                                            ></PaymentDetail>
                                            <div class="text-nowrap text-xs">{{ dispute.payment_detail.name }}</div>
                                        </td>
                                        <td>
                                            <div class="text-nowrap">{{ dispute.order.amount }} {{dispute.order.currency.toUpperCase()}}</div>
                                            <div class="text-nowrap text-xs">{{ dispute.order.total_profit }} {{dispute.order.base_currency.toUpperCase()}}</div>
                                        </td>
                                        <td>
                                            {{ dispute.user.email }}
                                        </td>
                                        <td>
                                            <DisputeStatus :status="dispute.status"></DisputeStatus>
                                        </td>
                                        <td>
                                            <DateTime :data="dispute.created_at"></DateTime>
                                        </td>
                                        <td class="text-right">
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
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                        <DataCard
                            v-for="dispute in disputes.data"
                            :key="dispute.id"
                        >
                                    <!-- Шапка: ID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-0 pb-1">
                                        <div class="inline-flex gap-3">
                                            <div class="inline-flex items-center">
                                                <span class="text-base-content/70">ID:</span> <span class="font-medium ml-4">{{ dispute.id }}</span>
                                            </div>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="dispute.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Для экранов sm и больше -->
                                    <div class="hidden sm:block">
                                        <div class="flex items-center justify-between gap-1 py-1">
                                            <PaymentDetail
                                                :detail="dispute.payment_detail.detail"
                                                :type="dispute.payment_detail.type"
                                                :name="dispute.payment_detail.name"
                                                :copyable="false"
                                            ></PaymentDetail>
                                            <div>
                                                <div class="text-nowrap text-base-content">{{ dispute.order.amount }} {{ dispute.order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ dispute.order.total_profit }} {{ dispute.order.base_currency.toUpperCase() }}</div>
                                            </div>
                                            <div>
                                                <DisputeStatus :status="dispute.status"></DisputeStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-xs text-base-content/70">
                                                <span>Трейдер:</span> <span class="text-base-content">{{ dispute.user.email }}</span>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-square btn-primary btn-outline btn-xs"
                                                @click.prevent="modalStore.openDisputeModal({dispute})"
                                                aria-label="Открыть спор"
                                            >
                                                <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-width="2" d="M21 12c0 1.2-4.03 6-9 6s-9-4.8-9-6c0-1.2 4.03-6 9-6s9 4.8 9 6Z"/>
                                                    <path stroke="currentColor" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для экранов меньше sm -->
                                    <div class="sm:hidden">
                                        <div class="flex justify-between mb-2">
                                            <PaymentDetail
                                                :detail="dispute.payment_detail.detail"
                                                :type="dispute.payment_detail.type"
                                                :copyable="false"
                                                :name="dispute.payment_detail.name"
                                            ></PaymentDetail>
                                            <div>
                                                <div class="text-nowrap text-sm text-base-content">{{ dispute.order.amount }} {{ dispute.order.currency.toUpperCase() }}</div>
                                                <div class="text-nowrap text-xs opacity-70">{{ dispute.order.total_profit }} {{ dispute.order.base_currency.toUpperCase() }}</div>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-xs text-base-content/70 grid gap-1">
                                                <div>Трейдер:</div>
                                                <div class="text-base-content">
                                                    {{ dispute.user.email }}
                                                </div>
                                            </div>
                                            <div class="text-center">
                                                <DisputeStatus :status="dispute.status"></DisputeStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-end">
                                            <div>
                                                <button
                                                    type="button"
                                                    class="btn btn-square btn-primary btn-outline btn-xs"
                                                    @click.prevent="modalStore.openDisputeModal({dispute})"
                                                    aria-label="Открыть спор"
                                                >
                                                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
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
            </template>
        </MainTableSection>

        <DisputeModal
            @accept="confirmAcceptDispute"
            @cancel="modalStore.openDisputeCancelModal({dispute:$event})"
            @rollback="confirmRollbackDispute"
        />
        <CancelDisputeModal />
        <ConfirmModal />
    </div>
</template>
