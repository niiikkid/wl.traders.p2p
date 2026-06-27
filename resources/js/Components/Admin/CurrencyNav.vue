<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    current: {
        type: String,
        default: null,
    },
    markets: {
        type: Array,
        required: true,
    },
});

const visit = (marketKey) => {
    if (props.current === marketKey) {
        return;
    }

    router.visit(route('admin.currencies.index', { market: marketKey }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <nav aria-label="Маркеты валют" class="w-fit max-w-full">
        <div
            role="tablist"
            class="tabs tabs-box bg-base-200/60 border border-base-300/70 rounded-xl p-1 gap-0.5 w-fit max-w-full overflow-x-auto"
        >
            <button
                v-for="item in markets"
                :key="item.key"
                type="button"
                role="tab"
                class="tab whitespace-nowrap px-3 sm:px-4"
                :class="{ 'tab-active font-semibold': current === item.key }"
                :aria-selected="current === item.key"
                :aria-current="current === item.key ? 'page' : undefined"
                @click="visit(item.key)"
            >
                {{ item.label }}
            </button>
        </div>
    </nav>
</template>
