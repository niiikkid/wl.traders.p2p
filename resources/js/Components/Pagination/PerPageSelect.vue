<script setup>
defineProps({
    modelValue: {
        type: Number,
        default: 10,
    },
    options: {
        type: Array,
        default: () => [
            { value: 5, name: '5 строк' },
            { value: 10, name: '10 строк' },
            { value: 15, name: '15 строк' },
            { value: 20, name: '20 строк' },
            { value: 25, name: '25 строк' },
            { value: 50, name: '50 строк' },
            { value: 100, name: '100 строк' },
        ],
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const selectValue = (value, event) => {
    emit('update:modelValue', value);
    emit('change', value);

    event?.currentTarget?.closest('.dropdown')?.querySelector('[tabindex="0"]')?.blur();
};
</script>

<template>
    <div class="dropdown dropdown-left dropdown-top">
        <div
            tabindex="0"
            role="button"
            class="btn btn-xs gap-1 border-transparent bg-base-300/60 font-normal text-base-content hover:bg-base-300"
        >
            {{ modelValue }} строк
            <svg
                class="size-2.5 opacity-60"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 10 6"
            >
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
            </svg>
        </div>

        <ul
            tabindex="0"
            class="dropdown-content menu z-20 w-40 rounded-box border border-base-300/50 bg-base-100 p-1 shadow-sm"
        >
            <li v-for="option in options" :key="option.value">
                <button
                    type="button"
                    class="text-xs"
                    :class="modelValue === option.value ? 'active bg-primary/15 text-primary font-medium' : ''"
                    @click="selectValue(option.value, $event)"
                >
                    {{ option.name }}
                </button>
            </li>
        </ul>
    </div>
</template>
