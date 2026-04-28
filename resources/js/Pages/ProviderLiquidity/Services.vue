<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    services: Array,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Сервисы провайдера" />
        <div class="space-y-4">
            <h1 class="text-xl font-semibold">Сервисы / интеграции</h1>
            <div class="overflow-x-auto bg-base-100 rounded-box shadow">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Код</th>
                            <th>Тип</th>
                            <th>Создан</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="service in services" :key="service.id">
                            <td>{{ service.name }}</td>
                            <td>{{ service.code }}</td>
                            <td>{{ service.provider_type }}</td>
                            <td>{{ service.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
