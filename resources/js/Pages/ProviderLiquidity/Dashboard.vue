<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    provider: Object,
    stats: Object,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Провайдер" />
        <div class="space-y-4">
            <h1 class="text-xl font-semibold">Главная провайдера ликвидности</h1>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="stat bg-base-100 rounded-box shadow">
                    <div class="stat-title">Провайдер</div>
                    <div class="stat-value text-lg">{{ provider?.name ?? 'Не привязан' }}</div>
                    <div class="stat-desc">{{ provider?.code }}</div>
                </div>
                <div class="stat bg-base-100 rounded-box shadow">
                    <div class="stat-title">Сделки</div>
                    <div class="stat-value">{{ stats.deals_count }}</div>
                </div>
                <div class="stat bg-base-100 rounded-box shadow">
                    <div class="stat-title">Provider balance</div>
                    <div class="stat-value text-lg">{{ stats.provider_balance ?? '0' }} USDT</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
