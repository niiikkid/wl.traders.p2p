<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {computed, ref} from "vue";

const users = ref(usePage().props.users);
const totals = ref(usePage().props.totals);

const formatNumber = (num) => { //TODO move to utils
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const totalsFormated = computed(() => {
    return {
        trust_balance: formatNumber(totals.value.trust_balance),
        merchant_balance: formatNumber(totals.value.merchant_balance),
        total_balance: formatNumber(totals.value.total_balance),
        trust_deposits: formatNumber(totals.value.trust_deposits),
        trust_withdrawals: formatNumber(totals.value.trust_withdrawals),
        merchant_deposits: formatNumber(totals.value.merchant_deposits),
        merchant_withdrawals: formatNumber(totals.value.merchant_withdrawals),
        payment_for_orders: formatNumber(totals.value.payment_for_orders),
    }
});

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Учет средств" />

        <MainTableSection
            title="Учет средств"
            :data="users"
        >
            <template v-slot:header>
                <FiltersPanel name="user-balances">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                        class="w-64"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="mb-4 card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title mb-2">Итоговые суммы</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
                            <div class="stat bg-base-200 rounded-box">
                                <div class="stat-title text-sm">Траст баланс</div>
                                <div class="stat-value text-lg">{{ totalsFormated.trust_balance }} $</div>
                            </div>
                            <div class="stat bg-base-200 rounded-box">
                                <div class="stat-title text-sm">Мерчант баланс</div>
                                <div class="stat-value text-lg">{{ totalsFormated.merchant_balance }} $</div>
                            </div>
                            <div class="stat bg-base-200 rounded-box">
                                <div class="stat-title text-sm">Общий баланс</div>
                                <div class="stat-value text-lg">{{ totalsFormated.total_balance }} $</div>
                            </div>
                        </div>

                        <h3 class="card-title mt-4 mb-2">Итоговые суммы операций</h3>
                        <div class="grid sm:grid-cols-2 md:grid-cols-3 2xl:grid-cols-5 gap-3 md:gap-4">
                            <div class="stat rounded-box bg-base-200">
                                <div class="stat-title text-sm">Зачисления на траст</div>
                                <div class="stat-value text-lg text-success">{{ totalsFormated.trust_deposits }} $</div>
                            </div>
                            <div class="stat rounded-box bg-base-200">
                                <div class="stat-title text-sm">Выводы с траста</div>
                                <div class="stat-value text-lg text-error">{{ totalsFormated.trust_withdrawals }} $</div>
                            </div>
                            <div class="stat rounded-box bg-base-200">
                                <div class="stat-title text-sm">Зачисления на мерчант</div>
                                <div class="stat-value text-lg text-success">{{ totalsFormated.merchant_deposits }} $</div>
                            </div>
                            <div class="stat rounded-box bg-base-200">
                                <div class="stat-title text-sm">Выводы с мерчанта</div>
                                <div class="stat-value text-lg text-error">{{ totalsFormated.merchant_withdrawals }} $</div>
                            </div>
                            <div class="stat rounded-box bg-base-200">
                                <div class="stat-title text-sm">Оплата сделок</div>
                                <div class="stat-value text-lg text-error">{{ totalsFormated.payment_for_orders }} $</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <div class="card-body p-0">
                                <table class="table table-sm">
                                    <thead class="text-xs uppercase bg-base-300">
                                        <tr>
                                            <th scope="col" class="px-3 py-2">
                                                ID
                                            </th>
                                            <th scope="col" class="px-3 py-2">
                                                Пользователь
                                            </th>
                                            <th scope="col" class="text-center px-3 py-2">
                                                Cделки
                                            </th>
                                            <th scope="col" class="text-center px-3 py-2">
                                                Траст (баланс, зачисления, выводы)
                                            </th>
                                            <th scope="col" class="text-center px-3 py-2">
                                                Мерчант (баланс, зачисления, выводы)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="user in users.data" class="">
                                            <th scope="row" class="px-3 py-2 font-medium whitespace-nowrap">
                                                {{ user.id }}
                                            </th>
                                            <td class="px-3 py-2 text-nowrap">
                                                <div class="inline-flex items-center gap-2">
                                                    <div>
                                                        <div class="text-nowrap">
                                                            {{ user.email }}
                                                        </div>
                                                        <div class="text-nowrap text-base-content/50 text-xs">
                                                            {{ user.role.name }}
                                                        </div>
                                                    </div>
                                                    <span
                                                        v-if="user.banned_at"
                                                    >
                                                        <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                            <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-nowrap text-center font-medium text-error">
                                                -{{ user.wallet.payment_for_orders }} $
                                            </td>
                                            <td class="px-3 py-2 text-nowrap font-medium">
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="px-3 py-2 text-nowrap font-medium">
                                                        {{ user.wallet.trust_balance }} $
                                                    </span>
                                                        <span class="px-3 py-2 text-nowrap font-medium text-success">
                                                        +{{ user.wallet.trust_deposits }} $
                                                    </span>
                                                        <span class="px-3 py-2 text-nowrap font-medium text-error">
                                                        -{{ user.wallet.trust_withdrawals }} $
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="px-3 py-2 text-nowrap font-medium">
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="px-3 py-2 text-nowrap font-medium">
                                                        {{ user.wallet.merchant_balance }} $
                                                    </span>
                                                        <span class="px-3 py-2 text-nowrap font-medium text-success">
                                                        +{{ user.wallet.merchant_deposits }} $
                                                    </span>
                                                        <span class="px-3 py-2 text-nowrap font-medium text-error">
                                                        -{{ user.wallet.merchant_withdrawals }} $
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="user in users.data"
                                :key="user.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: ID и пользователь -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium">{{ user.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-2">
                                            <div class="text-right">
                                                <div class="text-sm font-medium text-nowrap">{{ user.email }}</div>
                                                <div class="text-xs text-base-content/50">{{ user.role.name }}</div>
                                            </div>
                                            <span v-if="user.banned_at">
                                                <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Для экранов sm и больше -->
                                    <div class="hidden sm:block">
                                        <!-- Сделки -->
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Сделки:</span>
                                            <span class="font-medium text-error">-{{ user.wallet.payment_for_orders }} $</span>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>

                                        <!-- Траст баланс -->
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Траст баланс:</span>
                                            <span class="font-medium">{{ user.wallet.trust_balance }} $</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Зачисления:</span>
                                            <span class="font-medium text-success">+{{ user.wallet.trust_deposits }} $</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Выводы:</span>
                                            <span class="font-medium text-error">-{{ user.wallet.trust_withdrawals }} $</span>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>

                                        <!-- Мерчант баланс -->
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Мерчант баланс:</span>
                                            <span class="font-medium">{{ user.wallet.merchant_balance }} $</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Зачисления:</span>
                                            <span class="font-medium text-success">+{{ user.wallet.merchant_deposits }} $</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1">
                                            <span class="text-xs text-base-content/70">Выводы:</span>
                                            <span class="font-medium text-error">-{{ user.wallet.merchant_withdrawals }} $</span>
                                        </div>
                                    </div>

                                    <!-- Для экранов меньше sm -->
                                    <div class="sm:hidden">
                                        <!-- Сделки -->
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs text-base-content/70">Сделки:</span>
                                            <span class="font-medium text-error text-sm">-{{ user.wallet.payment_for_orders }} $</span>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>

                                        <!-- Траст -->
                                        <div class="mb-2">
                                            <div class="text-xs text-base-content/70 mb-1">Траст:</div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs">Баланс:</span>
                                                <span class="font-medium text-sm">{{ user.wallet.trust_balance }} $</span>
                                            </div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs">Зачисления:</span>
                                                <span class="font-medium text-success text-sm">+{{ user.wallet.trust_deposits }} $</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs">Выводы:</span>
                                                <span class="font-medium text-error text-sm">-{{ user.wallet.trust_withdrawals }} $</span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>

                                        <!-- Мерчант -->
                                        <div>
                                            <div class="text-xs text-base-content/70 mb-1">Мерчант:</div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs">Баланс:</span>
                                                <span class="font-medium text-sm">{{ user.wallet.merchant_balance }} $</span>
                                            </div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs">Зачисления:</span>
                                                <span class="font-medium text-success text-sm">+{{ user.wallet.merchant_deposits }} $</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs">Выводы:</span>
                                                <span class="font-medium text-error text-sm">-{{ user.wallet.merchant_withdrawals }} $</span>
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
    </div>
</template>
<style scoped>
.stat {
    &:not(:last-child) {
        @supports (color: color-mix(in lab, #ff00a4, #00ffcc)) {
            border-inline-end: none;
        }
    }
}
</style>
