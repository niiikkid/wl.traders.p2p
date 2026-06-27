<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

import FinancesNav from '@/Components/Admin/FinancesNav.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import InvoiceStatus from "@/Components/InvoiceStatus.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref} from "vue";
import DateTime from "@/Components/DateTime.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

const invoices = usePage().props.invoices;

const expandedCards = ref({});
const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Финансы" />

        <MainTableSection
            title="Финансы"
            :data="invoices"
        >
            <template v-slot:header>
                <FinancesNav current="deposits" />
            </template>
            <template v-slot:table-filters>
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
                    <DataTable>
                        <template #head>
                                    <th scope="col">ID</th>
                                    <th scope="col">Сумма</th>
                                    <th scope="col">Пользователь</th>
                                    <th scope="col">Статус</th>
                                    <th scope="col">Дата создания</th>
                        </template>
                                <tr v-for="invoice in invoices.data" :key="invoice.id" class="bg-base-100 border-b last:border-none border-base-200">
                                    <th scope="row" class="font-medium whitespace-nowrap">
                                        {{ invoice.id }}
                                    </th>
                                    <td>
                                        <div class="text-nowrap">{{ invoice.amount }} {{invoice.currency.toUpperCase()}}</div>
                                        <div v-show="invoice.balance_type === 'trust'" class="text-xs opacity-70">Траст</div>
                                        <div v-show="invoice.balance_type === 'merchant'" class="text-xs opacity-70">Мерчант</div>
                                    </td>
                                    <td>
                                        {{ invoice.user.email }}
                                    </td>
                                    <td>
                                        <InvoiceStatus :status="invoice.status"></InvoiceStatus>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime :data="invoice.created_at"></DateTime>
                                    </td>
                                </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="invoice in invoices.data"
                                :key="invoice.id"
                            >
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
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
                                            <div>
                                                <div class="text-nowrap text-xs text-base-content">
                                                    {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                                </div>
                                                <div class="text-nowrap text-xs opacity-70" v-if="invoice.balance_type === 'trust'">Траст</div>
                                                <div class="text-nowrap text-xs opacity-70" v-else-if="invoice.balance_type === 'merchant'">Мерчант</div>
                                            </div>
                                            <div>
                                                <InvoiceStatus :status="invoice.status"></InvoiceStatus>
                                            </div>
                                        </div>
                                        <div class="flex justify-end mt-2">
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

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[invoice.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">{{ invoice.user.email }}</span>
                                        </div>
                                    </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal/>
    </div>
</template>
