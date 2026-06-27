<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], default: null },
    iconClass: { type: String, default: '' },
    active: { type: Boolean, default: false },
    badge: { type: [String, Number], default: null },
    badgeClass: { type: String, default: 'badge-primary' },
    spanClass: { type: String, default: '' },
    external: { type: Boolean, default: false },
});

const navigate = () => {
    if (props.external) {
        window.open(props.href, '_blank');
        return;
    }

    router.visit(props.href, { preserveScroll: true });
};
</script>

<template>
    <li :class="{ 'bg-base-content/10 rounded-lg': active }">
        <span
            role="link"
            tabindex="0"
            :class="spanClass"
            @click="navigate"
            @keydown.enter.space="navigate"
        >
            <component :is="icon" v-if="icon" :class="iconClass" />
            {{ label }}
            <span
                v-if="badge"
                class="badge badge-sm justify-self-end"
                :class="badgeClass"
            >
                {{ badge }}
            </span>
        </span>
    </li>
</template>
