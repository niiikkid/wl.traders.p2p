<script setup>
import { useClipboard } from '@vueuse/core'

const props = defineProps({
    text: {
        type: String,
    },
    copy_text: {
        type: String,
    },
});
const { copy, copied } = useClipboard()
</script>

<template>
    <div class="tooltip" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
        <a
            href="#"
            @click.prevent="copy(copy_text)"
            class="flex items-center justify-center hover:text-primary rounded-xl text-nowrap"
        >
            {{ text }}
            <svg class="text-primary ml-2 sm:w-6 sm:h-6 w-5 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M9 8v3a1 1 0 0 1-1 1H5m11 4h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-7a1 1 0 0 0-1 1v1m4 3v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7.13a1 1 0 0 1 .24-.65L7.7 8.35A1 1 0 0 1 8.46 8H13a1 1 0 0 1 1 1Z"/>
            </svg>
        </a>
    </div>
</template>

<style scoped>

</style>
