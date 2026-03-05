<script setup>
import Dropzone from "@/Components/Form/Dropzone.vue";
import InputError from "@/Components/InputError.vue";
import MainButton from "@/Pages/PaymentLink/Components/MainButton.vue";
import {useForm} from "@inertiajs/vue3";

const props = defineProps({
    stage: {
        type: String
    },
    data: {
        type: Object,
        default: {}
    }
});

const formReceipt = useForm({
    receipt: null,
})

const openSuccess = () => {
    window.location = props.data.success_url;
};

const openFail = () => {
    window.location = props.data.fail_url;
};

const submitReceipt = () => {
    formReceipt.post(route('payment.dispute.store', props.data.uuid))
}
</script>

<template>
    <div class="py-1">
        <div class="mt-5 mb-5 text-base flex justify-center">
            <div class="w-2/3">
                <template v-if="stage === 'success'">
                    <div class="flex items-center justify-center mb-2">
                        <svg class="w-24 h-24 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 11.5 11 14l4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="mb-1 text-2xl font-semibold text-base-content text-center">
                        Платеж зачислен
                    </p>
                    <p class="text-sm text-base-content/60 text-center">
                        Счет на сумму {{ data.amount_formated }}{{ data.currency_symbol }} оплачен. Спасибо за оплату.
                    </p>
                </template>
                <template v-else>
                    <div class="flex items-center justify-center mb-2">
                        <svg class="w-24 h-24 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 9-6 6m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="mb-1 text-2xl font-semibold text-base-content text-center">
                        Время истекло
                    </p>
                    <p class="text-sm text-base-content/60 text-center">
                        Счет на сумму {{ data.amount_formated }}{{ data.currency_symbol }} не оплачен. Оплата не поступила.
                    </p>
                </template>
            </div>
        </div>

        <div class="mt-5" v-show="stage === 'success' && data.success_url">
            <MainButton
                text="Вернуться на сайт"
                @click.prevent="openSuccess"
            />
        </div>

        <form @submit.prevent="submitReceipt" v-show="stage === 'fail'" class="w-full">
            <div class="text-base-content/60 text-sm mb-3 text-center">
                Загрузите чек вашей транзакции, что бы мы могли найти ваш платеж
            </div>
            <Dropzone v-model="formReceipt.receipt" description="Расширение: jpeg, jpg, png, pdf"/>
            <InputError :message="formReceipt.errors.receipt" class="mt-2" />

            <div class="mt-4">
                <button type="submit" class="btn btn-neutral w-full">Отправить</button>
            </div>

            <div class="mt-5">
                <MainButton
                    text="Вернуться на сайт"
                    v-show="data.fail_url"
                    @click.prevent="openFail"
                />
            </div>
        </form>
    </div>
</template>

<style scoped>

</style>
