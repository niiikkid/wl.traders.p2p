<script setup>
import { router } from '@inertiajs/vue3';
import { useTableFiltersStore } from '@/store/tableFilters.js';

const props = defineProps({
    traderId: {
        type: [Number, String],
        required: true,
    },
    current: {
        type: String,
        required: true,
        validator: (value) => ['active', 'archived'].includes(value),
    },
});

const tableFiltersStore = useTableFiltersStore();

const items = [
    {
        key: 'active',
        label: 'Активные',
    },
    {
        key: 'archived',
        label: 'Архив',
    },
];

const visit = (tabKey) => {
    if (props.current === tabKey) {
        return;
    }

    tableFiltersStore.setTab(tabKey);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route('leader.traders.payment-details.index', { trader: props.traderId }), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    });
};
</script>

<template>
    <nav aria-label="Разделы реквизитов" class="w-full max-w-full border-b border-base-300/40">
        <div
            role="tablist"
            class="tabs tabs-border tabs-sm w-fit max-w-full overflow-x-auto -mb-px gap-0"
        >
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                role="tab"
                class="tab gap-1.5 whitespace-nowrap px-2 sm:px-3 text-base-content/55 hover:text-base-content/80 transition-colors"
                :class="{ 'tab-active !text-base-content font-medium': current === item.key }"
                :aria-selected="current === item.key"
                @click="visit(item.key)"
            >
                <svg
                    v-if="item.key === 'active'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-3.5 shrink-0 opacity-70"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-3.5 shrink-0 opacity-70"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
