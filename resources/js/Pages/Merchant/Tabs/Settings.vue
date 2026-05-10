<script setup>
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import SaveButton from "@/Components/Form/SaveButton.vue";
import {usePage} from "@inertiajs/vue3";
import {computed, reactive, ref, watch} from "vue";
import {useViewStore} from "@/store/view.js";
import Select from "@/Components/Select.vue";
import Gateways from "@/Pages/Merchant/Tabs/Partials/Gateways.vue";
import DatepickerInput from "@/Pages/Merchant/Tabs/Partials/DatepickerInput.vue";
import DUUID from "@/Components/DUUID.vue";

const viewStore = useViewStore();
const emit = defineEmits(['updated']);
const MERCHANT_API_MARKET = 'merchant_api';

const props = defineProps({
    merchant: {
        type: Object,
        default: null,
    },
    markets: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    currencies: {
        type: Array,
        default: () => [],
    },
    detailTypes: {
        type: Array,
        default: () => [],
    },
    commissionSettings: {
        type: Array,
        default: () => [],
    },
    paymentGateways: {
        type: Object,
        default: () => ({ data: [] }),
    },
});

const page = usePage();

const deepClone = (value, fallback = undefined) => {
    if (value === undefined || value === null) {
        return fallback ?? null;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch (e) {
        return fallback ?? value;
    }
};

const merchant = ref(deepClone(props.merchant ?? page?.props?.merchant ?? null));
const markets = ref(deepClone(props.markets?.length ? props.markets : page?.props?.markets ?? []));
const categories = ref(deepClone(props.categories?.length ? props.categories : page?.props?.categories ?? []));
const currencies = ref(deepClone(props.currencies?.length ? props.currencies : page?.props?.currencies ?? []));
const detailTypes = ref(deepClone(props.detailTypes?.length ? props.detailTypes : page?.props?.detailTypes ?? []));
const commissionSettings = ref(deepClone(props.commissionSettings ?? page?.props?.commissionSettings ?? [], []));
const paymentGateways = ref(deepClone(
    (props.paymentGateways && Object.keys(props.paymentGateways).length)
        ? props.paymentGateways
        : page?.props?.paymentGateways ?? { data: [] }
));

const normalizeGeoItems = (items) => {
    const source = deepClone(items ?? [], []);

    if (!source || source.length === 0) {
        return [{
            currency: 'rub',
            market: 'rapira',
            order_reference_rate: null,
            payout_reference_rate: null,
            max_deviation_percent: null,
        }];
    }

    return source
        .filter((geo) => geo?.currency && geo?.market)
        .map((geo) => ({
            currency: (geo.currency ?? '').toLowerCase(),
            market: geo.market,
            order_reference_rate: geo.order_reference_rate ?? geo.reference_rate ?? null,
            payout_reference_rate: geo.payout_reference_rate ?? geo.reference_rate ?? null,
            max_deviation_percent: geo.max_deviation_percent ?? null,
        }));
};

const geoItems = ref(normalizeGeoItems(merchant.value?.geos));
const selectedCurrency = ref('');
const minOrderAmounts = ref(merchant.value?.min_order_amounts ? {...merchant.value.min_order_amounts} : {});

const formCallback = reactive({
    callback_url: merchant.value?.callback_url ?? '',
    payout_callback_url: merchant.value?.payout_callback_url ?? '',
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _successTimer: null,
});

const geoForm = reactive({
    currency: '',
    market: '',
    order_reference_rate: '',
    payout_reference_rate: '',
    max_deviation_percent: '',
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _successTimer: null,
});

const formSettings = reactive({
    categories: merchant.value?.categories ?? [],
    max_order_wait_time: merchant.value?.max_order_wait_time ?? null,
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _successTimer: null,
});

const formStatus = reactive({
    processing: false,
});

const formResendCallback = reactive({
    start_date: '',
    end_date: '',
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _successTimer: null,
});

const availableCurrencies = computed(() => {
    return currencies.value.filter(
        (currency) => !Object.keys(minOrderAmounts.value || {}).includes(currency.value)
    );
});

const availableGeoCurrencies = computed(() => {
    const selected = (geoItems.value || []).map((geo) => geo.currency?.toLowerCase());

    return currencies.value.filter(
        (currency) => !selected.includes(currency.value.toLowerCase())
    );
});

const merchantGeosReadonly = computed(() => {
    const raw = merchant.value?.geos;
    if (!Array.isArray(raw) || raw.length === 0) {
        return [];
    }

    return raw
        .filter((geo) => geo?.currency && geo?.market)
        .map((geo) => ({
            currency: String(geo.currency).toLowerCase(),
            market: geo.market,
            order_reference_rate: geo.order_reference_rate ?? geo.reference_rate ?? null,
            payout_reference_rate: geo.payout_reference_rate ?? geo.reference_rate ?? null,
            max_deviation_percent: geo.max_deviation_percent ?? null,
        }));
});

const resetFormsFromMerchant = (value) => {
    if (!value) {
        return;
    }

    formCallback.callback_url = value.callback_url ?? '';
    formCallback.payout_callback_url = value.payout_callback_url ?? '';
    formSettings.categories = value.categories ?? [];
    formSettings.max_order_wait_time = value.max_order_wait_time ?? null;
    minOrderAmounts.value = value.min_order_amounts ? {...value.min_order_amounts} : {};
    geoItems.value = normalizeGeoItems(value.geos ?? []);
};

watch(
    () => props.merchant,
    (value) => {
        if (value !== undefined) {
            merchant.value = value ? deepClone(value) : null;
            resetFormsFromMerchant(merchant.value);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.merchant,
    (value) => {
        if (!props.merchant && value) {
            merchant.value = deepClone(value);
            resetFormsFromMerchant(merchant.value);
        }
    },
    { immediate: true }
);

watch(
    () => props.paymentGateways,
    (value) => {
        if (value !== undefined) {
            paymentGateways.value = deepClone(value ?? { data: [] }, { data: [] });
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.paymentGateways,
    (value) => {
        if (value !== undefined && (!props.paymentGateways || !Object.keys(props.paymentGateways).length)) {
            paymentGateways.value = deepClone(value ?? { data: [] }, { data: [] });
        }
    },
    { immediate: true }
);

watch(
    () => props.markets,
    (value) => {
        if (value !== undefined) {
            markets.value = deepClone(value ?? [], []);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.markets,
    (value) => {
        if (value !== undefined && (!props.markets || !props.markets.length)) {
            markets.value = deepClone(value ?? [], []);
        }
    },
    { immediate: true }
);

watch(
    () => props.categories,
    (value) => {
        if (value !== undefined) {
            categories.value = deepClone(value ?? [], []);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.categories,
    (value) => {
        if (value !== undefined && (!props.categories || !props.categories.length)) {
            categories.value = deepClone(value ?? [], []);
        }
    },
    { immediate: true }
);

watch(
    () => props.currencies,
    (value) => {
        if (value !== undefined) {
            currencies.value = deepClone(value ?? [], []);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.currencies,
    (value) => {
        if (value !== undefined && (!props.currencies || !props.currencies.length)) {
            currencies.value = deepClone(value ?? [], []);
        }
    },
    { immediate: true }
);

watch(
    () => props.detailTypes,
    (value) => {
        if (value !== undefined) {
            detailTypes.value = deepClone(value ?? [], []);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.detailTypes,
    (value) => {
        if (value !== undefined && (!props.detailTypes || !props.detailTypes.length)) {
            detailTypes.value = deepClone(value ?? [], []);
        }
    },
    { immediate: true }
);

watch(
    () => props.commissionSettings,
    (value) => {
        if (value !== undefined) {
            commissionSettings.value = deepClone(value ?? [], []);
        }
    },
    { immediate: false }
);

watch(
    () => page.props?.commissionSettings,
    (value) => {
        if (value !== undefined && (!props.commissionSettings || !props.commissionSettings.length)) {
            commissionSettings.value = deepClone(value ?? [], []);
        }
    },
    { immediate: true }
);

watch(
    () => geoForm.market,
    (market) => {
        if (market !== MERCHANT_API_MARKET) {
            geoForm.order_reference_rate = '';
            geoForm.payout_reference_rate = '';
            geoForm.max_deviation_percent = '';
            clearFormError(geoForm, 'order_reference_rate');
            clearFormError(geoForm, 'payout_reference_rate');
            clearFormError(geoForm, 'max_deviation_percent');
        }
    }
);

const markRecentlySuccessful = (form) => {
    if (!form) {
        return;
    }

    if (form._successTimer) {
        clearTimeout(form._successTimer);
    }

    form.recentlySuccessful = true;
    form._successTimer = setTimeout(() => {
        form.recentlySuccessful = false;
        form._successTimer = null;
    }, 2000);
};

const clearFormError = (form, field) => {
    if (form?.errors && Object.prototype.hasOwnProperty.call(form.errors, field)) {
        const errors = {...form.errors};
        delete errors[field];
        form.errors = errors;
    }
};

const handleValidationError = (error, form) => {
    if (error.response?.data?.errors) {
        form.errors = error.response.data.errors;
    }
};

const submitCallback = () => {
    if (!merchant.value || formCallback.processing) {
        return;
    }

    formCallback.processing = true;
    formCallback.errors = {};

    axios.patch(route('merchants.callback.update', merchant.value.id), {
        callback_url: formCallback.callback_url,
        payout_callback_url: formCallback.payout_callback_url,
    }, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        if (data?.merchant) {
            merchant.value = data.merchant;
            resetFormsFromMerchant(merchant.value);
            emit('updated', merchant.value);
        }
        markRecentlySuccessful(formCallback);
    }).catch((error) => {
        handleValidationError(error, formCallback);
    }).finally(() => {
        formCallback.processing = false;
    });
};

const submitSettings = () => {
    if (!merchant.value || formSettings.processing) {
        return;
    }

    formSettings.processing = true;
    formSettings.errors = {};

    axios.patch(route('admin.merchants.settings.update', merchant.value.id), {
        categories: formSettings.categories,
        max_order_wait_time: formSettings.max_order_wait_time,
        min_order_amounts: minOrderAmounts.value,
    }, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        if (data?.merchant) {
            merchant.value = data.merchant;
            resetFormsFromMerchant(merchant.value);
            emit('updated', merchant.value);
        }
        markRecentlySuccessful(formSettings);
    }).catch((error) => {
        handleValidationError(error, formSettings);
    }).finally(() => {
        formSettings.processing = false;
    });
};

const addGeo = () => {
    if (!geoForm.currency || !geoForm.market) {
        return;
    }

    if (geoForm.market === MERCHANT_API_MARKET) {
        const orderReferenceRate = Number(geoForm.order_reference_rate);
        const payoutReferenceRate = Number(geoForm.payout_reference_rate);
        const maxDeviationPercent = Number(geoForm.max_deviation_percent);

        if (!Number.isFinite(orderReferenceRate) || orderReferenceRate <= 0) {
            geoForm.errors = { order_reference_rate: ['Укажите корректный опорный курс для сделок больше 0.'] };
            return;
        }

        if (!Number.isFinite(payoutReferenceRate) || payoutReferenceRate <= 0) {
            geoForm.errors = { payout_reference_rate: ['Укажите корректный опорный курс для выплат больше 0.'] };
            return;
        }

        if (!Number.isFinite(maxDeviationPercent) || maxDeviationPercent <= 0) {
            geoForm.errors = { max_deviation_percent: ['Укажите корректное отклонение в процентах больше 0.'] };
            return;
        }
    }

    const currency = geoForm.currency.toLowerCase();

    if ((geoItems.value || []).some((geo) => geo.currency === currency)) {
        geoForm.errors = {geos: [`Валюта ${currency.toUpperCase()} уже добавлена.`]};
        return;
    }

    geoItems.value = [
        ...geoItems.value,
        {
            currency,
            market: geoForm.market,
            order_reference_rate: geoForm.market === MERCHANT_API_MARKET ? geoForm.order_reference_rate : null,
            payout_reference_rate: geoForm.market === MERCHANT_API_MARKET ? geoForm.payout_reference_rate : null,
            max_deviation_percent: geoForm.market === MERCHANT_API_MARKET ? geoForm.max_deviation_percent : null,
        },
    ];

    geoForm.currency = '';
    geoForm.market = '';
    geoForm.order_reference_rate = '';
    geoForm.payout_reference_rate = '';
    geoForm.max_deviation_percent = '';
    geoForm.errors = {};
};

const removeGeo = (currency) => {
    if ((geoItems.value || []).length <= 1) {
        return;
    }

    geoItems.value = geoItems.value.filter((geo) => geo.currency !== currency);
};

const submitGeo = () => {
    if (!merchant.value || geoForm.processing) {
        return;
    }

    geoForm.processing = true;
    geoForm.errors = {};

    axios.patch(route('admin.merchants.geo.update', merchant.value.id), {
        geos: geoItems.value,
    }, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        if (data?.merchant) {
            merchant.value = data.merchant;
            resetFormsFromMerchant(merchant.value);
            emit('updated', merchant.value);
        }
        markRecentlySuccessful(geoForm);
    }).catch((error) => {
        handleValidationError(error, geoForm);
    }).finally(() => {
        geoForm.processing = false;
    });
};

const performStatusAction = (routeName) => {
    if (!merchant.value || formStatus.processing) {
        return;
    }

    formStatus.processing = true;

    axios.patch(route(routeName, merchant.value.id), {}, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        if (data?.merchant) {
            merchant.value = data.merchant;
            resetFormsFromMerchant(merchant.value);
            emit('updated', merchant.value);
        }
    }).finally(() => {
        formStatus.processing = false;
    });
};

const submitBan = () => performStatusAction('admin.merchants.ban');
const submitUnban = () => performStatusAction('admin.merchants.unban');
const submitValidated = () => performStatusAction('admin.merchants.validated');

const submitResendCallback = () => {
    if (!merchant.value || formResendCallback.processing) {
        return;
    }

    formResendCallback.processing = true;
    formResendCallback.errors = {};

    axios.post(route('admin.merchants.resend-callback', merchant.value.id), {
        start_date: formResendCallback.start_date,
        end_date: formResendCallback.end_date,
    }, {
        headers: {Accept: 'application/json'},
    }).then(() => {
        markRecentlySuccessful(formResendCallback);
    }).catch((error) => {
        handleValidationError(error, formResendCallback);
    }).finally(() => {
        formResendCallback.processing = false;
    });
};

const addMinOrderAmount = () => {
    if (!selectedCurrency.value) {
        return;
    }

    if (!minOrderAmounts.value[selectedCurrency.value]) {
        minOrderAmounts.value = {
            ...minOrderAmounts.value,
            [selectedCurrency.value]: "",
        };
    }

    selectedCurrency.value = '';
};

const removeMinOrderAmount = (currency) => {
    if (minOrderAmounts.value[currency] !== undefined) {
        const updated = {...minOrderAmounts.value};
        delete updated[currency];
        minOrderAmounts.value = updated;
    }
};

const handleGatewaySettingsUpdated = (payload) => {
    if (payload?.commission_settings) {
        commissionSettings.value = payload.commission_settings;
    }
    if (payload?.merchant) {
        merchant.value = payload.merchant;
        resetFormsFromMerchant(merchant.value);
        emit('updated', merchant.value);
    }
};

const activeTab = ref('callback');

const adminTabs = [
    {id: 'moderation', title: 'Модерация', description: 'Статус доступа'},
    {id: 'geo', title: 'Гео', description: 'Валюты и маркеты'},
    {id: 'settings', title: 'Лимиты', description: 'Время и суммы'},
    {id: 'resend', title: 'Callback resend', description: 'Повторная отправка'},
];

const tabs = computed(() => {
    const rows = [
        {id: 'callback', title: 'Callback', description: 'URL уведомлений'},
    ];

    if (!viewStore.isAdminViewMode) {
        rows.push({id: 'geo', title: 'Гео', description: 'Текущие направления'});
    }

    rows.push({id: 'gateways', title: 'Комиссии', description: ''});

    if (viewStore.isAdminViewMode) {
        rows.push(...adminTabs);
    }

    return rows;
});

const activeTabMeta = computed(() => tabs.value.find((tab) => tab.id === activeTab.value) ?? tabs.value[0]);

const merchantStatus = computed(() => {
    if (!merchant.value) {
        return null;
    }

    if (!merchant.value.validated_at) {
        return {label: 'На модерации', class: 'badge-warning'};
    }

    if (merchant.value.banned_at) {
        return {label: 'Заблокирован', class: 'badge-error'};
    }

    if (merchant.value.active) {
        return {label: 'Включен', class: 'badge-success'};
    }

    return {label: 'Выключен', class: 'badge-neutral'};
});
</script>

<template>
    <div class="space-y-3 text-xs">
        <div class="rounded-xl border border-base-300 bg-base-200/50 p-2.5 sm:p-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-sm font-semibold text-base-content">
                            {{ merchant?.name || 'Мерчант' }}
                        </h2>
                        <span v-if="merchantStatus" class="badge badge-sm" :class="merchantStatus.class">
                            {{ merchantStatus.label }}
                        </span>
                    </div>
                    <p v-if="activeTabMeta?.description" class="mt-0.5 text-[11px] text-base-content/60">
                        {{ activeTabMeta.description }}
                    </p>
                </div>

                <div v-if="merchant?.uuid" class="shrink-0 rounded-lg bg-base-100 px-2.5 py-1.5 text-[11px] shadow-sm">
                    <div class="mb-0.5 text-base-content/50">Merchant ID</div>
                    <DUUID :uuid="merchant.uuid"/>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <div class="tabs tabs-boxed w-max min-w-full flex-nowrap bg-base-100 p-0.5 rounded-lg">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="tab h-auto min-h-7 whitespace-nowrap px-2.5 py-1 text-xs"
                        :class="{ 'tab-active font-semibold': activeTab === tab.id }"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.title }}
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-base-300 bg-base-100 p-2.5 shadow-sm sm:p-3">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 class="text-xs font-semibold text-base-content">
                        {{ activeTabMeta?.title }}
                    </h3>
                    <p v-if="activeTabMeta?.description" class="mt-0.5 text-[11px] text-base-content/60">
                        {{ activeTabMeta.description }}
                    </p>
                </div>
            </div>
            <!-- Таб: Callback URL -->
            <div v-if="activeTab === 'callback'" class="space-y-3">
                <div v-if="merchant">
                    <div class="rounded-lg bg-base-200/60 p-2.5 sm:p-3">
                        <p class="mb-3 text-xs text-base-content/70">
                            Укажите, куда слать уведомления о сделках и выплатах. Если поле пустое, колбеки по соответствующей сущности отправляться не будут.
                        </p>
                        <form class="grid grid-cols-1 gap-3 lg:grid-cols-2" @submit.prevent="submitCallback">
                            <div>
                                <InputLabel
                                    for="callback_url"
                                    value="Callback для сделок"
                                    :error="!!formCallback.errors.callback_url"
                                />

                                <TextInput
                                    id="callback_url"
                                    v-model="formCallback.callback_url"
                                    type="text"
                                    class="input-sm mt-1 block h-7 min-h-7 w-full py-1 text-[11px] leading-tight"
                                    placeholder="https://example.com/callback"
                                    :error="!!formCallback.errors.callback_url"
                                    @input="clearFormError(formCallback, 'callback_url')"
                                />

                                <InputError :message="formCallback.errors.callback_url" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel
                                    for="payout_callback_url"
                                    value="Callback для выплат"
                                    :error="!!formCallback.errors.payout_callback_url"
                                />

                                <TextInput
                                    id="payout_callback_url"
                                    v-model="formCallback.payout_callback_url"
                                    type="text"
                                    class="input-sm mt-1 block h-7 min-h-7 w-full py-1 text-[11px] leading-tight"
                                    placeholder="https://example.com/payout-callback"
                                    :error="!!formCallback.errors.payout_callback_url"
                                    @input="clearFormError(formCallback, 'payout_callback_url')"
                                />

                                <InputError :message="formCallback.errors.payout_callback_url" class="mt-1" />
                            </div>

                            <div class="lg:col-span-2">
                                <SaveButton
                                    :disabled="formCallback.processing"
                                    :saved="formCallback.recentlySuccessful"
                                    size="xs"
                                />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таб: Гео (мерчант, только просмотр) -->
            <div v-if="activeTab === 'geo' && !viewStore.isAdminViewMode" class="space-y-3">
                <div v-if="merchant" class="space-y-3 rounded-lg bg-base-200/60 p-2.5 sm:p-3">
                
                    <div v-if="merchantGeosReadonly.length" class="grid grid-cols-1 gap-1.5 lg:grid-cols-2">
                        <div
                            v-for="geo in merchantGeosReadonly"
                            :key="`${geo.currency}-${geo.market}`"
                            class="rounded-lg bg-base-100 p-2.5 ring-1 ring-base-content/5"
                        >
                            <div class="text-xs font-medium text-base-content">
                                {{ currencies.find((c) => c.value.toLowerCase() === geo.currency)?.name || geo.currency.toUpperCase() }}
                            </div>
                            <div class="text-[11px] text-base-content/70">
                                {{ markets.find((m) => m.value === geo.market)?.name || geo.market }}
                            </div>
                            <div
                                v-if="geo.market === MERCHANT_API_MARKET"
                                class="mt-0.5 text-[11px] text-base-content/70"
                            >
                                Сделки: {{ geo.order_reference_rate ?? '—' }} · Выплаты: {{ geo.payout_reference_rate ?? '—' }} · Отклонение: ±{{ geo.max_deviation_percent ?? '—' }}%
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-base-content/70">
                        GEO ещё не настроено — приём сделок и выплат может быть недоступен.
                    </p>
                </div>
            </div>

            <!-- Таб: Модерация (только для админа) -->
            <div v-if="activeTab === 'moderation' && viewStore.isAdminViewMode" class="space-y-3">
                <div v-if="merchant">
                    <div class="rounded-lg bg-base-200/60 p-3">
                        <p class="mb-3 text-xs text-base-content/70">
                            Разрешите работу мерчанта или заблокируйте его.
                        </p>
                        <form @submit.prevent="submitCallback">
                            <div class="flex flex-col gap-2 rounded-lg bg-base-100 p-2.5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-base-content/50">Текущий статус</div>
                                    <div class="mt-1">
                                        <span v-if="merchantStatus" class="badge" :class="merchantStatus.class">
                                            {{ merchantStatus.label }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        @click="submitValidated"
                                        v-if="! merchant.validated_at"
                                        type="button"
                                        class="btn btn-sm btn-success"
                                        :disabled="formStatus.processing"
                                    >
                                        Разрешить
                                    </button>
                                    <button
                                        @click="submitUnban"
                                        v-if="merchant.banned_at"
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        :disabled="formStatus.processing"
                                    >
                                        Разблокировать
                                    </button>
                                    <button
                                        @click="submitBan"
                                        v-else
                                        type="button"
                                        class="btn btn-sm btn-error"
                                        :disabled="formStatus.processing"
                                    >
                                        Заблокировать
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таб: Гео (только для админа) -->
            <div v-if="activeTab === 'geo' && viewStore.isAdminViewMode" class="space-y-3">
                <div v-if="merchant">
                    <div class="space-y-3 rounded-lg bg-base-200/60 p-2.5 sm:p-3">
                        <p class="text-xs text-base-content/70">
                            Укажите источник курсов для каждой валюты. Если GEO не настроено, создание сделок и выплат будет недоступно.
                        </p>

                        <form class="space-y-3" @submit.prevent="submitGeo">
                            <div class="grid grid-cols-1 gap-2.5 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <InputLabel
                                        for="geo_currency"
                                        value="Валюта GEO"
                                        :error="!!geoForm.errors.currency || !!geoForm.errors.geos"
                                        class="mb-0.5"
                                    />
                                    <Select
                                        id="geo_currency"
                                        v-model="geoForm.currency"
                                        :items="availableGeoCurrencies"
                                        value="value"
                                        name="name"
                                        default_title="Выберите валюту"
                                        :required="false"
                                        size="sm"
                                        :error="!!geoForm.errors.currency || !!geoForm.errors.geos"
                                        @change="() => { clearFormError(geoForm, 'currency'); clearFormError(geoForm, 'geos'); }"
                                    ></Select>
                                </div>

                                <div>
                                    <InputLabel
                                        for="geo_market"
                                        value="Маркет"
                                        :error="!!geoForm.errors.market || !!geoForm.errors.geos"
                                        class="mb-0.5"
                                    />
                                    <Select
                                        id="geo_market"
                                        v-model="geoForm.market"
                                        :items="markets"
                                        value="value"
                                        name="name"
                                        default_title="Выберите маркет"
                                        :required="false"
                                        size="sm"
                                        :error="!!geoForm.errors.market || !!geoForm.errors.geos"
                                        @change="() => { clearFormError(geoForm, 'market'); clearFormError(geoForm, 'geos'); }"
                                    ></Select>
                                </div>

                                <div v-if="geoForm.market === MERCHANT_API_MARKET">
                                    <InputLabel
                                        for="geo_order_reference_rate"
                                        value="Опорный курс для сделок"
                                        :error="!!geoForm.errors.order_reference_rate || !!geoForm.errors.geos"
                                        class="mb-0.5"
                                    />
                                    <TextInput
                                        id="geo_order_reference_rate"
                                        v-model="geoForm.order_reference_rate"
                                        type="text"
                                        class="input-sm mt-1 block w-full text-xs"
                                        placeholder="Например, 95.12345678"
                                        :error="!!geoForm.errors.order_reference_rate || !!geoForm.errors.geos"
                                        @input="() => { clearFormError(geoForm, 'order_reference_rate'); clearFormError(geoForm, 'geos'); }"
                                    />
                                    <InputError :message="geoForm.errors.order_reference_rate" class="mt-1" />
                                </div>

                                <div v-if="geoForm.market === MERCHANT_API_MARKET">
                                    <InputLabel
                                        for="geo_payout_reference_rate"
                                        value="Опорный курс для выплат"
                                        :error="!!geoForm.errors.payout_reference_rate || !!geoForm.errors.geos"
                                        class="mb-0.5"
                                    />
                                    <TextInput
                                        id="geo_payout_reference_rate"
                                        v-model="geoForm.payout_reference_rate"
                                        type="text"
                                        class="input-sm mt-1 block w-full text-xs"
                                        placeholder="Например, 95.12345678"
                                        :error="!!geoForm.errors.payout_reference_rate || !!geoForm.errors.geos"
                                        @input="() => { clearFormError(geoForm, 'payout_reference_rate'); clearFormError(geoForm, 'geos'); }"
                                    />
                                    <InputError :message="geoForm.errors.payout_reference_rate" class="mt-1" />
                                </div>

                                <div v-if="geoForm.market === MERCHANT_API_MARKET">
                                    <InputLabel
                                        for="geo_max_deviation_percent"
                                        value="Допустимое отклонение, %"
                                        :error="!!geoForm.errors.max_deviation_percent || !!geoForm.errors.geos"
                                        class="mb-0.5"
                                    />
                                    <TextInput
                                        id="geo_max_deviation_percent"
                                        v-model="geoForm.max_deviation_percent"
                                        type="text"
                                        class="input-sm mt-1 block w-full text-xs"
                                        placeholder="Например, 3.00"
                                        :error="!!geoForm.errors.max_deviation_percent || !!geoForm.errors.geos"
                                        @input="() => { clearFormError(geoForm, 'max_deviation_percent'); clearFormError(geoForm, 'geos'); }"
                                    />
                                    <InputError :message="geoForm.errors.max_deviation_percent" class="mt-1" />
                                </div>

                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary w-full"
                                        @click="addGeo"
                                        :disabled="
                                            !geoForm.currency
                                            || !geoForm.market
                                            || (
                                                geoForm.market === MERCHANT_API_MARKET
                                                && (
                                                    !geoForm.order_reference_rate
                                                    || !geoForm.payout_reference_rate
                                                    || !geoForm.max_deviation_percent
                                                )
                                            )
                                        "
                                    >
                                        Добавить GEO
                                    </button>
                                </div>
                            </div>

                            <InputError
                                :message="Array.isArray(geoForm.errors.geos) ? geoForm.errors.geos.join(' ') : (geoForm.errors.geos || geoForm.errors.currency || geoForm.errors.market)"
                                class="mt-1"
                            />

                            <div v-if="geoItems?.length" class="grid grid-cols-1 gap-1.5 lg:grid-cols-2">
                                <div
                                    v-for="geo in geoItems"
                                    :key="geo.currency"
                                    class="flex items-start justify-between gap-2 rounded-lg bg-base-100 p-2.5"
                                >
                                    <div>
                                        <div class="text-xs font-medium text-base-content">
                                            {{ currencies.find(c => c.value.toLowerCase() === geo.currency?.toLowerCase())?.name || geo.currency?.toUpperCase() }}
                                        </div>
                                        <div class="text-[11px] text-base-content/70">
                                            {{ markets.find(m => m.value === geo.market)?.name || geo.market }}
                                        </div>
                                        <div
                                            v-if="geo.market === MERCHANT_API_MARKET"
                                            class="mt-0.5 text-[11px] text-base-content/70"
                                        >
                                            Сделки: {{ geo.order_reference_rate }} | Выплаты: {{ geo.payout_reference_rate }} | Отклонение: ±{{ geo.max_deviation_percent }}%
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-ghost text-error"
                                        @click.prevent="removeGeo(geo.currency)"
                                        :disabled="geoItems.length <= 1"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-xs text-base-content/70">
                                Добавьте хотя бы один GEO: выберите валюту и маркет, затем нажмите «Добавить».
                            </p>

                            <SaveButton
                                :disabled="geoForm.processing"
                                :saved="geoForm.recentlySuccessful"
                                size="xs"
                            />
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таб: Настройки (только для админа) -->
            <div v-if="activeTab === 'settings' && viewStore.isAdminViewMode" class="space-y-3">
                <div v-if="merchant">
                    <div class="rounded-lg bg-base-200/60 p-2.5 sm:p-3">
                        <form class="space-y-3" @submit.prevent="submitSettings">
                            <div>
                                <InputLabel
                                    for="max_order_wait_time"
                                    value="Время на выдачу реквизита (max)"
                                    :error="!!formSettings.errors.max_order_wait_time"
                                    class="mb-0.5"
                                />
                                <TextInput
                                    id="max_order_wait_time"
                                    v-model="formSettings.max_order_wait_time"
                                    type="number"
                                    min="1"
                                    placeholder="Введите время в миллисекундах (1 сек = 1000 мс)"
                                    class="input-sm mt-1 block w-full text-xs"
                                    :error="!!formSettings.errors.max_order_wait_time"
                                    @input="clearFormError(formSettings, 'max_order_wait_time')"
                                />
                                <p class="mt-1 text-xs text-base-content/70">
                                    Примеры: 3000 мс = 3 секунды, 60000 мс = 1 минута
                                </p>
                                <InputError :message="formSettings.errors.max_order_wait_time" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel
                                    value="Максимальная сумма сделки"
                                    class="mb-0.5"
                                />

                                <!-- Выбор валюты -->
                                <div class="mb-2 grid grid-cols-1 gap-1.5 sm:grid-cols-[1fr_auto] sm:items-stretch">
                                    <div class="w-full min-w-0">
                                        <Select
                                            v-model="selectedCurrency"
                                            :items="availableCurrencies"
                                            value="value"
                                            name="name"
                                            default_title="Выберите валюту"
                                            :required="false"
                                            size="sm"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary w-full shrink-0 sm:w-auto sm:self-stretch sm:min-h-0"
                                        @click="addMinOrderAmount"
                                        :disabled="!selectedCurrency"
                                    >
                                        Добавить
                                    </button>
                                </div>

                                <!-- Список минимальных сумм по валютам -->
                                <div v-if="Object.keys(minOrderAmounts).length > 0" class="mt-2 space-y-1.5">
                                    <div
                                        v-for="(amount, currency) in minOrderAmounts"
                                        :key="currency"
                                        class="flex items-center gap-2 rounded-lg bg-base-200 p-2"
                                    >
                                        <div class="flex-1">
                                            <div class="mb-1 text-xs font-medium text-base-content">
                                                {{ currencies.find(c => c.value === currency)?.name || currency.toUpperCase() }}
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <TextInput
                                                    v-model="minOrderAmounts[currency]"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Мин. сумма"
                                                    class="input-sm block w-full text-xs"
                                                />

                                                <button
                                                    type="button"
                                                    class="btn btn-xs btn-ghost btn-square text-error"
                                                    @click.prevent="removeMinOrderAmount(currency)"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mt-1 text-xs text-base-content/70">
                                    Нет настроенных минимальных сумм. Добавьте валюту для настройки.
                                </p>
                            </div>

                            <SaveButton
                                :disabled="formSettings.processing"
                                :saved="formSettings.recentlySuccessful"
                                size="xs"
                            />
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таб: Повторная отправка callback (только для админа) -->
            <div v-if="activeTab === 'resend' && viewStore.isAdminViewMode" class="space-y-3">
                <div v-if="merchant">
                    <div class="rounded-lg bg-base-200/60 p-2.5 sm:p-3">
                        <p class="mb-3 text-xs text-base-content/70">
                            Выберите период дат для повторной отправки callback по всем сделкам мерчанта за указанный период.
                        </p>
                        <form class="space-y-3" @submit.prevent="submitResendCallback">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <InputLabel
                                        for="start_date"
                                        value="Дата начала"
                                        :error="!!formResendCallback.errors.start_date"
                                    />
                                    <DatepickerInput
                                        id="start_date"
                                        v-model="formResendCallback.start_date"
                                        placeholder="дд/мм/гггг"
                                        :error="!!formResendCallback.errors.start_date"
                                        size="sm"
                                        @change="clearFormError(formResendCallback, 'start_date')"
                                    />
                                    <InputError :message="formResendCallback.errors.start_date" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel
                                        for="end_date"
                                        value="Дата окончания"
                                        :error="!!formResendCallback.errors.end_date"
                                    />
                                    <DatepickerInput
                                        id="end_date"
                                        v-model="formResendCallback.end_date"
                                        placeholder="дд/мм/гггг"
                                        :error="!!formResendCallback.errors.end_date"
                                        size="sm"
                                        @change="clearFormError(formResendCallback, 'end_date')"
                                    />
                                    <InputError :message="formResendCallback.errors.end_date" class="mt-1" />
                                </div>
                            </div>
                            <InputError :message="formResendCallback.errors.date_range" class="mt-1" />
                            <SaveButton
                                :disabled="formResendCallback.processing"
                                :saved="formResendCallback.recentlySuccessful"
                                button-text="Отправить callback"
                                size="sm"
                            />
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таб: Комиссии -->
            <div v-if="activeTab === 'gateways'" class="space-y-3 text-xs">
                <Gateways
                    v-if="paymentGateways"
                    :merchant-id="merchant?.id"
                    :detail-types="detailTypes"
                    :commission-settings="commissionSettings"
                    :payment-gateways="paymentGateways"
                    :is-admin="viewStore.isAdminViewMode"
                    @updated="handleGatewaySettingsUpdated"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
