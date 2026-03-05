<script setup>
import { reactive } from 'vue';
import ApiResponse from './ApiResponse.vue';

const props = defineProps({
    executeRequest: {
        type: Function,
        required: true
    },
    loading: {
        type: Boolean,
        required: true
    }
});

const responseState = reactive({
    currencies: {
        response: null,
        error: null
    },
    paymentGateways: {
        response: null,
        error: null
    }
});

const handleRequest = async (key, method, endpoint) => {
    responseState[key].response = null;
    responseState[key].error = null;

    const result = await props.executeRequest(method, endpoint);

    if (result.success) {
        responseState[key].response = result.data;
    } else {
        responseState[key].error = result.error;
    }
};

const clearResponse = (key) => {
    responseState[key].response = null;
    responseState[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить доступные валюты</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/currencies</p>

                        <div class="card-actions justify-end">
                            <button @click="handleRequest('currencies', 'GET', 'currencies')"
                                    class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="responseState.currencies.response"
                            :response-error="responseState.currencies.error"
                            @clear="clearResponse('currencies')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить доступные платежные методы</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/payment-gateways</p>

                        <div class="card-actions justify-end">
                            <button @click="handleRequest('paymentGateways', 'GET', 'payment-gateways')"
                                    class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="responseState.paymentGateways.response"
                            :response-error="responseState.paymentGateways.error"
                            @clear="clearResponse('paymentGateways')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

