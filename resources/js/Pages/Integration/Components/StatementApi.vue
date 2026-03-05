<script setup>
import { computed, reactive, ref } from 'vue';
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

const ordersForm = ref({
    merchant_id: '',
    per_page: 20,
    page: 1,
    sort: 'new',
});

const payoutsForm = ref({
    merchant_id: '',
    per_page: 20,
    page: 1,
    sort: 'new',
});

const responses = reactive({
    orders: { response: null, error: null },
    payouts: { response: null, error: null },
});

const handleRequest = async (key, endpoint, payload) => {
    responses[key].response = null;
    responses[key].error = null;

    const result = await props.executeRequest('GET', endpoint, payload);

    if (result.success) {
        responses[key].response = result.data;
    } else {
        responses[key].error = result.error;
    }
};

const clearResponse = (key) => {
    responses[key].response = null;
    responses[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="alert bg-base-100 shadow">
            <div>
                <h3 class="font-semibold text-base-content">API выписок</h3>
                <p class="text-sm text-base-content/70">
                    Получайте списки сделок и выплат по всем своим мерчантам или по конкретному мерчанту.
                    Параметр <code class="px-1 rounded bg-base-200 text-xs">per_page</code> ограничен 100 записями.
                </p>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Список сделок</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/statements/orders</p>

                        <div class="grid gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">merchant_id</span>
                                </label>
                                <select v-model="ordersForm.merchant_id" class="select select-bordered w-full">
                                    <option value="">Все мерчанты</option>
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
                                    <span class="label-text">sort</span>
                                </label>
                                <select v-model="ordersForm.sort" class="select select-bordered w-full">
                                    <option value="new">Сначала новые</option>
                                    <option value="old">Сначала старые</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">per_page</span>
                                </label>
                                <input
                                    v-model.number="ordersForm.per_page"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="input input-bordered w-full"
                                    placeholder="20"
                                >
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">page</span>
                                </label>
                                <input
                                    v-model.number="ordersForm.page"
                                    type="number"
                                    min="1"
                                    class="input input-bordered w-full"
                                    placeholder="1"
                                >
                            </div>
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading"
                                @click="handleRequest('orders', 'statements/orders', ordersForm)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="responses.orders.response"
                            :response-error="responses.orders.error"
                            @clear="clearResponse('orders')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Список выплат</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/statements/payouts</p>

                        <div class="grid gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">merchant_id</span>
                                </label>
                                <select v-model="payoutsForm.merchant_id" class="select select-bordered w-full">
                                    <option value="">Все мерчанты</option>
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
                                    <span class="label-text">sort</span>
                                </label>
                                <select v-model="payoutsForm.sort" class="select select-bordered w-full">
                                    <option value="new">Сначала новые</option>
                                    <option value="old">Сначала старые</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">per_page</span>
                                </label>
                                <input
                                    v-model.number="payoutsForm.per_page"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="input input-bordered w-full"
                                    placeholder="20"
                                >
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">page</span>
                                </label>
                                <input
                                    v-model.number="payoutsForm.page"
                                    type="number"
                                    min="1"
                                    class="input input-bordered w-full"
                                    placeholder="1"
                                >
                            </div>
                        </div>

                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary"
                                :disabled="loading"
                                @click="handleRequest('payouts', 'statements/payouts', payoutsForm)"
                            >
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>

                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="responses.payouts.response"
                            :response-error="responses.payouts.error"
                            @clear="clearResponse('payouts')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
