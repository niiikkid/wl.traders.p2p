<script setup>
import { useClipboard } from '@vueuse/core'

const props = defineProps({
    text: {
        type: String,
    },
});
const { copy, copied } = useClipboard()
</script>

<template>
    <div class="tooltip" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
        <a
            href="#"
            @click.prevent="copy(text)"
            class="text-base-content hover:text-base-content hover:bg-base-200 p-1 rounded-lg text-nowrap lg:blur lg:blur-xs lg:hover:blur-none"
        >
            {{ text.substring(0, 8) + '...' +  text.substring(text.length - 8) }}
        </a>
    </div>
</template>

<style scoped>

</style>
