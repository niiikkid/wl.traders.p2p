<script setup>
import { ref, reactive } from 'vue';
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

// Wallet формы
const walletWithdrawForm = ref({
    amount: '',
    address: '',
    network: 'bsc'
});

const walletResponses = reactive({
    balance: {
        response: null,
        error: null
    },
    withdraw: {
        response: null,
        error: null
    }
});

const isWithdrawIntegrationEnabled = false; // включится после подключения сервиса выплат

const handleWalletRequest = async (key, method, endpoint, payload = {}, headers = {}) => {
    if (key === 'withdraw' && !isWithdrawIntegrationEnabled) {
        walletResponses[key].response = null;
        const message = 'Автовывод доступен только при подключении сервиса выплат.';
        walletResponses[key].error = {
            message,
            rawBody: message
        };
        return;
    }

    walletResponses[key].response = null;
    walletResponses[key].error = null;

    const result = await props.executeRequest(method, endpoint, payload, headers);

    if (result.success) {
        walletResponses[key].response = result.data;
    } else {
        walletResponses[key].error = result.error;
    }
};

const clearWalletResponse = (key) => {
    walletResponses[key].response = null;
    walletResponses[key].error = null;
};
</script>

<template>
    <div class="space-y-6">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Получить баланс</h3>
                        <p class="text-sm text-base-content/70 mb-4">GET /api/wallet/balance</p>

                        <div class="card-actions justify-end">
                            <button @click="handleWalletRequest('balance', 'GET', 'wallet/balance')"
                                    class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                                Отправить запрос
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="walletResponses.balance.response"
                            :response-error="walletResponses.balance.error"
                            @clear="clearWalletResponse('balance')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-y-6 xl:gap-x-6">
                    <div class="space-y-4 col-span-1">
                        <h3 class="card-title mb-4">Создать запрос на вывод</h3>
                        <p class="text-sm text-base-content/70 mb-4">POST /api/wallet/withdraw</p>

                        <div class="alert alert-warning text-sm">
                            Автовывод доступен только при интеграции с сервисом выплат. Обратитесь к менеджеру, чтобы
                            подключить провайдера.
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">amount <span class="text-error">*</span></span>
                                </label>
                                <input v-model="walletWithdrawForm.amount" type="number" class="input input-bordered w-full" placeholder="1000">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">address <span class="text-error">*</span></span>
                                </label>
                                <input v-model="walletWithdrawForm.address" type="text" class="input input-bordered w-full" placeholder="Адрес кошелька">
                            </div>
                            <div class="form-control grid">
                                <label class="label">
                                    <span class="label-text">network <span class="text-error">*</span></span>
                                </label>
                                <select v-model="walletWithdrawForm.network" class="select select-bordered w-full">
                                    <option value="bsc">BSC</option>
                                    <option value="arb">ARB</option>
                                    <option value="trx">TRX</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-actions justify-end mt-4">
                            <button
                                class="btn btn-primary btn-disabled"
                                disabled
                                title="Доступно только после интеграции с сервисом выплат"
                            >
                                Недоступно
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2 xl:border-l xl:pl-6 xl:border-base-300">
                        <ApiResponse
                            :response="walletResponses.withdraw.response"
                            :response-error="walletResponses.withdraw.error"
                            @clear="clearWalletResponse('withdraw')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

