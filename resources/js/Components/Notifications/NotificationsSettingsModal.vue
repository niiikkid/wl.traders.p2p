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

const close = () => {
    emit('close');
};
</script>

<template>
    <ModalNext
        v-if="canShow"
        :show="show"
        max-width="5xl"
        @close="close"
    >
        <ModalHeaderNext title="Уведомления" @close="close" />
        <ModalBodyNext>
            <NotificationsSettingsContent />
        </ModalBodyNext>
    </ModalNext>
</template>
