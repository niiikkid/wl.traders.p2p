<script setup>
import { ref, reactive, computed, watch } from 'vue';
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
    },
    receiptTemplate: {
        type: String,
        default: ''
    }
});

const merchantOptions = computed(() => props.merchants);
const initialMerchantId = props.merchantId || merchantOptions.value[0]?.uuid || '';
const makeTestClientId = () => `test-${Math.floor(Math.random() * 1000000)}`;

// H2H API формы
const h2hOrderForm = ref({
    external_id: `test_h2h_${Date.now()}`,
    client_id: makeTestClientId(),
    amount: '1000',
    payment_gateway: '',
    currency: 'rub',
    payment_detail_type: '',
    merchant_id: initialMerchantId,
    callback_url: '',
    'X-Max-Wait-Ms': '30000'
});

const h2hGetOrderForm = ref({
    order_id: ''
});

const h2hCancelOrderForm = ref({
    order_id: ''
});

const h2hDisputeForm = ref({
    order_id: '',
    receipt: ''
});

const h2hGetDisputeForm = ref({
    order_id: ''
});

const h2hResponses = reactive({
    createOrder: {
        response: null,
        error: null
    },
    getOrder: {
        response: null,
        error: null
    },
    cancelOrder: {
        response: null,
        error: null
    },
    createDispute: {
        response: null,
        error: null
    },
    getDispute: {
        response: null,
        error: null
    }
});

watch(
    () => props.receiptTemplate,
    (value) => {
        if (!value) {
            return;
        }

        const disputeForm = h2hDisputeForm.value;
        if (!disputeForm.receipt) {
            disputeForm.receipt = value;
        }
    },
    { immediate: true }
);

const handleH2HRequest = async (key, method, endpoint, payload = {}, headers = {}) => {
    h2hResponses[key].response = null;
    h2hResponses[key].error = null;

    const result = await props.executeRequest(method, endpoint, payload, headers);

    if (result.success) {
        h2hResponses[key].response = result.data;
    } else {
        h2hResponses[key].error = result.error;
    }
};

const clearH2HResponse = (key) => {
    h2hResponses[key].response = null;
    h2hResponses[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Создать сделку</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/h2h/order</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">external_id <span class="text-error">*</span></span>
                                </label>
                                <input v-model="h2hOrderForm.external_id" type="text" class="input input-bordered w-full" placeholder="Уникальный ID сделки">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">client_id</span>
                                </label>
                                <input v-model="h2hOrderForm.client_id" type="text" class="input input-bordered w-full" placeholder="test-123456">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">amount <span class="text-error">*</span></span>
                                </label>
                                <input v-model="h2hOrderForm.amount" type="number" class="input input-bordered w-full" placeholder="1000">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_gateway</span>
                                </label>
                                <input v-model="h2hOrderForm.payment_gateway" type="text" class="input input-bordered w-full" placeholder="sberbank">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">currency</span>
                                </label>
                                <input v-model="h2hOrderForm.currency" type="text" class="input input-bordered w-full" placeholder="rub">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_detail_type</span>
                                </label>
                                <select v-model="h2hOrderForm.payment_detail_type" class="select select-bordered w-full">
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
                                <select v-model="h2hOrderForm.merchant_id" class="select select-bordered w-full mb-2">
                                    <option value="">Выберите мерчант</option>
                                    <option
                                        v-for="merchant in merchantOptions"
                                        :key="merchant.uuid"
                                        :value="merchant.uuid"
                                    >
                                        {{ merchant.name || merchant.uuid }}
                                    </option>
                                </select>
                                <input v-model="h2hOrderForm.merchant_id" type="text" class="input input-bordered w-full" placeholder="UUID мерчанта">
                                <label v-if="!merchantOptions.length" class="label">
                                    <span class="label-text-alt text-base-content/60">Нет доступных мерчантов</span>
                                </label>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">callback_url</span>
                                </label>
                                <input v-model="h2hOrderForm.callback_url" type="url" class="input input-bordered w-full" placeholder="https://example.com/callback">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">X-Max-Wait-Ms</span>
                                </label>
                                <input v-model="h2hOrderForm['X-Max-Wait-Ms']" type="number" class="input input-bordered w-full" placeholder="30000">
                            </div>
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('createOrder', 'POST', 'h2h/order', Object.fromEntries(Object.entries(h2hOrderForm).filter(([key]) => key !== 'X-Max-Wait-Ms')), { 'X-Max-Wait-Ms': h2hOrderForm['X-Max-Wait-Ms'] })"
                                    class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.createOrder.response"
                            :response-error="h2hResponses.createOrder.error"
                            @clear="clearH2HResponse('createOrder')"
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
                        <p class="text-sm text-base-content/70 mb-4">GET /api/h2h/order/{order_id}</p>

                        <div class="grid grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">order_id</span>
                                </label>
                                <input v-model="h2hGetOrderForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                            </div>
                            <!-- Оставлено только поле order_id -->
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('getOrder', 'GET', `h2h/order/${h2hGetOrderForm.order_id}`)"
                                    class="btn btn-primary" :disabled="loading || !h2hGetOrderForm.order_id">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.getOrder.response"
                            :response-error="h2hResponses.getOrder.error"
                            @clear="clearH2HResponse('getOrder')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Закрыть сделку</h3>
                        <p class="text-sm text-base-content/70 mb-4">PATCH /api/h2h/order/{order_id}/cancel</p>

                        <div class="form-control grid">
                            <label class="label">
                                <span class="label-text">order_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="h2hCancelOrderForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('cancelOrder', 'PATCH', `h2h/order/${h2hCancelOrderForm.order_id}/cancel`)"
                                    class="btn btn-primary" :disabled="loading || !h2hCancelOrderForm.order_id">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.cancelOrder.response"
                            :response-error="h2hResponses.cancelOrder.error"
                            @clear="clearH2HResponse('cancelOrder')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Открыть спор</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/h2h/order/{order_id}/dispute</p>

                        <div class="grid grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">order_id <span class="text-error">*</span></span>
                                </label>
                                <input v-model="h2hDisputeForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">receipt <span class="text-error">*</span></span>
                                </label>
                                <textarea v-model="h2hDisputeForm.receipt" class="textarea textarea-bordered w-full" placeholder="Base64 изображения (jpeg, jpg, png, pdf)"></textarea>
                                <label class="label">
                                    <span class="label-text-alt">Изображение в base64 (до 5МБ)</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('createDispute', 'POST', `h2h/order/${h2hDisputeForm.order_id}/dispute`, { receipt: h2hDisputeForm.receipt })"
                                    class="btn btn-primary" :disabled="loading || !h2hDisputeForm.order_id || !h2hDisputeForm.receipt">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.createDispute.response"
                            :response-error="h2hResponses.createDispute.error"
                            @clear="clearH2HResponse('createDispute')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить спор</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/h2h/order/{order_id}/dispute</p>

                        <div class="form-control grid">
                            <label class="label">
                                <span class="label-text">order_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="h2hGetDisputeForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('getDispute', 'GET', `h2h/order/${h2hGetDisputeForm.order_id}/dispute`)"
                                    class="btn btn-primary" :disabled="loading || !h2hGetDisputeForm.order_id">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.getDispute.response"
                            :response-error="h2hResponses.getDispute.error"
                            @clear="clearH2HResponse('getDispute')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

