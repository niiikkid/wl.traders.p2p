<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import PaymentLayout from "@/Layouts/PaymentLayout.vue";
import {nextTick, onMounted, ref} from "vue";
import SupportButton from "@/Pages/PaymentLink/Components/SupportButton.vue";
import Clock from "@/Components/Clock.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import MerchantName from "@/Pages/PaymentLink/Components/MerchantName.vue";
import PaymentHeader from "@/Pages/PaymentLink/Components/PaymentHeader.vue";
import HelperModal from "@/Pages/PaymentLink/Components/HelperModal.vue";
import SelectGateway from "@/Pages/PaymentLink/Components/Stages/SelectGateway.vue";
import Payment from "@/Pages/PaymentLink/Components/Stages/Payment.vue";
import SuccessOrFail from "@/Pages/PaymentLink/Components/Stages/SuccessOrFail.vue";
import DisputeReview from "@/Pages/PaymentLink/Components/Stages/DisputeReview.vue";
import DisputeCanceled from "@/Pages/PaymentLink/Components/Stages/DisputeCanceled.vue";

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const stage = ref('payment');
const clockRef = ref(null);
const data = ref({});

const initializeClock = () => {
    nextTick(() => {
        clockRef.value.initializeClock();
    });
}

const setData = () => {
    data.value = {
        order_status: usePage().props.data.order_status,
        uuid: usePage().props.data.uuid,
        name: usePage().props.data.name,
        amount: usePage().props.data.amount,
        amount_formated: usePage().props.data.amount_formated,
        currency_symbol: usePage().props.data.currency_symbol,
        support_link: usePage().props.data.support_link,
        detail: usePage().props.data.detail,
        detail_type: usePage().props.data.detail_type,
        initials: usePage().props.data.initials,
        payment_gateway: usePage().props.data.payment_gateway,
        success_url: usePage().props.data.success_url,
        fail_url: usePage().props.data.fail_url,
        created_at: usePage().props.data.created_at,
        expires_at: usePage().props.data.expires_at,
        now: usePage().props.data.now,
        has_dispute: usePage().props.data.has_dispute,
        dispute_status: usePage().props.data.dispute_status,
        dispute_cancel_reason: usePage().props.data.dispute_cancel_reason,
        manually: usePage().props.data.manually,
        gateway_selected: usePage().props.data.gateway_selected,
        available_gateways: usePage().props.data.available_gateways,
    }
}

const checkPaid = () => {
    setInterval(async () => {
        router.reload({ only: ['data'] })
    }, 5000);
}

const setStage = () => {
    if (! data.value.gateway_selected) {
        stage.value = 'select_gateway';
    } else  if (data.value.order_status === 'pending' && ! data.value.has_dispute) {
        stage.value = 'payment';
    } else if (data.value.order_status === 'success') {
        stage.value = 'success';
    } else if (data.value.order_status === 'fail' && ! data.value.has_dispute) {
        stage.value = 'fail';
    } else if (data.value.has_dispute && data.value.dispute_status === 'pending') {
        stage.value = 'dispute_review';
    } else if (data.value.has_dispute  && data.value.dispute_status === 'canceled') {
        stage.value = 'dispute_canceled';
    }
}

setData();
setStage();

router.on('success', (event) => {
    setData();

    setStage();
})

onMounted(() => {
    setTimeout(() => {
        checkPaid();
    }, 5000)

    if (data.value.gateway_selected) {
        initializeClock();
    }
})

defineOptions({ layout: PaymentLayout });
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-base-200">
        <Head title="Платеж" />

        <div
            class="w-full m-8"
            :class="stage === 'select_gateway' ? 'sm:max-w-lg' : 'sm:max-w-md'"
        >
            <div class="flex justify-between items-center px-2 sm:px-0 mb-2">
                <MerchantName :name="data.name"/>
                <SupportButton :support_link="data.support_link"/>
            </div>

            <PaymentHeader :stage="stage" :data="data">
                <template v-slot:clock>
                    <Clock :expires_at="data.expires_at" :now="data.now" ref="clockRef"/>
                </template>
            </PaymentHeader>

            <div class="card bg-base-100 shadow-md mt-4 sm:mx-0 mx-2">
                <div class="card-body sm:px-6 px-3 py-4">
                    <div>
                    <SelectGateway
                        v-if="stage === 'select_gateway'"
                        :data="data"
                        @selected="initializeClock"
                    />

                    <Payment
                        v-show="stage === 'payment'"
                        :data="data"
                    />

                    <SuccessOrFail
                        v-if="stage === 'success' || stage === 'fail'"
                        :stage="stage"
                        :data="data"
                    />

                    <DisputeReview
                        v-if="stage === 'dispute_review'"
                    />

                    <DisputeCanceled
                        v-if="stage === 'dispute_canceled'"
                        :data="data"
                    />

                    <HelperModal :data="data"/>
                    </div>
                </div>
            </div>
            <div class="flex justify-center mt-5">
                <ThemeToggle/>
            </div>
<!--            <StageSwitcher :stage="stage" @setStage="stage = $event"/>-->
        </div>
    </div>
</template>
