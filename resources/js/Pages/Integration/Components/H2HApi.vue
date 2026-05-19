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
const DEFAULT_MAX_WAIT_MS = '30000';

// H2H API формы
const h2hOrderForm = ref({
    external_id: `test_h2h_${Date.now()}`,
    client_id: makeTestClientId(),
    amount: '1000',
    manual_control_acquiring: false,
    payment_gateway: '',
    currency: 'rub',
    rate: '',
    payment_detail_type: '',
    card_number: '',
    expiry_month: '',
    expiry_year: '',
    cvc: '',
    cardholder_name: '',
    merchant_id: initialMerchantId,
    callback_url: '',
});

const h2hGetOrderForm = ref({
    order_id: ''
});

const h2hCancelOrderForm = ref({
    order_id: ''
});

const h2hConfirmationCodeForm = ref({
    order_id: '',
    confirmation_code: '',
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
    sendConfirmationCode: {
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

watch(
    () => h2hOrderForm.value.manual_control_acquiring,
    (isEnabled) => {
        if (isEnabled) {
            h2hOrderForm.value.payment_gateway = '';
            h2hOrderForm.value.payment_detail_type = 'card';
            return;
        }

        h2hOrderForm.value.card_number = '';
        h2hOrderForm.value.expiry_month = '';
        h2hOrderForm.value.expiry_year = '';
        h2hOrderForm.value.cvc = '';
        h2hOrderForm.value.cardholder_name = '';
    }
);

const isManualControlCardDataValid = computed(() => {
    if (!h2hOrderForm.value.manual_control_acquiring) {
        return true;
    }

    const hasCardNumber = !!String(h2hOrderForm.value.card_number || '').trim();
    const hasMonth = !!String(h2hOrderForm.value.expiry_month || '').trim();
    const hasYear = !!String(h2hOrderForm.value.expiry_year || '').trim();
    const hasCvc = !!String(h2hOrderForm.value.cvc || '').trim();

    return hasCardNumber && hasMonth && hasYear && hasCvc;
});

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

const getCreateOrderPayload = () => {
    const basePayload = {...h2hOrderForm.value};

    if (!h2hOrderForm.value.manual_control_acquiring) {
        delete basePayload.card_number;
        delete basePayload.expiry_month;
        delete basePayload.expiry_year;
        delete basePayload.cvc;
        delete basePayload.cardholder_name;
    }

    return basePayload;
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
                                <label class="label cursor-pointer justify-start gap-3">
                                    <input v-model="h2hOrderForm.manual_control_acquiring" type="checkbox" class="checkbox checkbox-primary">
                                    <span class="label-text">manual_control_acquiring</span>
                                </label>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_gateway</span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.payment_gateway"
                                    :disabled="h2hOrderForm.manual_control_acquiring"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="sberbank"
                                >
                                <label v-if="h2hOrderForm.manual_control_acquiring" class="label">
                                    <span class="label-text-alt text-base-content/60">В режиме MCA поле недоступно</span>
                                </label>
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">currency</span>
                                </label>
                                <input v-model="h2hOrderForm.currency" type="text" class="input input-bordered w-full" placeholder="rub">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">rate</span>
                                </label>
                                <input v-model="h2hOrderForm.rate" type="text" class="input input-bordered w-full" placeholder="95.12345678">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">payment_detail_type</span>
                                </label>
                                <select
                                    v-model="h2hOrderForm.payment_detail_type"
                                    :disabled="h2hOrderForm.manual_control_acquiring"
                                    class="select select-bordered w-full"
                                >
                                    <option value="">Не указано</option>
                                    <option value="card">card</option>
                                    <option value="phone">phone</option>
                                    <option value="mobile_commerce">mobile_commerce</option>
                                    <option value="account_number">account_number</option>
                                    <option value="iban_uah">iban_uah</option>
                                    <option value="nspk">nspk</option>
                                    <option value="e-com">e-com</option>
                                </select>
                                <label v-if="h2hOrderForm.manual_control_acquiring" class="label">
                                    <span class="label-text-alt text-base-content/60">В этом режиме используется только card</span>
                                </label>
                            </div>
                            <div class="form-control grid" v-if="h2hOrderForm.manual_control_acquiring">
                                <label class="label">
                                    <span class="label-text">card_number <span class="text-error" v-if="h2hOrderForm.manual_control_acquiring">*</span></span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.card_number"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="4444333322221111"
                                >
                            </div>
                            <div class="form-control grid" v-if="h2hOrderForm.manual_control_acquiring">
                                <label class="label">
                                    <span class="label-text">expiry_month <span class="text-error" v-if="h2hOrderForm.manual_control_acquiring">*</span></span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.expiry_month"
                                    type="number"
                                    min="1"
                                    max="12"
                                    class="input input-bordered w-full"
                                    placeholder="12"
                                >
                            </div>
                            <div class="form-control grid" v-if="h2hOrderForm.manual_control_acquiring">
                                <label class="label">
                                    <span class="label-text">expiry_year <span class="text-error" v-if="h2hOrderForm.manual_control_acquiring">*</span></span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.expiry_year"
                                    type="number"
                                    min="2000"
                                    class="input input-bordered w-full"
                                    placeholder="2029"
                                >
                            </div>
                            <div class="form-control grid" v-if="h2hOrderForm.manual_control_acquiring">
                                <label class="label">
                                    <span class="label-text">cvc <span class="text-error" v-if="h2hOrderForm.manual_control_acquiring">*</span></span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.cvc"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="123"
                                >
                                <label class="label">
                                    <span class="label-text-alt text-base-content/60">Код проверки карты (CVV/CVC)</span>
                                </label>
                            </div>
                            <div class="form-control grid" v-if="h2hOrderForm.manual_control_acquiring">
                                <label class="label">
                                    <span class="label-text">cardholder_name</span>
                                </label>
                                <input
                                    v-model="h2hOrderForm.cardholder_name"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="IVAN IVANOV"
                                >
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
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button @click="handleH2HRequest('createOrder', 'POST', 'h2h/order', getCreateOrderPayload(), { 'X-Max-Wait-Ms': DEFAULT_MAX_WAIT_MS })"
                                    class="btn btn-primary" :disabled="loading || !isManualControlCardDataValid">
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
                        <h3 class="card-title mb-4">Отправить код подтверждения</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/h2h/order/{order_id}/confirmation-code</p>

                        <div class="grid grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">order_id <span class="text-error">*</span></span>
                                </label>
                                <input v-model="h2hConfirmationCodeForm.order_id" type="text" class="input input-bordered w-full" placeholder="UUID сделки">
                            </div>

                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">confirmation_code <span class="text-error">*</span></span>
                                </label>
                                <input v-model="h2hConfirmationCodeForm.confirmation_code" type="text" class="input input-bordered w-full" placeholder="123456">
                            </div>
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button
                                @click="handleH2HRequest('sendConfirmationCode', 'POST', `h2h/order/${h2hConfirmationCodeForm.order_id}/confirmation-code`, { confirmation_code: h2hConfirmationCodeForm.confirmation_code })"
                                class="btn btn-primary"
                                :disabled="loading || !h2hConfirmationCodeForm.order_id || !h2hConfirmationCodeForm.confirmation_code"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="h2hResponses.sendConfirmationCode.response"
                            :response-error="h2hResponses.sendConfirmationCode.error"
                            @clear="clearH2HResponse('sendConfirmationCode')"
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

