<script setup>
import {Head, router} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';

defineProps({
    wallet: Object,
    transactions: Object,
});

const changePage = (pageNumber) => {
    router.get(
        route(route().current()),
        {page: pageNumber},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Кошелёк провайдера" />
        <div class="space-y-4">
            <h1 class="text-xl font-semibold">Кошелёк</h1>
            <div class="stats bg-base-100 shadow">
                <div class="stat">
                    <div class="stat-title">Trust balance</div>
                    <div class="stat-value text-lg">{{ wallet?.trust_balance ?? '0' }} USDT</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Reserve</div>
                    <div class="stat-value text-lg">{{ wallet?.reserve_balance ?? '0' }} USDT</div>
                </div>
            </div>

            <div class="overflow-x-auto bg-base-100 rounded-box shadow">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Тип</th>
                            <th>Направление</th>
                            <th>Сумма</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in transactions?.data ?? []" :key="transaction.id">
                            <td>{{ transaction.id }}</td>
                            <td>{{ transaction.type }}</td>
                            <td>{{ transaction.direction }}</td>
                            <td>{{ transaction.amount }}</td>
                            <td>{{ transaction.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                v-if="transactions?.meta"
                :model-value="transactions.meta.current_page"
                :total-pages="transactions.meta.last_page"
                :per-page="transactions.meta.per_page"
                :total-items="transactions.meta.total"
                @page-changed="changePage"
            />
        </div>
    </AuthenticatedLayout>
</template>
