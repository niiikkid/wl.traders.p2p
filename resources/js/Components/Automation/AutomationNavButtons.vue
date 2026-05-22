<script setup>
import {router} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({
    current: {
        type: String,
        required: true,
    },
});

const items = [
    {key: 'messages', label: 'Сообщения', route: 'admin.sms-logs.index'},
    {key: 'shadow', label: 'Теневой лог', route: 'admin.shadow-sms-logs.index'},
    {key: 'app', label: 'Приложение', route: 'admin.app.index'},
    {key: 'devices', label: 'Устройства', route: 'admin.devices.index'},
];

const visibleItems = computed(() => items.filter((item) => item.key !== props.current));

const visit = (routeName) => {
    router.visit(route(routeName), {preserveScroll: true});
};
</script>

<template>
    <div class="ml-auto flex flex-wrap justify-end gap-2">
        <button
            v-for="item in visibleItems"
            :key="item.key"
            type="button"
            class="btn btn-outline btn-sm shrink-0"
            @click="visit(item.route)"
        >
            {{ item.label }}
        </button>
    </div>
</template>
