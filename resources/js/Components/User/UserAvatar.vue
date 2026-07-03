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
    avatarUrl: {
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

const resolvedAvatarUrl = computed(() => (
    props.avatarUrl
    || props.user?.avatar_url
    || null
));

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'lg':
            return { wrapper: 'w-12 h-12', text: 'text-2xl font-semibold' };
        case 'md':
            return { wrapper: 'w-12 h-12', text: 'text-xl font-semibold' };
        default:
            return { wrapper: 'w-10 h-10', text: 'text-lg font-semibold' };
    }
});
</script>

<template>
    <div class="avatar shrink-0" :class="resolvedAvatarUrl ? '' : 'avatar-placeholder'">
        <div
            class="rounded-full grid place-items-center overflow-hidden"
            :class="[
                sizeClasses.wrapper,
                resolvedAvatarUrl ? 'bg-base-200' : 'bg-neutral text-neutral-content',
                ring ? 'ring-primary ring-offset-base-100 ring-2 ring-offset-2' : '',
            ]"
        >
            <img
                v-if="resolvedAvatarUrl"
                :src="resolvedAvatarUrl"
                :alt="email || user?.email || 'Аватар'"
                class="h-full w-full object-cover"
            >
            <span
                v-else
                class="flex size-full items-center justify-center leading-none text-center"
                :class="sizeClasses.text"
            >{{ initials }}</span>
        </div>
    </div>
</template>
