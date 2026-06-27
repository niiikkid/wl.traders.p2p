<script setup>
import FunnelIcon from '@/Components/Filters/Icons/FunnelIcon.vue';
import ChevronDownIcon from '@/Components/Filters/Icons/ChevronDownIcon.vue';

defineProps({
    title: {
        type: String,
        default: 'Фильтр',
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['toggle']);
</script>

<template>
    <button
        type="button"
        class="input input-bordered input-sm w-full flex items-center gap-2 focus:outline-none focus:ring-0"
        :aria-expanded="isOpen ? 'true' : 'false'"
        @click.stop="$emit('toggle', $event)"
    >
        <span v-if="selectedCount" class="badge badge-primary badge-xs flex-none">{{ selectedCount }}</span>
        <FunnelIcon class="size-4 text-base-content/60 flex-none"/>
        <span class="truncate text-left">{{ title }}</span>
        <ChevronDownIcon
            class="ml-auto size-4 text-base-content/60 flex-none transition-transform duration-200"
            :class="{ 'rotate-180': isOpen }"
        />
    </button>
</template>
