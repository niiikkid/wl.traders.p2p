<script setup>
import { ref, reactive, computed } from 'vue';
import ApiResponse from './ApiResponse.vue';

const props = defineProps({
    executeRequest: {
        type: Function,
        required: true
    },
    loading: {
        type: Boolean,
        required: true
    },
    merchantId: {
        type: String,
        default: ''
    },
    merchants: {
        type: Array,
        default: () => []
    }
});

const merchantOptions = computed(() => props.merchants);
const initialMerchantId = props.merchantId || merchantOptions.value[0]?.uuid || '';
const makeTestClientId = () => `test-${Math.floor(Math.random() * 1000000)}`;

// Merchant API формы
const merchantOrderForm = ref({
    external_id: `test_${Date.now()}`,
    client_id: makeTestClientId(),
    amount: '1000',
    payment_gateway: '',
    currency: 'rub',
    payment_detail_type: '',
    merchant_id: initialMerchantId,
    callback_url: '',
    success_url: '',
    fail_url: '',
    manually: '',
    'X-Max-Wait-Ms': '30000'
});

const merchantGetOrderForm = ref({
    order_id: ''
});

const merchantResponses = reactive({
    createOrder: {
        response: null,
        error: null
    },
    getOrder: {
        response: null,
        error: null
    }
});

const handleMerchantRequest = async (key, method, endpoint, payload = {}, headers = {}) => {
    merchantResponses[key].response = null;
    merchantResponses[key].error = null;

    const result = await props.executeRequest(method, endpoint, payload, headers);

    if (result.success) {
        merchantResponses[key].response = result.data;
    } else {
        merchantResponses[key].error = result.error;
    }
};

const clearMerchantResponse = (key) => {
    merchantResponses[key].response = null;
    merchantResponses[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Создать сделку</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/merchant/order</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">external_id <span class="text-error">*</span></span>
                                </label>
                                <input v-model="merchantOrderForm.external_id" type="text" class="input input-bordered w-full" placeholder="Уникальный ID сделки">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">client_id</span>
                                </label>
                                <input v-model="merchantOrderForm.client_id" type="text" class="input input-bordered w-full" placeholder="test-123456">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">amount <span class="text-error">*</span></span>
                                </label>
                                <input v-model="merchantOrderForm.amount" type="number" class="input input-bordered w-full" placeholder="1000">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_gateway</span>
                                </label>
                                <input v-model="merchantOrderForm.payment_gateway" type="text" class="input input-bordered w-full" placeholder="sberbank">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">currency</span>
                                </label>
                                <input v-model="merchantOrderForm.currency" type="text" class="input input-bordered w-full" placeholder="rub">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_detail_type</span>
                                </label>
                                <select v-model="merchantOrderForm.payment_detail_type" class="select select-bordered w-full">
                                    <option value="">Не указано</option>
                                    <option value="card">card</option>
                                    <option value="phone">phone</option>
                                    <option value="mobile_commerce">mobile_commerce</option>
                                    <option value="account_number">account_number</option>
                                    <option value="nspk">nspk</option>
                                </select>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">merchant_id <span class="text-error">*</span></span>
                                </label>
                                <select v-model="merchantOrderForm.merchant_id" class="select select-bordered w-full mb-2">
                                    <option value="">Выберите мерчант</option>
                                    <option
                                        v-for="merchant in merchantOptions"
                                        :key="merchant.uuid"
                                        :value="merchant.uuid"
                                    >
                                        {{ merchant.name || merchant.uuid }}
                                    </option>
                                </select>
                                <input v-model="merchantOrderForm.merchant_id" type="text" class="input input-bordered w-full" placeholder="UUID мерчанта">
                                <label v-if="!merchantOptions.length" class="label">
                                    <span class="label-text-alt text-base-content/60">Нет доступных мерчантов</span>
                                </label>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">callback_url</span>
                                </label>
                                <input v-model="merchantOrderForm.callback_url" type="url" class="input input-bordered w-full" placeholder="https://example.com/callback">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">success_url</span>
                                </label>
                                <input v-model="merchantOrderForm.success_url" type="url" class="input input-bordered w-full" placeholder="https://example.com/success">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">fail_url</span>
                                </label>
                                <input v-model="merchantOrderForm.fail_url" type="url" class="input input-bordered w-full" placeholder="https://example.com/fail">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">manually</span>
                                </label>
                                <select v-model="merchantOrderForm.manually" class="select select-bordered w-full">
                                    <option value="">Не указано</option>
                                    <option value="1">1 (Да)</option>
                                </select>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">X-Max-Wait-Ms</span>
                                </label>
                                <input v-model="merchantOrderForm['X-Max-Wait-Ms']" type="number" class="input input-bordered w-full" placeholder="30000">
                            </div>
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleMerchantRequest('createOrder', 'POST', 'merchant/order', Object.fromEntries(Object.entries(merchantOrderForm).filter(([key]) => key !== 'X-Max-Wait-Ms')), { 'X-Max-Wait-Ms': merchantOrderForm['X-Max-Wait-Ms'] })"
                                    class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="merchantResponses.createOrder.response"
                            :response-error="merchantResponses.createOrder.error"
                            @clear="clearMerchantResponse('createOrder')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить сделку</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/merchant/order/{order_id}</p>

                        <div class="grid grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">order_id</span>
                                </label>
                                <input v-model="merchantGetOrderForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                            </div>
                            <!-- Удалены поля merchant_id и external_id; остался только order_id -->
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleMerchantRequest('getOrder', 'GET', `merchant/order/${merchantGetOrderForm.order_id}`)"
                                    class="btn btn-primary" :disabled="loading || !merchantGetOrderForm.order_id">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="merchantResponses.getOrder.response"
                            :response-error="merchantResponses.getOrder.error"
                            @clear="clearMerchantResponse('getOrder')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

