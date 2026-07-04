<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';
import CurrencyPairDisplay from '@/Components/Currency/CurrencyPairDisplay.vue';

const model = defineModel({
    required: true,
});

const props = defineProps({
    error: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: '',
    },
    items: {
        type: [Array, Object],
        default: () => [],
    },
    value: {
        type: String,
        required: true,
    },
    name: {
        type: String,
        required: true,
    },
    default_title: {
        type: String,
        default: 'Выберите',
    },
    default_value: {
        default: '0',
    },
    required: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    pairBase: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['change']);

const isOpen = ref(false);
const rootRef = ref(null);

const itemsList = computed(() => {
    if (!props.items) {
        return [];
    }

    return Array.isArray(props.items) ? props.items : Object.values(props.items);
});

const hasSelection = computed(() => {
    if (model.value === null || model.value === undefined || model.value === '') {
        return false;
    }

    return model.value !== props.default_value;
});

const selectedItem = computed(() => itemsList.value.find((item) => item[props.value] === model.value) ?? null);

const displayLabel = computed(() => {
    if (!hasSelection.value) {
        return props.default_title;
    }

    return selectedItem.value?.[props.name] ?? String(model.value).toUpperCase();
});

const triggerClasses = computed(() => [
    'select select-bordered w-full flex items-center justify-between gap-2 min-h-0 h-auto py-2',
    props.error ? 'select-error' : '',
    props.size === 'sm' ? 'select-sm text-xs' : '',
    props.disabled ? 'select-disabled pointer-events-none opacity-70' : 'cursor-pointer',
]);

const selectValue = (nextValue) => {
    if (props.disabled) {
        return;
    }

    model.value = nextValue;
    isOpen.value = false;
    emit('change', nextValue);
};

const toggleOpen = () => {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;
};

const onDocumentClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="dropdown w-full" :class="{ 'dropdown-open': isOpen && !disabled }">
        <div
            tabindex="0"
            role="button"
            :class="triggerClasses"
            @click.stop="toggleOpen"
        >
            <span class="flex min-w-0 flex-1 items-center gap-2 truncate text-left">
                <CurrencyPairDisplay
                    v-if="pairBase && hasSelection"
                    :base-currency="pairBase"
                    :quote-currency="model"
                    :show-label="false"
                    size="sm"
                    :icon-size="18"
                />
                <CurrencyDisplay
                    v-else-if="hasSelection"
                    :currency="model"
                    :show-label="false"
                    size="sm"
                    :icon-size="18"
                />
                <span class="truncate">{{ displayLabel }}</span>
            </span>
            <svg class="size-2.5 shrink-0 opacity-60" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
            </svg>
        </div>

        <ul
            tabindex="0"
            class="dropdown-content z-[100] mt-1 flex max-h-60 w-full min-w-full flex-col flex-nowrap gap-0.5 overflow-y-auto rounded-box border border-base-300 bg-base-100 p-2 shadow"
            @click.stop
        >
            <li v-if="!required" class="w-full">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-base-200"
                    :class="{ 'bg-base-200': !hasSelection }"
                    @click="selectValue(default_value)"
                >
                    <span class="text-sm">{{ default_title }}</span>
                </button>
            </li>
            <li v-for="item in itemsList" :key="String(item[value])" class="w-full">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-base-200"
                    :class="{ 'bg-base-200': item[value] === model }"
                    @click="selectValue(item[value])"
                >
                    <CurrencyPairDisplay
                        v-if="pairBase"
                        :base-currency="pairBase"
                        :quote-currency="item[value]"
                        :show-label="false"
                        size="sm"
                        :icon-size="18"
                    />
                    <CurrencyDisplay
                        v-else
                        :currency="item[value]"
                        :show-label="false"
                        size="sm"
                        :icon-size="18"
                    />
                    <span class="truncate text-sm">{{ item[name] }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
