<script setup>
const props = defineProps({
    data: {
        type: Object,
        default: {}
    }
});
</script>

<template>
    <div>
        <div class="mt-5 mb-5 text-base flex justify-center">
            <div class="w-2/3">
                <div class="flex items-center justify-center mb-2">
                    <svg class="w-24 h-24 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m6 6 12 12m3-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="mb-1 text-2xl font-semibold text-base-content text-center">
                    Заявка отклонена
                </p>
                <p class="text-sm text-base-content/60 text-center">
                    Причина: {{ data.dispute_cancel_reason }}.
                </p>
                <p class="text-sm text-base-content/60 text-center mt-3">
                    Если вы не согласны с решением, свяжитесь с поддержкой, кнопка вверху.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
