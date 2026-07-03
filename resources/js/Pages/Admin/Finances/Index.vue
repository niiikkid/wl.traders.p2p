<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FinancesNav from '@/Components/Admin/FinancesNav.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import InvoiceStatus from '@/Components/InvoiceStatus.vue';
import SuccessAction from '@/Components/Table/SuccessAction.vue';
import FailAction from '@/Components/Table/FailAction.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import CopyAddress from '@/Components/CopyAddress.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import DropdownFilter from '@/Components/Filters/Partials/DropdownFilter.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import DateTime from '@/Components/DateTime.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import { useModalStore } from '@/store/modal.js';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    tab: {
        type: String,
        default: 'deposits',
    },
});

const modalStore = useModalStore();
const page = usePage();

const invoices = ref(page.props.invoices);
const isDepositsTab = computed(() => props.tab === 'deposits');
const isWithdrawalsTab = computed(() => props.tab === 'withdrawals');
const filtersPanelName = computed(() => (
    isDepositsTab.value ? 'finances-deposits' : 'finances-withdrawals'
));

const expandedCards = ref({});
const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const confirmSuccessWithdrawal = (invoice) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите завершить заявку как успешную?',
        confirm_button_name: 'Подтвердить',
        confirm: () => {
            useForm({}).patch(route('admin.withdrawals.success', invoice.id), {
                preserveScroll: true,
            });
        },
    });
};

const confirmFailWithdrawal = (invoice) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите отклонить заявку?',
        confirm_button_name: 'Отклонить',
        confirm: () => {
            useForm({}).patch(route('admin.withdrawals.fail', invoice.id), {
                preserveScroll: true,
            });
        },
    });
};

router.on('success', () => {
    invoices.value = page.props.invoices;
});
</script>

<template>
    <div>
        <Head title="Финансы" />

        <MainTableSection
            title="Финансы"
            :data="invoices"
            :visit-extra-data="{ tab }"
        >
            <template #header>
                <FinancesNav :current="tab" />
            </template>

            <template #table-filters>
                <FiltersPanel
                    :name="filtersPanelName"
                    :query="{ tab }"
                >
                    <DropdownFilter
                        name="invoiceStatuses"
                        title="Статусы"
                    />
                    <InputFilter
                        name="id"
                        :placeholder="isDepositsTab ? 'ID депозита' : 'ID вывода'"
                    />
                    <InputFilter
                        name="amount"
                        placeholder="Сумма"
                    />
                    <InputFilter
                        name="user"
                        placeholder="Пользователь"
                    />
                    <DropdownFilter
                        name="merchantIds"
                        title="Мерчант"
                    />
                    <InputFilter
                        v-if="isWithdrawalsTab"
                        name="address"
                        placeholder="Адрес"
                    />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <DataTable>
                        <template #head>
                            <th scope="col">ID</th>
                            <th scope="col">Сумма</th>
                            <th scope="col">Пользователь</th>
                            <th scope="col">Мерчант</th>
                            <th v-if="isWithdrawalsTab" scope="col">Адрес</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Дата создания</th>
                            <th v-if="isWithdrawalsTab" scope="col">
                                <span class="sr-only">Действия</span>
                            </th>
                        </template>

                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.id"
                            class="bg-base-100 border-b last:border-none border-base-200"
                        >
                            <th scope="row" class="font-medium whitespace-nowrap">
                                {{ invoice.id }}
                            </th>
                            <td>
                                <div class="font-medium text-nowrap text-base-content">{{ invoice.amount }} {{ invoice.currency.toUpperCase() }}</div>
                                <span v-if="invoice.balance_type === 'trust'" class="badge badge-ghost badge-xs mt-1">Траст</span>
                                <span v-else-if="invoice.balance_type === 'merchant'" class="badge badge-ghost badge-xs mt-1">Мерчант</span>
                            </td>
                            <td>{{ invoice.user.email }}</td>
                            <td>
                                <div v-if="invoice.merchant" class="max-w-44">
                                    <div class="truncate font-medium">{{ invoice.merchant.name }}</div>
                                    <div class="truncate font-mono text-xs text-base-content/60">{{ invoice.merchant.uuid }}</div>
                                </div>
                                <span v-else class="text-base-content/50">—</span>
                            </td>
                            <td v-if="isWithdrawalsTab">
                                <div class="flex gap-2">
                                    <CopyAddress v-if="invoice.address" :text="invoice.address" />
                                    <div class="text-primary">{{ invoice.network?.toUpperCase() }}</div>
                                </div>
                            </td>
                            <td>
                                <InvoiceStatus :status="invoice.status" />
                            </td>
                            <td class="text-nowrap">
                                <DateTime :data="invoice.created_at" />
                            </td>
                            <td v-if="isWithdrawalsTab" class="text-nowrap text-right">
                                <template v-if="invoice.status === 'pending'">
                                    <SuccessAction @click.prevent="confirmSuccessWithdrawal(invoice)" />
                                    <FailAction class="ml-3" @click.prevent="confirmFailWithdrawal(invoice)" />
                                </template>
                            </td>
                        </tr>
                    </DataTable>

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
                                    <DateTime class="justify-start" :data="invoice.created_at" />
                                </div>
                            </div>

                            <div class="hidden sm:flex items-center justify-between gap-2">
                                <div :class="isWithdrawalsTab ? 'w-24' : 'text-right'">
                                    <div class="font-medium text-nowrap text-base-content">
                                        {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                    </div>
                                    <span v-if="invoice.balance_type === 'trust'" class="badge badge-ghost badge-xs mt-1">Траст</span>
                                    <span v-else-if="invoice.balance_type === 'merchant'" class="badge badge-ghost badge-xs mt-1">Мерчант</span>
                                </div>
                                <div>
                                    <InvoiceStatus :status="invoice.status" />
                                </div>
                                <div>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-xs"
                                        @click.stop="toggleExpand(invoice.id)"
                                        :aria-expanded="!!expandedCards[invoice.id]"
                                        :aria-label="!!expandedCards[invoice.id] ? 'Скрыть' : 'Показать детали'"
                                    >
                                        <svg
                                            :class="['w-4 h-4 transition-transform', { 'rotate-180': !!expandedCards[invoice.id] }]"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="sm:hidden">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-nowrap text-xs text-base-content">
                                            {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                        </div>
                                        <span v-if="invoice.balance_type === 'trust'" class="badge badge-ghost badge-xs mt-1">Траст</span>
                                        <span v-else-if="invoice.balance_type === 'merchant'" class="badge badge-ghost badge-xs mt-1">Мерчант</span>
                                    </div>
                                    <div>
                                        <InvoiceStatus :status="invoice.status" />
                                    </div>
                                </div>
                                <div class="flex justify-end mt-2">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-xs"
                                        @click.stop="toggleExpand(invoice.id)"
                                        :aria-expanded="!!expandedCards[invoice.id]"
                                        :aria-label="!!expandedCards[invoice.id] ? 'Скрыть' : 'Показать детали'"
                                    >
                                        <svg
                                            :class="['w-4 h-4 transition-transform', { 'rotate-180': !!expandedCards[invoice.id] }]"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div v-show="!!expandedCards[invoice.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    <span class="text-base-content/80 truncate">{{ invoice.user.email }}</span>
                                </div>

                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64M3.75 21V9.349m16.5 0V21M3.75 9.349a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72" />
                                    </svg>
                                    <template v-if="invoice.merchant">
                                        <span class="text-base-content/80 truncate">{{ invoice.merchant.name }}</span>
                                        <span class="font-mono text-xs text-base-content/50 truncate">{{ invoice.merchant.uuid }}</span>
                                    </template>
                                    <span v-else class="text-base-content/60">—</span>
                                </div>

                                <div v-if="isWithdrawalsTab" class="flex items-center gap-2 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                    </svg>
                                    <span class="text-base-content/80 truncate">Адрес:</span>
                                    <div class="flex gap-2 items-center min-w-0">
                                        <CopyAddress v-if="invoice.address" :text="invoice.address" />
                                        <span v-else class="text-base-content/60">—</span>
                                        <span v-if="invoice.network" class="text-primary text-xs">{{ invoice.network.toUpperCase() }}</span>
                                    </div>
                                </div>

                                <div v-if="isWithdrawalsTab && invoice.status === 'pending'" class="flex items-center gap-2 pt-2 border-t border-base-300">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-success"
                                        @click.prevent="confirmSuccessWithdrawal(invoice)"
                                    >
                                        Подтвердить
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-error"
                                        @click.prevent="confirmFailWithdrawal(invoice)"
                                    >
                                        Отклонить
                                    </button>
                                </div>
                            </div>
                        </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal />
    </div>
</template>
