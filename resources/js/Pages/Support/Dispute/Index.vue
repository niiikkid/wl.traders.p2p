<script setup>
import {Head, usePage} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import DisputeStatus from "@/Components/DisputeStatus.vue";
import {useModalStore} from "@/store/modal.js";
import DisputeModal from "@/Modals/DisputeModal.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import ShowAction from "@/Components/Table/ShowAction.vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";

const modalStore = useModalStore();

const disputes = usePage().props.disputes;
const oldestDisputeCreatedAt = usePage().props.oldestDisputeCreatedAt;

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
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col" class=" text-nowrap">
                                            Сделка
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="dispute in disputes.data" class="bg-base-100 border-b last:border-none">
                                        <th scope="row" class=" font-medium whitespace-nowrap">
                                            {{ dispute.id }}
                                        </th>
                                        <td>
                                            <DisplayUUID :uuid="dispute.order.uuid"/>
                                        </td>
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
                                        <td class=" text-right">
                                            <ShowAction @click="modalStore.openDisputeModal({dispute})"></ShowAction>
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
                                    <!-- Шапка: ID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-0 pb-1">
                                        <div class="inline-flex gap-3">
                                            <div class="inline-flex items-center">
                                                <span class="text-base-content/70">ID:</span> <span class="font-medium ml-4">{{ dispute.id }}</span>
                                            </div>
                                            <div class="hidden sm:inline-flex gap-1 items-center">
                                                <span class="text-base-content/70 text-xs">Сделка:</span>
                                                <DisplayUUID :uuid="dispute.order.uuid"/>
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
                                                class="btn btn-primary btn-xs"
                                                @click="modalStore.openDisputeModal({dispute})"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
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
                                        <div class="flex items-center justify-between">
                                            <div class="inline-flex items-center gap-1">
                                                <span class="text-base-content/70 text-xs">Сделка:</span>
                                                <DisplayUUID :uuid="dispute.order.uuid"/>
                                            </div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click="modalStore.openDisputeModal({dispute})"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
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
            </template>
        </MainTableSection>

        <DisputeModal />
        <ConfirmModal />
    </div>
</template>
