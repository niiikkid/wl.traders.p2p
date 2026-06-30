<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import NotificationsSettingsContent from '@/Components/Notifications/NotificationsSettingsContent.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const canShow = computed(() => usePage().props.notificationsSettings != null);

const showInAppSoundSettings = computed(
    () => usePage().props.notificationsSettings?.showInAppSoundSettings === true,
);

const modalMaxWidth = computed(() => (showInAppSoundSettings.value ? '5xl' : '2xl'));

const close = () => {
    emit('close');
};
</script>

<template>
    <ModalNext
        v-if="canShow"
        :show="show"
        :max-width="modalMaxWidth"
        @close="close"
    >
        <ModalHeaderNext title="Уведомления" class="!px-3 sm:!px-4" @close="close" />
        <ModalBodyNext class="!px-1.5 !py-1.5 sm:!px-2.5 sm:!py-2 ![scrollbar-gutter:auto]">
            <NotificationsSettingsContent />
        </ModalBodyNext>
    </ModalNext>
</template>
