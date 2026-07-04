<script setup>
import { computed } from 'vue';

const props = defineProps({
    merchant: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const statusKey = computed(() => {
    if (!props.merchant.validated_at) {
        return 'moderation';
    }
    if (props.merchant.banned_at) {
        return 'banned';
    }
    if (props.merchant.active) {
        return 'active';
    }

    return 'disabled';
});

const statusConfig = computed(() => {
    const configs = {
        moderation: {
            label: props.compact ? 'Модерация' : 'На модерации',
            badge: 'badge-soft badge-warning',
            status: 'status-warning',
        },
        banned: {
            label: 'Заблокирован',
            badge: 'badge-soft badge-error',
            status: 'status-error',
        },
        active: {
            label: 'Включен',
            badge: 'badge-soft badge-success',
            status: 'status-success',
        },
        disabled: {
            label: 'Выключен',
            badge: 'badge-soft badge-neutral',
            status: 'status-neutral',
        },
    };

    return configs[statusKey.value];
});
</script>

<template>
    <span
        role="status"
        :class="[
            'badge inline-flex items-center gap-1.5 whitespace-nowrap border-0 font-medium leading-none',
            compact ? 'badge-xs px-1.5 py-0.5' : 'badge-sm px-2',
            statusConfig.badge,
        ]"
    >
        <span
            :class="['status shrink-0', statusConfig.status, compact ? 'scale-75' : '']"
            aria-hidden="true"
        />
        {{ statusConfig.label }}
    </span>
</template>
