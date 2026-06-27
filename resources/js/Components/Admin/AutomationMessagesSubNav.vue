<script setup>
defineProps({
    current: {
        type: String,
        required: true,
        validator: (value) => ['logs', 'stop-list', 'stop-words'].includes(value),
    },
});

const emit = defineEmits(['change']);

const items = [
    {
        key: 'logs',
        label: 'Сообщения',
    },
    {
        key: 'stop-list',
        label: 'Стоп-лист (отправители)',
    },
    {
        key: 'stop-words',
        label: 'Стоп-слова',
    },
];

const select = (key, isActive) => {
    if (isActive) {
        return;
    }

    emit('change', key);
};
</script>

<template>
    <nav aria-label="Подразделы сообщений" class="w-full max-w-full border-b border-base-300/40">
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
                @click="select(item.key, current === item.key)"
            >
                <svg
                    v-if="item.key === 'logs'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-3.5 shrink-0 opacity-70"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.556 8.5h8m-8 3.5H12m7.111-7H4.89a.896.896 0 0 0-.629.256.868.868 0 0 0-.26.619v9.25c0 .232.094.455.26.619A.896.896 0 0 0 4.89 16H9l3 4 3-4h4.111a.896.896 0 0 0 .629-.256.868.868 0 0 0 .26-.619v-9.25a.868.868 0 0 0-.26-.619.896.896 0 0 0-.63-.256Z" />
                </svg>
                <svg
                    v-else-if="item.key === 'stop-list'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-3.5 shrink-0 opacity-70"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12m3-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6H6m12 4H6m12 4H6m12 4H6" />
                </svg>
                <span class="hidden sm:inline">{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
