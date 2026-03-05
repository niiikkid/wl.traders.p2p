<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {useClipboard} from "@vueuse/core";
import {ref, onMounted, onBeforeUnmount, nextTick, unref} from 'vue';
import axios from 'axios';
import ApiDocumentation from '@/Pages/Integration/Components/ApiDocumentation.vue';
import MerchantApi from '@/Pages/Integration/Components/MerchantApi.vue';
import H2HApi from '@/Pages/Integration/Components/H2HApi.vue';
import WalletApi from '@/Pages/Integration/Components/WalletApi.vue';
import CommonApi from '@/Pages/Integration/Components/CommonApi.vue';
import PayoutApi from '@/Pages/Integration/Components/PayoutApi.vue';
import StatementApi from '@/Pages/Integration/Components/StatementApi.vue';
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";

const pageProps = usePage().props;
const user = pageProps.auth.user;
const token = ref(pageProps.token ?? '');
const merchantId = pageProps.merchantId;
const merchants = pageProps.merchants ?? [];

const { text, copy, copied } = useClipboard();
const modalStore = useModalStore();

const DEFAULT_TAB = 'merchant';
const VALID_TABS = ['merchant', 'h2h', 'payouts', 'statements', 'wallet', 'common', 'docs'];
const hasWindow = typeof window !== 'undefined';

const getTabFromUrl = () => {
    if (!hasWindow) {
        return DEFAULT_TAB;
    }

    const hash = window.location.hash;
    if (hash) {
        return 'docs';
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    return VALID_TABS.includes(tabParam) ? tabParam : DEFAULT_TAB;
};

const activeTab = ref(getTabFromUrl());
const loading = ref(false);
const receiptTemplate = ref('');
const regenerating = ref(false);

const scrollToHash = (hashOverride = null) => {
    if (!hasWindow) {
        return;
    }

    const hash = hashOverride ?? window.location.hash;

    if (!hash) {
        return;
    }

    const target = document.querySelector(hash);
    if (target) {
        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
};

const updateUrl = (tab, hashValue = null) => {
    if (!hasWindow) {
        return;
    }

    const url = new URL(window.location.href);

    if (tab === DEFAULT_TAB) {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', tab);
    }

    if (tab === 'docs') {
        if (hashValue) {
            url.hash = hashValue.startsWith('#') ? hashValue : `#${hashValue.replace('#', '')}`;
        } else if (window.location.hash) {
            url.hash = window.location.hash;
        }
    } else {
        url.hash = '';
    }

    window.history.replaceState({}, '', url.toString());
};

const setActiveTab = (tab) => {
    if (!VALID_TABS.includes(tab)) {
        return;
    }

    if (activeTab.value === tab) {
        if (tab === 'docs') {
            scrollToHash();
        }
        return;
    }

    activeTab.value = tab;
    updateUrl(tab);

    if (tab === 'docs') {
        nextTick(() => scrollToHash());
    }
};

const handleHashChange = () => {
    if (!hasWindow) {
        return;
    }

    if (window.location.hash) {
        if (activeTab.value !== 'docs') {
            activeTab.value = 'docs';
        }
        updateUrl('docs', window.location.hash);
        nextTick(() => scrollToHash());
    }
};

const handlePopState = () => {
    const newTab = getTabFromUrl();
    if (newTab !== activeTab.value) {
        activeTab.value = newTab;
    }

    updateUrl(newTab);

    if (newTab === 'docs') {
        nextTick(() => scrollToHash());
    }
};

onMounted(() => {
    activeTab.value = getTabFromUrl();
    updateUrl(activeTab.value);

    if (activeTab.value === 'docs') {
        nextTick(() => scrollToHash());
    }

    loadReceiptTemplate();

    window.addEventListener('hashchange', handleHashChange);
    window.addEventListener('popstate', handlePopState);
});

onBeforeUnmount(() => {
    if (!hasWindow) {
        return;
    }
    window.removeEventListener('hashchange', handleHashChange);
    window.removeEventListener('popstate', handlePopState);
});

const sanitizeObject = (source) => {
    const raw = unref(source);

    if (!raw || typeof raw !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(raw).filter(([_, value]) => value !== '' && value !== null && value !== undefined)
    );
};

const buildEndpointUrl = (endpoint) => {
    const normalized = (endpoint || '').toString().trim();

    if (!normalized) {
        throw new Error('Endpoint не указан');
    }

    if (/^https?:\/\//i.test(normalized)) {
        return normalized;
    }

    if (normalized.startsWith('/api/')) {
        return normalized;
    }

    if (normalized.startsWith('api/')) {
        return `/${normalized}`;
    }

    return `/api/${normalized.replace(/^\/+/, '')}`;
};

const loadReceiptTemplate = async () => {
    try {
        const response = await axios.get('/integration/receipt-template', {
            headers: {
                Accept: 'application/json',
                ...(token.value ? { 'Access-Token': token.value } : {}),
            },
        });

        const base64Value = response?.data?.data?.base64;
        if (typeof base64Value === 'string' && base64Value.trim()) {
            receiptTemplate.value = base64Value;
        }
    } catch (error) {
        console.error('Не удалось загрузить шаблон чека:', error);
    }
};

const getRawResponseBody = (axiosResponse) => {
    if (!axiosResponse) {
        return '';
    }

    const request = axiosResponse.request;

    if (request) {
        if (typeof request.responseText === 'string' && request.responseText.length > 0) {
            return request.responseText;
        }

        if (typeof request.response === 'string' && request.response.length > 0) {
            return request.response;
        }
    }

    if (typeof axiosResponse.data === 'string') {
        return axiosResponse.data;
    }

    try {
        return JSON.stringify(axiosResponse.data ?? '');
    } catch (error) {
        return '';
    }
};

const executeRequest = async (method, endpoint, data = {}, headers = {}) => {
    loading.value = true;

    try {
        const url = buildEndpointUrl(endpoint);
        const requestMethod = (method || 'GET').toUpperCase();
        const cleanData = sanitizeObject(data);
        const cleanHeaders = sanitizeObject(headers);

        const baseHeaders = {
            Accept: 'application/json',
            ...cleanHeaders
        };

        if (token.value) {
            baseHeaders['Access-Token'] = token.value;
        }

        const axiosConfig = {
            method: requestMethod,
            url,
            headers: baseHeaders
        };

        if (requestMethod === 'GET' || requestMethod === 'DELETE') {
            axiosConfig.params = cleanData;
        } else {
            axiosConfig.data = cleanData;
        }

        const response = await axios.request(axiosConfig);
        const rawBody = getRawResponseBody(response);

        return {
            success: true,
            data: {
                status: response.status,
                data: response.data,
                headers: response.headers,
                rawBody
            }
        };
    } catch (error) {
        const response = error.response;
        const rawBody = getRawResponseBody(response);

        return {
            success: false,
            error: {
                status: response?.status,
                message: response?.data?.message || error.message || 'Ошибка при выполнении запроса',
                errors: response?.data?.errors || {},
                rawBody
            }
        };
    } finally {
        loading.value = false;
    }
};

const regenerateToken = async () => {
    if (regenerating.value) {
        return;
    }

    regenerating.value = true;

    try {
        const response = await axios.post(route('integration.regenerate-token'));
        const newToken = response?.data?.data?.token;

        if (typeof newToken === 'string' && newToken.length > 0) {
            token.value = newToken;
        }
    } catch (error) {
        console.error('Не удалось перегенерировать токен:', error);
    } finally {
        regenerating.value = false;
    }
};

const openRegenerateConfirm = () => {
    modalStore.openConfirmModal({
        title: 'Перегенерировать API токен?',
        body: 'Старый токен станет недействительным. Действие невозможно отменить.',
        confirm_button_name: 'Перегенерировать',
        cancel_button_name: 'Отмена',
        confirm: regenerateToken,
    });
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <Head title="Интеграция по API"/>

    <div class="antialiased">
        <div class="mx-auto max-w-7xl">
            <h2 class="text-3xl font-bold text-base-content mb-6">Интеграция по API</h2>

            <!-- Блок с токеном -->
            <div class="card w-full bg-base-100 shadow mb-6">
                <div class="card-body">
                    <label for="api-key" class="text-sm font-medium text-base-content mb-2 block">API токен:</label>
                    <div class="relative">
                        <input
                            id="api-key"
                            type="text"
                            class="col-span-6 bg-base-200 border border-base-300 text-base-content/70 text-sm rounded-xl focus:ring-primary focus:border-primary block w-full p-2.5 pr-24"
                            :value="token"
                            disabled
                            readonly
                        >
                        <div class="absolute end-2 top-1/2 -translate-y-1/2 flex items-center gap-2">
                            <button
                                @click="copy(token)"
                                class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                type="button"
                                aria-label="Скопировать токен"
                            >
                                <span v-if="!copied">
                                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                        <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 1 1 0 2Zm0-5H5a1 1 0 0 1 0-2h2V2h4v2h2a1 1 0 1 1 0 2Z"/>
                                    </svg>
                                </span>
                                <span v-else class="inline-flex items-center">
                                    <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 12">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5.917 5.724 10.5 15 1.5"/>
                                    </svg>
                                </span>
                            </button>
                            <button
                                @click="openRegenerateConfirm"
                                class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                :class="{ 'opacity-50 pointer-events-none': regenerating }"
                                :disabled="regenerating"
                                type="button"
                                aria-label="Перегенерировать токен"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Табы для разделов API -->
            <div class="tabs tabs-boxed mb-6">
                <a class="tab" :class="{ 'tab-active': activeTab === 'merchant' }" @click="setActiveTab('merchant')">
                    Merchant API
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'h2h' }" @click="setActiveTab('h2h')">
                    H2H API
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'payouts' }" @click="setActiveTab('payouts')">
                    Выплаты
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'statements' }" @click="setActiveTab('statements')">
                    Выписки
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'wallet' }" @click="setActiveTab('wallet')">
                    Авто вывод
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'common' }" @click="setActiveTab('common')">
                    Общие методы
                </a>
                <a class="tab" :class="{ 'tab-active': activeTab === 'docs' }" @click="setActiveTab('docs')">
                    Документация
                </a>
            </div>

            <!-- Merchant API -->
            <MerchantApi
                v-if="activeTab === 'merchant'"
                :execute-request="executeRequest"
                :loading="loading"
                :merchant-id="merchantId"
                :merchants="merchants"
            />

            <!-- H2H API -->
            <H2HApi
                v-if="activeTab === 'h2h'"
                :execute-request="executeRequest"
                :loading="loading"
                :merchant-id="merchantId"
                :merchants="merchants"
                :receipt-template="receiptTemplate"
            />

            <!-- Payout API -->
            <PayoutApi
                v-if="activeTab === 'payouts'"
                :execute-request="executeRequest"
                :loading="loading"
                :merchant-id="merchantId"
                :merchants="merchants"
            />

            <!-- Statements API -->
            <StatementApi
                v-if="activeTab === 'statements'"
                :execute-request="executeRequest"
                :loading="loading"
                :merchant-id="merchantId"
                :merchants="merchants"
            />

            <!-- Wallet API -->
            <WalletApi
                v-if="activeTab === 'wallet'"
                :execute-request="executeRequest"
                :loading="loading"
            />

            <!-- Общие методы -->
            <CommonApi
                v-if="activeTab === 'common'"
                :execute-request="executeRequest"
                :loading="loading"
            />

            <!-- Документация -->
            <div v-if="activeTab === 'docs'">
                <ApiDocumentation />
            </div>
        </div>
    </div>

    <ConfirmModal />
</template>
