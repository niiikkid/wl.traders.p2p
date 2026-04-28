<script setup>
import {Head, router} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';

defineProps({
    logs: Object,
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
        <Head title="Логи провайдера" />
        <div class="space-y-4">
            <h1 class="text-xl font-semibold">API-логи</h1>
            <div class="overflow-x-auto bg-base-100 rounded-box shadow">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Операция</th>
                            <th>Метод</th>
                            <th>Результат</th>
                            <th>Ошибка</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs?.data ?? []" :key="log.id">
                            <td>{{ log.id }}</td>
                            <td>{{ log.operation }}</td>
                            <td>{{ log.method }}</td>
                            <td><span class="badge" :class="log.is_successful ? 'badge-success' : 'badge-error'">{{ log.is_successful ? 'Успешно' : 'Ошибка' }}</span></td>
                            <td class="max-w-md truncate">{{ log.error_message }}</td>
                            <td>{{ log.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                v-if="logs?.meta"
                :model-value="logs.meta.current_page"
                :total-pages="logs.meta.last_page"
                :per-page="logs.meta.per_page"
                :total-items="logs.meta.total"
                @page-changed="changePage"
            />
        </div>
    </AuthenticatedLayout>
</template>
