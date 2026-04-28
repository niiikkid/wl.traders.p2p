<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';

defineProps({
    services: {
        type: Array,
        default: () => [],
    },
});

const statusLabel = (service) => {
    if (service?.is_active) {
        return 'Включен';
    }

    return 'Выключен';
};

const statusClass = (service) => {
    if (service?.is_active) {
        return 'bg-success';
    }

    return 'bg-error';
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Сервисы" />

        <MainTableSection
            title="Сервисы"
            :data="{ data: services }"
        >
            <template #body>
                <div v-if="services.length === 0" class="alert alert-info mb-3">
                    <span>Провайдер не привязан. Обратитесь к администратору.</span>
                </div>

                <div v-else class="relative">
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col" class="pl-4">ID</th>
                                        <th scope="col" class="ps-2">Сервис</th>
                                        <th scope="col" class="ps-2">Интеграция</th>
                                        <th scope="col" class="ps-2">Статус</th>
                                        <th scope="col" class="text-right pr-3 sm:pr-4">Создан</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="service in services"
                                        :key="service.id"
                                        class="bg-base-100 border-b last:border-none border-base-200"
                                    >
                                        <th scope="row" class="whitespace-nowrap pl-4 font-medium">{{ service.id }}</th>
                                        <td class="ps-2">
                                            <div class="font-medium text-nowrap">{{ service.name }}</div>
                                            <div class="text-xs text-base-content/70 text-nowrap">{{ service.code }}</div>
                                        </td>
                                        <td class="ps-2">
                                            <div class="truncate max-w-52">{{ service.base_url || 'Base URL не задан' }}</div>
                                            <div class="text-xs text-base-content/70">
                                                Timeout: {{ service.timeout ?? '—' }} сек.
                                            </div>
                                        </td>
                                        <td class="ps-2">
                                            <div class="flex items-center text-nowrap">
                                                <div class="h-2.5 w-2.5 rounded-full me-2" :class="statusClass(service)"></div>
                                                {{ statusLabel(service) }}
                                            </div>
                                        </td>
                                        <td class="text-right align-middle pr-3 sm:pr-4">
                                            <div class="flex justify-end">
                                                <DateTime :data="service.created_at" />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="service in services"
                                :key="service.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body ps-5 pe-4 pt-2 pb-3">
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center gap-2 ps-0.5">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium">{{ service.id }}</span>
                                        </div>
                                        <div class="flex items-center text-nowrap">
                                            <div class="h-2.5 w-2.5 rounded-full me-2" :class="statusClass(service)"></div>
                                            <span class="text-xs">{{ statusLabel(service) }}</span>
                                        </div>
                                    </div>

                                    <div class="space-y-1 ps-1">
                                        <div class="font-medium truncate">{{ service.name }}</div>
                                        <div class="text-xs text-base-content/70 truncate">{{ service.code }}</div>
                                    </div>

                                    <div class="border-b border-base-content/10 my-1"></div>

                                    <div class="text-xs text-base-content/70 grid gap-1 ps-1">
                                        <div class="truncate">Base URL: {{ service.base_url || '—' }}</div>
                                        <div>Timeout: {{ service.timeout ?? '—' }} сек.</div>
                                    </div>

                                    <div class="border-b border-base-content/10 my-1"></div>

                                    <div class="text-xs text-base-content/70 flex justify-between items-baseline gap-2 ps-1">
                                        <span>Создан</span>
                                        <span class="text-nowrap text-end shrink-0">
                                            <DateTime :data="service.created_at" />
                                        </span>
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
