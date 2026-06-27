<script setup>
import { router } from '@inertiajs/vue3';
import { useTableFiltersStore } from '@/store/tableFilters.js';

const props = defineProps({
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

    router.visit(route(route().current()), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    });
};
</script>

<template>
    <nav aria-label="Разделы реквизитов" class="w-fit max-w-full">
        <div
            role="tablist"
            class="tabs tabs-box bg-base-200/60 border border-base-300/70 rounded-xl p-1 gap-0.5 w-fit max-w-full overflow-x-auto"
        >
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                role="tab"
                class="tab gap-2 whitespace-nowrap px-3 sm:px-4"
                :class="{ 'tab-active font-semibold': current === item.key }"
                :aria-selected="current === item.key"
                :aria-current="current === item.key ? 'page' : undefined"
                @click="visit(item.key)"
            >
                <svg
                    v-if="item.key === 'active'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4 shrink-0 opacity-80"
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
                    class="size-4 shrink-0 opacity-80"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
