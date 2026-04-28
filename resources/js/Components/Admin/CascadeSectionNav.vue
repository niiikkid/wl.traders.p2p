<script setup>
import {Link} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({
    active: {
        type: String,
        required: true,
        validator: (value) => ['integrations', 'deals', 'logs'].includes(value),
    },
});

const tabs = computed(() => [
    {id: 'deals', label: 'Сделки', routeName: 'admin.cascade-deals.index'},
    {id: 'integrations', label: 'Интеграции', routeName: 'admin.cascade-providers.index'},
    {id: 'logs', label: 'Логи', routeName: 'admin.cascade-provider-logs.index'},
]);
</script>

<template>
    <div
        class="join join-horizontal shrink-0"
        role="navigation"
        aria-label="Разделы каскада"
    >
        <template v-for="tab in tabs" :key="tab.id">
            <span
                v-if="active === tab.id"
                class="btn btn-sm btn-primary join-item cursor-default"
                aria-current="page"
            >
                {{ tab.label }}
            </span>
            <Link
                v-else
                :href="route(tab.routeName)"
                class="btn btn-sm btn-outline join-item"
                preserve-scroll
            >
                {{ tab.label }}
            </Link>
        </template>
    </div>
</template>
