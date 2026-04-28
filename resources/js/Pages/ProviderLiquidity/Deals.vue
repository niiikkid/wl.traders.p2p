<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    deals: Object,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Сделки провайдера" />
        <div class="space-y-4">
            <h1 class="text-xl font-semibold">Мои каскадные сделки</h1>
            <div class="overflow-x-auto bg-base-100 rounded-box shadow">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>PayIn</th>
                            <th>External ID</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Залог</th>
                            <th>Создана</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="deal in deals?.data ?? []" :key="deal.id">
                            <td class="font-mono text-xs">{{ deal.uuid }}</td>
                            <td>{{ deal.external_id }}</td>
                            <td>{{ deal.amount }} {{ deal.currency }}</td>
                            <td><span class="badge">{{ deal.sub_status }}</span></td>
                            <td>{{ deal.collateral_holds?.[0]?.amount ?? 'Пусто' }} USDT</td>
                            <td>{{ deal.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination v-if="deals" :links="deals.meta?.links ?? deals.links" />
        </div>
    </AuthenticatedLayout>
</template>
