<script setup>
import {Link} from '@inertiajs/vue3';
import {computed} from 'vue';

const props = defineProps({
    active: {
        type: String,
        required: true,
        validator: (value) => ['integrations', 'deals', 'provider-logs', 'merchant-logs', 'merchants'].includes(value),
    },
});

const tabs = computed(() => [
    {id: 'deals', label: 'Сделки', routeName: 'admin.cascade-deals.index'},
    {id: 'integrations', label: 'Интеграции', routeName: 'admin.cascade-providers.index'},
    {id: 'merchants', label: 'Мерчанты', routeName: 'admin.cascade-merchant-settings.index'},
    {id: 'provider-logs', label: 'Логи провайдера', routeName: 'admin.cascade-provider-logs.index'},
    {id: 'merchant-logs', label: 'Логи мерчанта', routeName: 'admin.cascade-merchant-logs.index'},
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
                class="btn btn-xs btn-primary join-item cursor-default min-h-0 h-7 px-2"
                aria-current="page"
            >
                {{ tab.label }}
            </span>
            <Link
                v-else
                :href="route(tab.routeName)"
                class="btn btn-xs btn-outline join-item min-h-0 h-7 px-2"
                preserve-scroll
            >
                {{ tab.label }}
            </Link>
        </template>
    </div>
</template>
