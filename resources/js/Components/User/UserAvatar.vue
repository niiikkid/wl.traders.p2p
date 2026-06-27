<script setup>
import { computed } from 'vue';
import { getUserInitials } from '@/utils/userInitials.js';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    name: {
        type: String,
        default: '',
    },
    email: {
        type: String,
        default: '',
    },
    login: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    ring: {
        type: Boolean,
        default: false,
    },
});

const initials = computed(() => getUserInitials({
    name: props.name || props.user?.name,
    email: props.email || props.user?.email,
    login: props.login || props.user?.login,
}));

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'lg':
            return { wrapper: 'w-12 h-12', text: 'text-2xl font-semibold leading-none tracking-tighter' };
        case 'md':
            return { wrapper: 'w-12 h-12', text: 'text-2xl font-semibold leading-none tracking-tighter' };
        default:
            return { wrapper: 'w-10 h-10', text: 'text-xl font-semibold leading-none tracking-tighter' };
    }
});
</script>

<template>
    <div class="avatar avatar-placeholder shrink-0">
        <div
            class="rounded-full bg-neutral text-neutral-content flex items-center justify-center"
            :class="[
                sizeClasses.wrapper,
                ring ? 'ring-primary ring-offset-base-100 ring-2 ring-offset-2' : '',
            ]"
        >
            <span :class="sizeClasses.text">{{ initials }}</span>
        </div>
    </div>
</template>
