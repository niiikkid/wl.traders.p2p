<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

const props = defineProps({
    text: {
        type: String,
    },
});
const { copy, copied } = useAppClipboard()
</script>

<template>
    <AppTooltip :tip="copied ? 'Скопировано!' : 'Скопировать'" placement="top" :open="copied">
        <a
            href="#"
            @click.prevent="copy(text)"
            class="hover:bg-base-200 dark:hover:text-base-content p-1 rounded text-nowrap cursor-pointer"
        >
            {{ text.substring(0, 4) + '...' +  text.substring(text.length - 4) }}
        </a>
    </AppTooltip>
</template>

<style scoped>

</style>
