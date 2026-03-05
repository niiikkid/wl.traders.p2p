<script setup>
import GatewayLogo from "@/Components/GatewayLogo.vue";
import MainButton from "@/Pages/PaymentLink/Components/MainButton.vue";
import {useForm} from "@inertiajs/vue3";
import {ref} from "vue";

const props = defineProps({
    data: {
        type: Object,
        default: {}
    },
});

const emit = defineEmits(['selected']);

const formGatewaySelect = useForm({});

const submitGatewaySelect = () => {
    formGatewaySelect.post(route('payment.payment-detail.store', {
        'order': props.data.uuid,
        'paymentGateway': selectedGateway.value,
    }), {
        onSuccess: result => {
            selected();
        },
    })
}

const selected = () => {
    emit('selected');
};

const selectedGateway = ref(null);
</script>

<template>
    <div>
        <div
            v-if="! data.available_gateways.length"
            class="py-5 flex items-center justify-center sm:text-xl text-xl text-base-content sm:mb-0 mb-3"
        >
            Доступные методы оплаты не найдены.
        </div>

        <template v-else>
            <div v-show="$page.props.flash.message && ! formGatewaySelect.processing" class="alert alert-error mb-4 text-sm" role="alert">
                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                </svg>
                <div>
                    {{ $page.props.flash.message }}
                </div>
            </div>
            <div class="relative sm:my-5 sm:text-base text-sm grid sm:grid-cols-3 grid-cols-2 gap-4 text-center">
                <div v-show="formGatewaySelect.processing" role="status" class="absolute -translate-x-1/2 -translate-y-1/2 top-2/4 left-1/2 z-20">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <span class="sr-only">Loading...</span>
                </div>
                <div v-show="formGatewaySelect.processing" class="absolute w-full h-full bg-base-200/90 rounded-xl z-10"></div>
                <div
                    v-for="gateway in data.available_gateways"
                    class="relative text-base-content border border-base-300 rounded-xl cursor-pointer hover:border-primary/70"
                    @click="selectedGateway = gateway.id"
                    :class="selectedGateway === gateway.id ? 'border border-primary/70' : ''"
                >
                    <div v-if="selectedGateway === gateway.id" class="absolute top-1 right-1">
                        <svg class="w-6 h-6 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                        </svg>
                    </div>
                    <div class="mx-3 my-4">
                        <div class="flex justify-center">
                            <GatewayLogo
                                :img_path="gateway.logo_path"
                                class="w-14 h-14 text-base-content/40"
                            />
                        </div>
                        <div class="text-sm truncate mt-3">
                            {{gateway.name}}
                        </div>
<!--                        <div class="text-gray-400 dark:text-gray-500 text-xs">
                            Комиссия: {{ gateway.commission }}%
                        </div>-->
                    </div>
                </div>
            </div>

            <div class="mt-5 sm:pb-3">
                <MainButton
                    text="Выбрать"
                    :disabled="! selectedGateway || formGatewaySelect.processing"
                    @click.prevent="submitGatewaySelect"
                />
            </div>
        </template>
    </div>
</template>

<style scoped>

</style>
