<script setup>
import { computed } from 'vue';
import { useDropdown } from '@/composables/useDropdown.js';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const { isOpen, el, toggle, close } = useDropdown();

const activeLabel = computed(() => (
    props.tabs.find((tab) => tab.value === props.modelValue)?.label || ''
));

const select = (value) => {
    emit('update:modelValue', value);
    close();
};
</script>

<template>
    <div>
        <div class="hidden md:join md:join-horizontal md:flex md:flex-wrap">
            <button
                v-for="tab in tabs"
                :key="`chart-tab-${tab.value}`"
                type="button"
                class="btn btn-sm join-item"
                :class="modelValue === tab.value ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                @click="emit('update:modelValue', tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div ref="el" class="dropdown md:hidden" :class="{ 'dropdown-open': isOpen }">
            <button
                type="button"
                class="btn btn-sm bg-base-100 border-transparent"
                @click.stop="toggle()"
            >
                Тип графика: {{ activeLabel }}
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                </svg>
            </button>
            <ul class="dropdown-content z-30 mt-2 menu w-56 max-w-[calc(100vw-1rem)] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                <li v-for="tab in tabs" :key="`mobile-chart-tab-${tab.value}`">
                    <button
                        type="button"
                        :class="modelValue === tab.value ? 'menu-active' : ''"
                        @click="select(tab.value)"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
