<script setup>
const props = defineProps({
    stage: {
        type: String,
    },
    data: {
        type: Object,
        default: {}
    }
});
</script>

<template>
    <div v-show="stage !== 'select_gateway'" class="sm:mx-0 mx-2">
        <div class="card bg-base-100 shadow-md">
            <div class="card-body py-5 px-6">
                <div class="flex justify-between w-full text-sm">
                    <div>
                        <div class="text-2xl font-semibold text-base-content">{{ data.amount_formated }}{{ data.currency_symbol }}</div>
                        <div class="text-base-content/60">Сумма для оплаты</div>
                    </div>
                    <div v-show="stage === 'payment'" class="text-right">
                        <div class="text-2xl font-semibold text-base-content">
                            <slot name="clock"></slot>
                        </div>
                        <div class="text-base-content/60">Время на оплату</div>
                    </div>
                </div>
                <div class="divider my-1"></div>
                <div class="w-full text-center text-sm text-base-content">
                    <span class="badge badge-primary badge-sm align-middle mr-2">ID</span>
                    <span class="align-middle">{{ data.uuid }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
