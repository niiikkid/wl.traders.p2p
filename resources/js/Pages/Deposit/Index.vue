<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePage } from '@inertiajs/vue3';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import InvoiceStatus from "@/Components/InvoiceStatus.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref} from "vue";
import DateTime from "@/Components/DateTime.vue";
import CopyAddress from "@/Components/CopyAddress.vue";

const invoices = usePage().props.invoices;

const expandedCards = ref({});
const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Депозиты средств" />

        <MainTableSection
            title="Депозиты средств"
            :data="invoices"
        >
            <template v-slot:header>
                <FiltersPanel name="deposits">
                    <DropdownFilter
                        name="invoiceStatuses"
                        title="Статусы"
                    />
                    <InputFilter
                        name="id"
                        placeholder="ID депозита"
                    />
                    <InputFilter
                        name="amount"
                        placeholder="Сумма"
                    />
                    <InputFilter
                        name="user"
                        placeholder="Пользователь"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col" class="text-nowrap">Transaction ID</th>
                                    <th scope="col">Сумма</th>
                                    <th scope="col">Пользователь</th>
                                    <th scope="col">txHash</th>
                                    <th scope="col">Статус</th>
                                    <th scope="col">Дата создания</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="invoice in invoices.data" class="bg-base-100 border-b last:border-none border-base-200">
                                    <th scope="row" class="font-medium whitespace-nowrap">
                                        {{ invoice.id }}
                                    </th>
                                    <td>
                                        {{ invoice.transaction_id }}
                                    </td>
                                    <td>
                                        <div class="text-nowrap">{{ invoice.amount }} {{invoice.currency.toUpperCase()}}</div>
                                        <div v-show="invoice.balance_type === 'trust'" class="text-xs opacity-70">Траст</div>
                                        <div v-show="invoice.balance_type === 'merchant'" class="text-xs opacity-70">Мерчант</div>
                                    </td>
                                    <td>
                                        {{ invoice.user.email }}
                                    </td>
                                    <td>
                                        <CopyAddress v-if="invoice.tx_hash" :text="invoice.tx_hash"></CopyAddress>
                                    </td>
                                    <td>
                                        <InvoiceStatus :status="invoice.status"></InvoiceStatus>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime :data="invoice.created_at"></DateTime>
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
                                v-for="invoice in invoices.data"
                                :key="invoice.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Компактная шапка: ID и дата -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content">{{ invoice.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="invoice.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="text-xs text-base-content/70">Transaction ID</div>
                                            <div class="truncate text-base-content">{{ invoice.transaction_id || '—' }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-nowrap text-base-content">{{ invoice.amount }} {{ invoice.currency.toUpperCase() }}</div>
                                            <div v-show="invoice.balance_type === 'trust'" class="text-xs opacity-70">Траст</div>
                                            <div v-show="invoice.balance_type === 'merchant'" class="text-xs opacity-70">Мерчант</div>
                                        </div>
                                        <div>
                                            <InvoiceStatus :status="invoice.status"></InvoiceStatus>
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(invoice.id)"
                                                :aria-expanded="!!expandedCards[invoice.id]"
                                                :aria-label="!!expandedCards[invoice.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[invoice.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between">
                                            <div class="min-w-0">
                                                <div class="text-xs text-base-content/70">Transaction ID</div>
                                                <div class="truncate text-base-content">{{ invoice.transaction_id || '—' }}</div>
                                            </div>
                                            <div>
                                                <InvoiceStatus :status="invoice.status"></InvoiceStatus>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-nowrap text-xs text-base-content">
                                                {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                            </div>
                                            <div class="text-nowrap text-xs opacity-70" v-if="invoice.balance_type === 'trust'">Траст</div>
                                            <div class="text-nowrap text-xs opacity-70" v-else-if="invoice.balance_type === 'merchant'">Мерчант</div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(invoice.id)"
                                                    :aria-expanded="!!expandedCards[invoice.id]"
                                                    :aria-label="!!expandedCards[invoice.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[invoice.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[invoice.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">{{ invoice.user.email }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-8 4h10M4 6h16M6 20h12" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">txHash:</span>
                                            <CopyAddress v-if="invoice.tx_hash" :text="invoice.tx_hash"></CopyAddress>
                        <span v-else class="text-base-content/60">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal/>
    </div>
</template>
