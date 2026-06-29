<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import NotificationsSettingsContent from '@/Components/Notifications/NotificationsSettingsContent.vue';

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Уведомления" />

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Уведомления</h2>
            </div>

            <NotificationsSettingsContent />
        </div>

        <ConfirmModal />
    </div>
</template>
