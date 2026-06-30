<script setup>
const props = defineProps({
    current: {
        type: String,
        required: true,
        validator: (value) => ['stack', 'history'].includes(value),
    },
});

const emit = defineEmits(['select']);

const items = [
    {
        key: 'stack',
        label: 'Доступные выплаты',
    },
    {
        key: 'history',
        label: 'История выплат',
    },
];

const visit = (tabKey) => {
    if (props.current === tabKey) {
        return;
    }

    emit('select', tabKey);
};
</script>

<template>
    <nav aria-label="Разделы выплат" class="w-fit max-w-full">
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
                    v-if="item.key === 'stack'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4 shrink-0 opacity-80"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75" />
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0" />
                </svg>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
