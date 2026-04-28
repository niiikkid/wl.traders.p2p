<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import OrderStatus from '@/Components/OrderStatus.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';

const cascadeDeals = ref(usePage().props.cascadeDeals);
const selectedDeal = ref(null);

router.on('success', () => {
    cascadeDeals.value = usePage().props.cascadeDeals;
});

const selectedDealJson = computed(() => {
    if (! selectedDeal.value) {
        return '';
    }

    return JSON.stringify(selectedDeal.value, null, 2);
});

const openDealModal = (deal) => {
    selectedDeal.value = deal;
};

const closeDealModal = () => {
    selectedDeal.value = null;
};

const formatCurrency = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return 'Пусто';
    }

    return `${amount} ${currency ?? ''}`.trim();
};

const getProviderName = (deal) => {
    return deal.selected_provider?.name ?? deal.selected_provider?.code ?? 'Не выбран';
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Каскад" />

        <MainTableSection
            title="Каскад"
            :data="cascadeDeals"
        >
            <template #button>
                <CascadeSectionNav active="deals" />
            </template>

            <template v-slot:body>
                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th scope="col">UUID</th>
                                <th scope="col">Внешний ID</th>
                                <th scope="col">Мерчант</th>
                                <th scope="col">Сумма</th>
                                <th scope="col">Метод</th>
                                <th scope="col">Интеграция</th>
                                <th scope="col">Статус</th>
                                <th scope="col">Создана</th>
                                <th scope="col">
                                    <span class="sr-only">Действия</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="deal in cascadeDeals.data"
                                :key="deal.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                            >
                                <th scope="row" class="font-medium whitespace-nowrap">
                                    <CopyableOrderUid :uuid="deal.uuid ?? ''"/>
                                </th>
                                <td class="text-nowrap text-base-content">
                                    <CopyableOrderUid :uuid="deal.external_id ?? ''"/>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ deal.merchant?.name ?? 'Пусто' }}</div>
                                    <div v-if="deal.merchant_client?.external_id" class="flex flex-wrap items-center gap-1.5 text-nowrap text-xs opacity-70">
                                        <span>Клиент:</span>
                                        <CopyableOrderUid :uuid="deal.merchant_client.external_id ?? ''"/>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">
                                        {{ deal.amount }} {{ (deal.currency ?? '').toUpperCase() }}
                                    </div>
                                    <div class="text-nowrap text-xs opacity-70">
                                        {{ deal.service_profit ?? 0 }} {{ (deal.base_currency ?? '').toUpperCase() }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ deal.payment_method_name ?? deal.payment_method ?? 'Пусто' }}</div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-base-content">{{ getProviderName(deal) }}</div>
                                    <div class="text-nowrap text-xs opacity-70">
                                        Попыток: {{ deal.transactions_count ?? 0 }}
                                    </div>
                                </td>
                                <td>
                                    <OrderStatus :status="deal.status" :status_name="deal.status_name"/>
                                    <div v-if="deal.sub_status" class="text-nowrap text-xs opacity-70">
                                        {{ deal.sub_status_name ?? deal.sub_status }}
                                    </div>
                                </td>
                                <td>
                                    <DateTime class="justify-start" :data="deal.created_at"/>
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-outline btn-xs"
                                        aria-label="Открыть каскадную сделку"
                                        @click.prevent="openDealModal(deal)"
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

                <div class="xl:hidden space-y-3">
                    <div
                        v-for="deal in cascadeDeals.data"
                        :key="deal.id"
                        class="card bg-base-100 shadow-sm"
                    >
                        <div class="card-body p-4 gap-3">
                            <div class="flex items-start justify-between gap-3 border-b border-base-content/10 pb-2">
                                <div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-sm text-base-content/70">UUID:</span>
                                        <CopyableOrderUid :uuid="deal.uuid ?? ''"/>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs text-base-content/70">
                                        <span>External ID:</span>
                                        <CopyableOrderUid :uuid="deal.external_id ?? ''"/>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-0.5">
                                    <OrderStatus :status="deal.status" :status_name="deal.status_name"/>
                                    <span v-if="deal.sub_status" class="text-right text-xs text-base-content/70 text-nowrap max-w-[12rem] truncate" :title="deal.sub_status_name ?? deal.sub_status">
                                        {{ deal.sub_status_name ?? deal.sub_status }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="col-span-2">
                                    <div class="text-base-content/60">Сумма</div>
                                    <div class="text-nowrap text-base-content">{{ deal.amount }} {{ (deal.currency ?? '').toUpperCase() }}</div>
                                    <div class="text-nowrap text-xs opacity-70">{{ deal.service_profit ?? 0 }} {{ (deal.base_currency ?? '').toUpperCase() }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Метод</div>
                                    <div class="font-medium">{{ deal.payment_method_name ?? deal.payment_method ?? 'Пусто' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Интеграция</div>
                                    <div class="font-medium">{{ getProviderName(deal) }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <DateTime class="justify-start text-xs" :data="deal.created_at"/>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-outline btn-xs"
                                    @click.prevent="openDealModal(deal)"
                                >
                                    Подробнее
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <dialog :open="Boolean(selectedDeal)" class="modal">
            <div class="modal-box max-w-4xl">
                <form method="dialog">
                    <button
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        @click="closeDealModal"
                    >
                        ✕
                    </button>
                </form>

                <template v-if="selectedDeal">
                    <h3 class="font-bold text-lg mb-4">Каскадная сделка</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Основное</h4>
                                <div class="text-sm space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">UUID:</span>
                                        <CopyableOrderUid :uuid="selectedDeal.uuid ?? ''"/>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="shrink-0">External ID:</span>
                                        <CopyableOrderUid :uuid="selectedDeal.external_id ?? ''"/>
                                    </div>
                                    <div>Мерчант: {{ selectedDeal.merchant?.name ?? 'Пусто' }}</div>
                                    <div>Статус: {{ selectedDeal.status_name ?? selectedDeal.status }}</div>
                                    <div>Подстатус: {{ selectedDeal.sub_status_name ?? selectedDeal.sub_status ?? 'Пусто' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Экономика</h4>
                                <div class="text-sm space-y-1">
                                    <div>Сумма: {{ formatCurrency(selectedDeal.amount, selectedDeal.currency) }}</div>
                                    <div>Initial: {{ formatCurrency(selectedDeal.initial_amount, selectedDeal.currency) }}</div>
                                    <div>USDT amount: {{ formatCurrency(selectedDeal.usdt_amount, selectedDeal.base_currency) }}</div>
                                    <div>Fee: {{ formatCurrency(selectedDeal.fee, selectedDeal.base_currency) }}</div>
                                    <div>Profit: {{ formatCurrency(selectedDeal.service_profit, selectedDeal.base_currency) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Платёжные данные</h4>
                                <div class="text-sm space-y-1">
                                    <div>Метод: {{ selectedDeal.payment_method_name ?? selectedDeal.payment_method ?? 'Пусто' }}</div>
                                    <div>Callback: {{ selectedDeal.callback_url ?? 'Пусто' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h4 class="font-semibold">Интеграция</h4>
                                <div class="text-sm space-y-1">
                                    <div>Выбран: {{ getProviderName(selectedDeal) }}</div>
                                    <div>Provider deal ID: {{ selectedDeal.selected_transaction?.provider_deal_id ?? 'Пусто' }}</div>
                                    <div>Статус транзакции: {{ selectedDeal.selected_transaction?.status_name ?? selectedDeal.selected_transaction?.status ?? 'Пусто' }}</div>
                                    <div>Попыток: {{ selectedDeal.transactions_count ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="collapse collapse-arrow bg-base-200 mt-4">
                        <input type="checkbox" />
                        <div class="collapse-title font-semibold">Raw данные</div>
                        <div class="collapse-content">
                            <pre class="text-xs whitespace-pre-wrap break-all">{{ selectedDealJson }}</pre>
                        </div>
                    </div>
                </template>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeDealModal">close</button>
            </form>
        </dialog>
    </div>
</template>
