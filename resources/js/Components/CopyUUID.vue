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
    <AppTooltip :tip="copied ? 'Скопировано!' : 'Скопировать'" :open="copied">
        <a
            href="#"
            @click.prevent="copy(text)"
            class="text-base-content hover:text-base-content hover:bg-base-200 p-1 rounded-lg text-nowrap lg:blur lg:blur-xs lg:hover:blur-none"
        >
            {{ text.substring(0, 8) + '...' +  text.substring(text.length - 8) }}
        </a>
    </AppTooltip>
</template>

<style scoped>

</style>
