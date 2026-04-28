<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, onMounted, ref} from "vue";
import {useViewStore} from "@/store/view.js";
import Pagination from "@/Components/Pagination/Pagination.vue";
import TableEmptyState from "@/Components/TableEmptyState.vue";
import DateTime from "@/Components/DateTime.vue";
import CopyAddress from "@/Components/CopyAddress.vue";

const viewStore = useViewStore();

const page = usePage();

/** Полный доступ к типам балансов в таблице — только при просмотре кошелька Super Admin в админке. */
const walletAdminFullView = computed(() => Boolean(page.props.walletAdminFullView));

const user = page.props.user;
const invoices = ref(page.props.invoices);
const transactions = ref(page.props.transactions);
const tabs = ref(page.props.tabs);
const filters = ref(page.props.filters);
const currentTab = ref(page.props.currentTab);
const currentFilters = ref(page.props.currentFilters);

router.on('success', (event) => {
    invoices.value = page.props.invoices;
    transactions.value = page.props.transactions;
    currentFilters.value = page.props.currentFilters;
});

const openPage = (page) => {
    if (viewStore.isAdminViewMode) {
        router.visit(route('admin.users.wallet.index', user.id), {
            data: {
                page,
                tab: currentTab.value,
                currentFilters: currentFilters.value,
            },
            preserveScroll: true
        })
    } else {
        router.visit(route(route().current(), route().params), {
            data: {
                page,
                tab: currentTab.value,
                currentFilters: currentFilters.value,
            },
            preserveScroll: true
        })
    }
}

const currentPage = ref(1);

onMounted(() => {
    let urlParams = new URLSearchParams(window.location.search);
    currentTab.value = urlParams.get('tab') ?? 'invoices'

    currentPage.value = urlParams.get('page') ?? 1;
})

/** Только при просмотре кошелька Super Admin: выгрузка после выбора типа кошелька, не «все». */
const isWalletExportBlocked = computed(() => {
    if (!walletAdminFullView.value) {
        return false;
    }
    const balanceTypes = currentFilters.value?.transactions?.balanceTypes;
    return !balanceTypes || balanceTypes === 'all';
});

const openTransactionsExport = () => {
    if (!user?.id || isWalletExportBlocked.value) {
        return;
    }

    let url = route('admin.users.wallet.transactions.export', user.id);
    if (walletAdminFullView.value) {
        const balanceType = currentFilters.value?.transactions?.balanceTypes;
        const joiner = url.includes('?') ? '&' : '?';
        url += joiner + 'balance_type=' + encodeURIComponent(balanceType);
    }
    window.open(url, '_blank');
};
</script>

<template>
    <div>
        <h2 class="text-xl font-medium sm:text-2xl mb-3">История операций</h2>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <ul class="flex flex-wrap text-sm font-medium text-center">
                <li class="me-2" v-for="tab in tabs" :key="tab.key">
                    <a
                        @click.prevent="currentTab = tab.key; openPage(1)"
                        href="#"
                        :class="[
                            'btn btn-sm inline-flex items-center rounded-xl',
                            currentTab === tab.key ? 'btn-primary' : 'btn-outline',
                        ]"
                        aria-current="page"
                    >
                        <span>{{ tab.name }}</span>
                    </a>
                </li>
            </ul>

            <button
                v-if="currentTab === 'transactions' && viewStore.isAdminViewMode && user?.id"
                type="button"
                class="btn btn-secondary btn-sm shrink-0"
                :disabled="isWalletExportBlocked"
                :title="isWalletExportBlocked ? 'Сначала выберите тип кошелька в фильтре' : undefined"
                @click="openTransactionsExport"
            >
                Выгрузить в Excel
            </button>
        </div>

        <div
            v-if="filters[currentTab]"
            class="mt-3 grid xl:grid-cols-5 lg:grid-cols-4 sm:grid-cols-3 grid-cols-1 gap-3"
        >
            <div
                v-for="(invoiceFilters, filterKey) in filters[currentTab]"
            >
                <select
                    class="select select-bordered select-sm w-full"
                    required
                    v-model="currentFilters[currentTab][filterKey]"
                    @change="openPage(1)"
                >
                    <option
                        v-for="filter in invoiceFilters"
                        :value="filter.key"
                    >{{ filter.name }}</option>
                </select>
            </div>
        </div>

        <div v-if="currentTab === 'invoices'" class="mt-3">
            <div class="mx-auto space-y-2">
                <TableEmptyState
                    v-if="!invoices?.data?.length"
                    class="mt-3"
                    title="Инвойсов пока нет"
                    description="По выбранным фильтрам записей о пополнениях и выводах пока нет — при появлении операций они появятся здесь."
                />
                <template v-else>
                    <div class="overflow-x-auto card bg-base-100 shadow hidden md:block">
                        <table class="table table-sm">
                            <tbody>
                            <tr v-for="invoice in invoices.data" :key="'inv-desktop-' + invoice.id">
                                <th scope="row" class="font-medium whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="mr-3">
                                        <span v-if="invoice.status === 'success'" class="badge badge-success">
                                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                                            </svg>
                                        </span>
                                            <span v-if="invoice.status === 'pending'" class="badge badge-warning">
                                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                                            </svg>
                                        </span>
                                            <span v-if="invoice.status === 'fail'" class="badge badge-error">
                                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                                            </svg>
                                        </span>
                                        </div>
                                        <div>#{{ invoice.id }}</div>
                                    </div>
                                </th>
                                <td>
                                    <div class="text-nowrap text-center">
                                        <template v-if="invoice.type === 'deposit'">Пополнение</template>
                                        <template v-if="invoice.type === 'withdrawal'">Вывод</template>
                                    </div>
                                </td>
                                <td v-show="viewStore.isAdminViewMode && walletAdminFullView">
                                    <div class="text-nowrap text-center">
                                        <template v-if="invoice.balance_type === 'trust'">Траст</template>
                                        <template v-else-if="invoice.balance_type === 'merchant'">Мерчант</template>
                                        <template v-else-if="invoice.balance_type === 'teamleader'">Тимлид</template>
                                        <template v-else-if="invoice.balance_type === 'provider'">Провайдер</template>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-center">
                                        <template v-if="invoice.type === 'deposit'">+</template>
                                        <template v-if="invoice.type === 'withdrawal'">-</template>
                                        {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap text-center">
                                        {{ invoice.address }}
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-center">
                                        <DateTime class="" :data="invoice.created_at"/>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-end">
                                        <span v-if="invoice.status === 'success'" class="badge badge-sm badge-success">Успешно</span>
                                        <span v-if="invoice.status === 'pending'" class="badge badge-sm badge-warning">Ожидание</span>
                                        <span v-if="invoice.status === 'fail'" class="badge badge-sm badge-error">Ошибка</span>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-2 md:hidden">
                        <div
                            v-for="invoice in invoices.data"
                            :key="'inv-mobile-' + invoice.id"
                            class="card bg-base-100 shadow p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <span v-if="invoice.status === 'success'" class="badge badge-success">Успешно</span>
                                    <span v-else-if="invoice.status === 'pending'" class="badge badge-warning">Ожидание</span>
                                    <span v-else-if="invoice.status === 'fail'" class="badge badge-error">Ошибка</span>
                                </div>
                                <div class="text-sm text-base-content/70">#{{ invoice.id }}</div>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="text-base-content/70 text-sm">Тип</div>
                                <div class="text-right">
                                    <template v-if="invoice.type === 'deposit'">Пополнение</template>
                                    <template v-else-if="invoice.type === 'withdrawal'">Вывод</template>
                                </div>
                                <div class="text-base-content/70 text-sm">Сумма</div>
                                <div class="text-right">
                                    <template v-if="invoice.type === 'deposit'">+</template>
                                    <template v-else-if="invoice.type === 'withdrawal'">-</template>
                                    {{ invoice.amount }} {{ invoice.currency.toUpperCase() }}
                                </div>
                                <div class="text-base-content/70 text-sm">Адрес</div>
                                <div class="text-right break-all">{{ invoice.address }}</div>
                                <div class="text-base-content/70 text-sm">Дата</div>
                                <div class="text-right">
                                    <DateTime :data="invoice.created_at" />
                                </div>
                                <template v-if="viewStore.isAdminViewMode && walletAdminFullView">
                                    <div class="text-base-content/70 text-sm">Баланс</div>
                                    <div class="text-right">
                                        <template v-if="invoice.balance_type === 'trust'">Траст</template>
                                        <template v-else-if="invoice.balance_type === 'merchant'">Мерчант</template>
                                        <template v-else-if="invoice.balance_type === 'teamleader'">Тимлид</template>
                                        <template v-else-if="invoice.balance_type === 'provider'">Провайдер</template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <Pagination
                        v-model="invoices.meta.current_page"
                        :total-items="invoices.meta.total"
                        previous-label="Назад" next-label="Вперед"
                        @page-changed="openPage"
                        :per-page="invoices.meta.per_page"
                    ></Pagination>
                </template>
            </div>
        </div>

        <div v-if="currentTab === 'transactions'" class="mt-3">
            <div class="mx-auto space-y-2">
                <TableEmptyState
                    v-if="!transactions?.data?.length"
                    class="mt-3"
                    title="Операций пока нет"
                    description="По выбранным фильтрам движений по балансу пока нет — при появлении транзакций они отобразятся здесь."
                />
                <template v-else>
                    <div class="overflow-x-auto card bg-base-100 shadow hidden md:block">
                        <table class="table table-sm">
                            <tbody>
                            <tr v-for="transaction in transactions.data" :key="'tr-desktop-' + transaction.id">
                                <th scope="row" class="font-medium whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="mr-3">
                                        <span v-if="transaction.direction === 'in'" class="badge badge-success">
                                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                                            </svg>
                                        </span>
                                            <span v-if="transaction.direction === 'out'" class="badge badge-error">
                                            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                                            </svg>
                                        </span>
                                        </div>
                                        <div>#{{ transaction.id }}</div>
                                    </div>
                                </th>
                                <td>
                                    <div class="text-nowrap text-center">
                                        <template v-if="transaction.direction === 'in'">+</template>
                                        <template v-if="transaction.direction === 'out'">-</template>
                                        {{ transaction.amount }} {{ transaction.currency.toUpperCase() }}
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-center gap-2">
                                        <p class="font-medium">{{ transaction.type_name }}</p>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <div class="flex justify-center">
                                        <DateTime class="" :data="transaction.created_at"/>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-end">
                                        <span v-if="transaction.direction === 'in'" class="badge badge-sm badge-success">Зачисление</span>
                                        <span v-if="transaction.direction === 'out'" class="badge badge-sm badge-error">Снятие</span>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-2 md:hidden">
                        <div
                            v-for="transaction in transactions.data"
                            :key="'tr-mobile-' + transaction.id"
                            class="card bg-base-100 shadow p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <span v-if="transaction.direction === 'in'" class="badge badge-success">Зачисление</span>
                                    <span v-else class="badge badge-error">Снятие</span>
                                </div>
                                <div class="text-sm text-base-content/70">#{{ transaction.id }}</div>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="text-base-content/70 text-sm">Сумма</div>
                                <div class="text-right">
                                    <template v-if="transaction.direction === 'in'">+</template>
                                    <template v-else>-</template>
                                    {{ transaction.amount }} {{ transaction.currency.toUpperCase() }}
                                </div>
                                <div class="text-base-content/70 text-sm">Тип</div>
                                <div class="text-right">{{ transaction.type_name }}</div>
                                <div class="text-base-content/70 text-sm">Дата</div>
                                <div class="text-right">
                                    <DateTime :data="transaction.created_at" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <Pagination
                        v-model="transactions.meta.current_page"
                        :total-items="transactions.meta.total"
                        previous-label="Назад" next-label="Вперед"
                        @page-changed="openPage"
                        :per-page="transactions.meta.per_page"
                    ></Pagination>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
