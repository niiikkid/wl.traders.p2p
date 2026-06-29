<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import NotificationsSettingsModal from '@/Components/Notifications/NotificationsSettingsModal.vue';

const page = usePage();
const showModal = ref(false);

const canShow = computed(() => page.props.notificationsSettings != null);

const telegramIsActive = computed(() => page.props.notificationsSettings?.telegramAccount?.is_active === true);

const openModal = () => {
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

defineExpose({ openModal, closeModal });
</script>

<template>
    <div v-if="canShow">
        <button
            type="button"
            class="btn btn-ghost btn-square indicator h-auto min-h-0 px-2.5 py-1.5 rounded-xl border border-base-300/70 hover:border-primary/60 hover:bg-primary/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary/40"
            :class="{ 'bg-base-300/60 border-base-300': showModal }"
            :aria-expanded="showModal"
            aria-haspopup="dialog"
            title="Уведомления"
            @click.prevent="openModal"
        >
            <span class="sr-only">Уведомления</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 opacity-80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span
                v-if="!telegramIsActive"
                class="indicator-item badge badge-warning badge-xs top-1 right-1 min-w-2 px-0.5"
                aria-hidden="true"
            />
        </button>

        <NotificationsSettingsModal :show="showModal" @close="closeModal" />
    </div>
</template>
