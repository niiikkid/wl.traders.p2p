<script setup>
import { computed, reactive, ref, watch } from 'vue';
import ApiResponse from './ApiResponse.vue';

const props = defineProps({
    executeRequest: {
        type: Function,
        required: true,
    },
    loading: {
        type: Boolean,
        required: true,
    },
    merchantId: {
        type: String,
        default: '',
    },
    merchants: {
        type: Array,
        default: () => [],
    },
});

const merchantOptions = computed(() => props.merchants);
const resolveDefaultMerchant = () => props.merchantId || merchantOptions.value[0]?.uuid || '';

const payoutCreateForm = ref({
    merchant_id: resolveDefaultMerchant(),
    external_id: `test_payout_${Date.now()}`,
    amount: '10000',
    payout_method_type: 'sbp',
    payment_gateway: '',
    currency: '',
    requisites: '',
    initials: '',
    bank_name: '',
    callback_url: '',
});

watch(
    () => [props.merchantId, props.merchants],
    () => {
        if (!payoutCreateForm.value.merchant_id) {
            payoutCreateForm.value.merchant_id = resolveDefaultMerchant();
        }
    },
    { immediate: true }
);

watch(
    () => payoutCreateForm.value.payment_gateway,
    (value) => {
        if (value) {
            payoutCreateForm.value.currency = '';
            payoutCreateForm.value.bank_name = '';
        }
    }
);

watch(
    () => payoutCreateForm.value.currency,
    (value) => {
        if (value) {
            payoutCreateForm.value.payment_gateway = '';
        }
    }
);

watch(
    () => payoutCreateForm.value.bank_name,
    (value) => {
        if (value) {
            payoutCreateForm.value.payment_gateway = '';
        }
    }
);

const payoutGetForm = ref({ payout_id: '' });
const payoutCancelForm = ref({ payout_id: '' });
const payoutConfirmForm = ref({ payout_id: '' });
const payoutReceiptForm = ref({ payout_id: '' });

const payoutResponses = reactive({
    create: { response: null, error: null },
    show: { response: null, error: null },
    cancel: { response: null, error: null },
    confirm: { response: null, error: null },
    receipt: { response: null, error: null },
});

const handlePayoutRequest = async (key, method, endpoint, payload = {}) => {
    payoutResponses[key].response = null;
    payoutResponses[key].error = null;

    const result = await props.executeRequest(method, endpoint, payload);

    if (result.success) {
        payoutResponses[key].response = result.data;
    } else {
        payoutResponses[key].error = result.error;
    }
};

const clearPayoutResponse = (key) => {
    payoutResponses[key].response = null;
    payoutResponses[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="alert bg-base-100 shadow">
            <div>
                <h3 class="font-semibold text-base-content">API выплат</h3>
                <p class="text-sm text-base-content/70">
                    Для тестирования используйте Access-Token мерчанта. Укажите либо платёжный метод
                    (<code class="px-1 rounded bg-base-200 text-xs">payment_gateway</code>), либо валюту
                    (<code class="px-1 rounded bg-base-200 text-xs">currency</code>).
                </p>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Создать выплату</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/payouts</p>

                        <div class="grid gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">merchant_id <span class="text-error">*</span></span>
                                </label>
                                <select v-model="payoutCreateForm.merchant_id" class="select select-bordered w-full">
                                    <option value="">Выберите мерчанта</option>
                                    <option
                                        v-for="merchant in merchantOptions"
                                        :key="merchant.uuid"
                                        :value="merchant.uuid"
                                    >
                                        {{ merchant.name || merchant.uuid }}
                                    </option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">external_id <span class="text-error">*</span></span>
                                </label>
                                <input v-model="payoutCreateForm.external_id" type="text" class="input input-bordered w-full" placeholder="ID выплаты во внешней системе">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">amount <span class="text-error">*</span></span>
                                </label>
                                <input v-model="payoutCreateForm.amount" type="number" class="input input-bordered w-full" placeholder="10000">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">payout_method_type <span class="text-error">*</span></span>
                                </label>
                                <select v-model="payoutCreateForm.payout_method_type" class="select select-bordered w-full">
                                    <option value="sbp">SBP</option>
                                    <option value="card">CARD</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">payment_gateway</span>
                                </label>
                                <input v-model="payoutCreateForm.payment_gateway" :disabled="!!payoutCreateForm.currency || !!payoutCreateForm.bank_name" type="text" class="input input-bordered w-full" placeholder="Код из списка платёжных методов">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">currency</span>
                                </label>
                                <input v-model="payoutCreateForm.currency" :disabled="!!payoutCreateForm.payment_gateway" type="text" class="input input-bordered w-full" placeholder="RUB">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">requisites <span class="text-error">*</span></span>
                                </label>
                                <input v-model="payoutCreateForm.requisites" type="text" class="input input-bordered w-full" placeholder="7926XXXXXXX или 4890 XXXX XXXX XXXX">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">initials <span class="text-error">*</span></span>
                                </label>
                                <input v-model="payoutCreateForm.initials" type="text" class="input input-bordered w-full" placeholder="ФИО получателя">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">bank_name</span>
                                </label>
                                <input v-model="payoutCreateForm.bank_name" :disabled="!!payoutCreateForm.payment_gateway" type="text" class="input input-bordered w-full" placeholder="Банк в свободной форме">
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">callback_url</span>
                                </label>
                                <input v-model="payoutCreateForm.callback_url" type="url" class="input input-bordered w-full" placeholder="https://example.com/payout-callback">
                            </div>
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading || !payoutCreateForm.merchant_id || !payoutCreateForm.external_id || (!payoutCreateForm.payment_gateway && !payoutCreateForm.currency) || !payoutCreateForm.requisites || !payoutCreateForm.initials"
                                @click="handlePayoutRequest('create', 'POST', 'payouts', payoutCreateForm)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="payoutResponses.create.response"
                            :response-error="payoutResponses.create.error"
                            @clear="clearPayoutResponse('create')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить выплату</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/payouts/{payout_id}</p>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">payout_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="payoutGetForm.payout_id" type="text" class="input input-bordered w-full" placeholder="UUID выплаты">
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading || !payoutGetForm.payout_id"
                                @click="handlePayoutRequest('show', 'GET', `payouts/${payoutGetForm.payout_id}`)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="payoutResponses.show.response"
                            :response-error="payoutResponses.show.error"
                            @clear="clearPayoutResponse('show')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Отменить выплату</h3>
                        <p class="text-sm text-base-content/70 mb-4">PATCH /api/payouts/{payout_id}/cancel</p>
                        <p class="text-sm text-base-content/70">
                            Отмена возможна только пока выплату не взял трейдер (статус <code class="bg-base-200 px-1 rounded text-xs">open</code>).
                        </p>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">payout_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="payoutCancelForm.payout_id" type="text" class="input input-bordered w-full" placeholder="UUID выплаты">
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading || !payoutCancelForm.payout_id"
                                @click="handlePayoutRequest('cancel', 'PATCH', `payouts/${payoutCancelForm.payout_id}/cancel`)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="payoutResponses.cancel.response"
                            :response-error="payoutResponses.cancel.error"
                            @clear="clearPayoutResponse('cancel')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Досрочно завершить холд</h3>
                        <p class="text-sm text-base-content/70 mb-2">PATCH /api/payouts/{payout_id}/confirm-paid</p>
                        <p class="text-sm text-base-content/70">
                            Доступно, когда трейдер уже отправил деньги (<code class="bg-base-200 px-1 rounded text-xs">sent</code>).
                            Эндпоинт мгновенно снимает холд и зачисляет USDT трейдеру.
                        </p>

                        <div class="form-control mt-2">
                            <label class="label">
                                <span class="label-text">payout_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="payoutConfirmForm.payout_id" type="text" class="input input-bordered w-full" placeholder="UUID выплаты">
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading || !payoutConfirmForm.payout_id"
                                @click="handlePayoutRequest('confirm', 'PATCH', `payouts/${payoutConfirmForm.payout_id}/confirm-paid`)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="payoutResponses.confirm.response"
                            :response-error="payoutResponses.confirm.error"
                            @clear="clearPayoutResponse('confirm')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить чек выплаты</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/payouts/{payout_id}/receipt</p>
                        <p class="text-sm text-base-content/70">
                            В ответ придёт base64-файл с указанием имени и MIME-типа. После декодирования содержимое
                            совпадает с тем, что отображается в интерфейсе.
                        </p>

                        <div class="form-control mt-2">
                            <label class="label">
                                <span class="label-text">payout_id <span class="text-error">*</span></span>
                            </label>
                            <input v-model="payoutReceiptForm.payout_id" type="text" class="input input-bordered w-full" placeholder="UUID выплаты">
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading || !payoutReceiptForm.payout_id"
                                @click="handlePayoutRequest('receipt', 'GET', `payouts/${payoutReceiptForm.payout_id}/receipt`)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="payoutResponses.receipt.response"
                            :response-error="payoutResponses.receipt.error"
                            @clear="clearPayoutResponse('receipt')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

