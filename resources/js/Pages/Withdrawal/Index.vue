<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePage } from '@inertiajs/vue3';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import InvoiceStatus from "@/Components/InvoiceStatus.vue";
import SuccessAction from "@/Components/Table/SuccessAction.vue";
import FailAction from "@/Components/Table/FailAction.vue";
import {useModalStore} from "@/store/modal.js";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import CopyAddress from "@/Components/CopyAddress.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref} from "vue";
import DateTime from "@/Components/DateTime.vue";
import DisplayID from "@/Components/DisplayID.vue";

const modalStore = useModalStore();

const invoices = ref(usePage().props.invoices);

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
        }
    });
};

const confirmFailParser = (invoice) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите отклонить заявку?',
        confirm_button_name: 'Отклонить',
        confirm: () => {
            useForm({}).patch(route('admin.withdrawals.fail', invoice.id), {
                preserveScroll: true,
            });
        }
    });
};

router.on('success', () => {
    invoices.value = usePage().props.invoices;
})

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Заявки на вывод средств" />

        <MainTableSection
            title="Заявки на вывод средств"
            :data="invoices"
        >
            <template v-slot:header>
                <FiltersPanel name="withdrawals">
                    <DropdownFilter
                        name="invoiceStatuses"
                        title="Статусы"
                    />
                    <InputFilter
                        name="id"
                        placeholder="ID вывода"
                    />
                    <InputFilter
                        name="amount"
                        placeholder="Сумма"
                    />
                    <InputFilter
                        name="user"
                        placeholder="Пользователь"
                    />
                    <InputFilter
                        name="address"
                        placeholder="Адрес"
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
                                    <th scope="col" class="text-nowrap">External ID</th>
                                    <th scope="col">Сумма</th>
                                    <th scope="col">Пользователь</th>
                                    <th scope="col">Адрес</th>
                                    <th scope="col">txHash</th>
                                    <th scope="col">Статус</th>
                                    <th scope="col">Дата создания</th>
                                    <th scope="col" class="flex justify-center">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="invoice in invoices.data" class="bg-base-100 border-b last:border-none border-base-200">
                                    <th scope="row" class="font-medium whitespace-nowrap">
                                        {{ invoice.id }}
                                    </th>
                                    <td>
                                        <DisplayID v-if="invoice.external_id" :id="invoice.external_id" :copyable="true"/>
                                        <span v-else>-</span>
                                    </td>
                                    <td>
                                        <div class="text-nowrap">{{ invoice.amount }} {{invoice.currency.toUpperCase()}}</div>
                                        <div v-show="invoice.balance_type === 'trust'" class="text-xs text-base-content/70">
                                            Траст
                                        </div>
                                        <div v-show="invoice.balance_type === 'merchant'" class="text-xs text-base-content/70">
                                            Мерчант
                                        </div>
                                    </td>
                                    <td>
                                        {{ invoice.user.email }}
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <CopyAddress v-if="invoice.address" :text="invoice.address"></CopyAddress>
                                            <div class="text-primary">{{ invoice.network?.toUpperCase() }}</div>
                                        </div>
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
                                    <td class="text-nowrap text-right">
                                        <template v-if="invoice.status === 'pending'">
                                            <SuccessAction @click.prevent="confirmSuccessWithdrawal(invoice)"/>
                                            <FailAction class="ml-3" @click.prevent="confirmFailParser(invoice)"/>
                                        </template>
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
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
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
                                        <div class="w-20">
                                            <div v-show="invoice.balance_type === 'trust'" class="text-xs text-base-content/70">Траст</div>
                                            <div v-show="invoice.balance_type === 'merchant'" class="text-xs text-base-content/70">Мерчант</div>
                                            <div class="text-nowrap text-base-content pt-1">{{ invoice.amount }} {{ invoice.currency.toUpperCase() }}</div>
                                        </div>
                                        <div class="w-20 text-center">
                                            <div class="text-xs text-base-content/70">External ID</div>
                                            <DisplayID v-if="invoice.external_id" :id="invoice.external_id" :copyable="true"/>
                                            <div v-else>-</div>
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
                                                <div v-show="invoice.balance_type === 'trust'" class="text-xs text-base-content/70">Траст</div>
                                                <div v-show="invoice.balance_type === 'merchant'" class="text-xs text-base-content/70">Мерчант</div>
                                                <div class="text-nowrap text-base-content pt-1">{{ invoice.amount }} {{ invoice.currency.toUpperCase() }}</div>
                                            </div>
                                            <div class="min-w-0 text-center">
                                                <div class="text-xs text-base-content/70">External ID</div>
                                                <DisplayID v-if="invoice.external_id" :id="invoice.external_id" :copyable="true"/>
                                                <div v-else>-</div>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
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
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[invoice.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">{{ invoice.user.email }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">Адрес:</span>
                                            <div class="flex gap-2 items-center">
                                                <CopyAddress v-if="invoice.address" :text="invoice.address"></CopyAddress>
                                                <span v-else class="text-base-content/60">—</span>
                                                <span v-if="invoice.network" class="text-primary text-xs">{{ invoice.network.toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-primary">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33" />
                                            </svg>
                                            <span class="text-base-content/80 truncate">txHash:</span>
                                            <CopyAddress v-if="invoice.tx_hash" :text="invoice.tx_hash"></CopyAddress>
                                            <span v-else class="text-base-content/60">—</span>
                                        </div>
                                        <div v-if="invoice.status === 'pending'" class="flex items-center gap-2 pt-2 border-t border-base-300">
                                            <button
                                                class="btn btn-sm btn-success"
                                                @click.prevent="confirmSuccessWithdrawal(invoice)"
                                            >
                                                Подтвердить
                                            </button>
                                            <button
                                                class="btn btn-sm btn-error"
                                                @click.prevent="confirmFailParser(invoice)"
                                            >
                                                Отклонить
                                            </button>
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
