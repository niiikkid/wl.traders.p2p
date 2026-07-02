<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";
import TextInput from "@/Components/TextInput.vue";
import Select from "@/Components/Select.vue";
import Multiselect from "@/Components/Form/Multiselect.vue";
import { ref, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { router } from "@inertiajs/vue3";

const modalStore = useModalStore();
const { rateSourceEditModal } = storeToRefs(modalStore);

const processing = ref(false);
const loadingOptions = ref(false);
const previewing = ref(false);
const previewResult = ref(null);
const errors = ref({});
const filterConditions = ref({});

const TYPE_OPTIONS = [
    { value: 'manual', name: 'Ручной' },
    { value: 'bybit', name: 'Bybit' },
    { value: 'binance', name: 'Binance' },
];

const SIDE_OPTIONS = [
    { value: 'sell', name: 'Продажа USDT (sell)' },
    { value: 'buy', name: 'Покупка USDT (buy)' },
];

const createForm = () => ({
    id: null,
    name: '',
    type: 'bybit',
    quote_currency: 'rub',
    is_active: true,
    side: 'sell',
    rate: null,
    amount: null,
    payment_methods: [],
    ad_quantity: null,
    min_recent_orders: null,
    country: [],
    min_month_orders: null,
});

const form = ref(createForm());

const isEdit = computed(() => !!form.value.id);
const currencyOptions = computed(() => {
    const list = rateSourceEditModal.value.params?.currencies ?? [];
    return list.map((code) => ({ value: String(code).toLowerCase(), name: String(code).toUpperCase() }));
});

const title = computed(() => (isEdit.value ? 'Редактирование источника курса' : 'Новый источник курса'));

const isAutomatic = computed(() => form.value.type === 'bybit' || form.value.type === 'binance');

const mapOptions = (items) => (Array.isArray(items) ? items : [])
    .map((item) => {
        if (item && typeof item === 'object') {
            return {
                id: String(item.id ?? item.value ?? item.code ?? ''),
                name: String(item.name ?? item.label ?? item.id ?? item.value ?? ''),
            };
        }
        const value = String(item ?? '');
        return { id: value, name: value };
    })
    .filter((item) => item.id !== '');

const paymentMethodOptions = computed(() => mapOptions(filterConditions.value?.payment_methods ?? []));
const countryOptions = computed(() => mapOptions(filterConditions.value?.countries ?? []));

const clearError = (field) => {
    if (!errors.value[field]) return;
    const copy = { ...errors.value };
    delete copy[field];
    errors.value = copy;
};

const errorMessage = (field) => errors.value?.[field]?.[0] ?? null;

const close = () => modalStore.closeModal('rateSourceEdit');

const populateFromSource = (source) => {
    const settings = source.settings ?? {};
    form.value = {
        id: source.id,
        name: source.name ?? '',
        type: source.type,
        quote_currency: source.quote_currency,
        is_active: !!source.is_active,
        side: settings.side ?? 'sell',
        rate: settings.rate ?? null,
        amount: settings.amount ?? null,
        payment_methods: (settings.payment_methods ?? []).map((value) => String(value)),
        ad_quantity: settings.ad_quantity ?? null,
        min_recent_orders: settings.min_recent_orders ?? null,
        country: settings.country ? [String(settings.country)] : [],
        min_month_orders: settings.min_month_orders ?? null,
    };
};

const loadFilterOptions = () => {
    filterConditions.value = {};
    if (!isAutomatic.value || !form.value.quote_currency) {
        return;
    }
    loadingOptions.value = true;
    axios.get(route('admin.rate-sources.filter-options'), {
        params: { type: form.value.type, currency: form.value.quote_currency },
    }).then(({ data }) => {
        filterConditions.value = data?.data?.filter_conditions ?? {};
    }).finally(() => {
        loadingOptions.value = false;
    });
};

const buildSettings = () => {
    const side = form.value.side || 'sell';

    if (form.value.type === 'manual') {
        return { rate: Number(form.value.rate ?? 0), ...(side ? { side } : {}) };
    }

    if (form.value.type === 'binance') {
        const country = Array.isArray(form.value.country) ? form.value.country[0] : form.value.country;
        return {
            country: country ? String(country) : null,
            payment_methods: (form.value.payment_methods ?? []).map((value) => String(value)),
            ad_quantity: form.value.ad_quantity ?? null,
            min_month_orders: form.value.min_month_orders ?? null,
            ...(side ? { side } : {}),
        };
    }

    return {
        amount: form.value.amount ?? null,
        payment_methods: (form.value.payment_methods ?? []).map((value) => Number(value)).filter((value) => !Number.isNaN(value)),
        ad_quantity: form.value.ad_quantity ?? null,
        min_recent_orders: form.value.min_recent_orders ?? null,
        ...(side ? { side } : {}),
    };
};

const buildPayload = () => ({
    name: (form.value.name || '').trim(),
    type: form.value.type,
    quote_currency: form.value.quote_currency,
    is_active: form.value.is_active,
    settings: buildSettings(),
});

const preview = () => {
    if (previewing.value) return;
    previewing.value = true;
    previewResult.value = null;
    errors.value = {};

    axios.post(route('admin.rate-sources.preview'), buildPayload(), { headers: { Accept: 'application/json' } })
        .then(({ data }) => {
            previewResult.value = data?.data ?? data ?? null;
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            }
            previewResult.value = {
                status: 'failed',
                rate: null,
                error: error.response?.data?.message ?? 'Не удалось проверить курс.',
            };
        })
        .finally(() => {
            previewing.value = false;
        });
};

const submit = () => {
    if (processing.value) return;

    if (!(form.value.name || '').trim()) {
        errors.value = { name: ['Укажите название источника.'] };
        return;
    }

    processing.value = true;
    errors.value = {};

    const payload = buildPayload();

    const request = isEdit.value
        ? axios.patch(route('admin.rate-sources.update', form.value.id), payload, { headers: { Accept: 'application/json' } })
        : axios.post(route('admin.rate-sources.store'), payload, { headers: { Accept: 'application/json' } });

    request.then(() => {
        close();
        router.reload({ only: ['sources'] });
    }).catch((error) => {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value = { name: [error.response.data.message] };
        }
    }).finally(() => {
        processing.value = false;
    });
};

watch(
    () => rateSourceEditModal.value.showed,
    (shown) => {
        if (!shown) {
            form.value = createForm();
            errors.value = {};
            filterConditions.value = {};
            previewResult.value = null;
            return;
        }

        previewResult.value = null;

        const source = rateSourceEditModal.value.params?.source ?? null;
        if (source) {
            populateFromSource(source);
        } else {
            form.value = createForm();
        }
        loadFilterOptions();
    }
);

watch(() => [form.value.type, form.value.quote_currency], () => {
    previewResult.value = null;
    if (rateSourceEditModal.value.showed) {
        loadFilterOptions();
    }
});
</script>

<template>
    <Modal :show="rateSourceEditModal.showed" @close="close" maxWidth="2xl">
        <ModalHeader @close="close" :title="title" />

        <ModalBody>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="rs-name" value="Название *" :error="!!errorMessage('name')" />
                        <TextInput
                            id="rs-name"
                            v-model="form.name"
                            type="text"
                            class="input input-bordered w-full mt-1"
                            placeholder="Например, Bybit RUB (продажа)"
                            :error="!!errorMessage('name')"
                            @input="clearError('name')"
                        />
                        <InputError :message="errorMessage('name')" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Тип источника" :error="!!errorMessage('type')" class="mb-1" />
                        <Select
                            v-model="form.type"
                            :items="TYPE_OPTIONS"
                            value="value"
                            name="name"
                            :required="false"
                            size="sm"
                            @change="clearError('type')"
                        />
                        <InputError :message="errorMessage('type')" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Валюта (фиат)" :error="!!errorMessage('quote_currency')" class="mb-1" />
                        <Select
                            v-model="form.quote_currency"
                            :items="currencyOptions"
                            value="value"
                            name="name"
                            :required="false"
                            size="sm"
                            @change="clearError('quote_currency')"
                        />
                        <InputError :message="errorMessage('quote_currency')" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Сторона курса" class="mb-1" />
                        <Select
                            v-model="form.side"
                            :items="SIDE_OPTIONS"
                            value="value"
                            name="name"
                            :required="false"
                            size="sm"
                        />
                        <InputHelper model-value="Какую сторону P2P-стакана парсить: продажа (sell) или покупка (buy) USDT." />
                    </div>

                    <div class="flex items-end">
                        <label class="label cursor-pointer gap-3">
                            <input type="checkbox" v-model="form.is_active" class="toggle toggle-success" />
                            <span class="label-text">Активен</span>
                        </label>
                    </div>
                </div>

                <div v-if="form.type === 'manual'" class="rounded-lg border border-base-300 bg-base-100/50 p-4">
                    <InputLabel for="rs-rate" value="Курс (фиат за 1 USDT)" :error="!!errorMessage('settings.rate')" />
                    <NumberInput
                        id="rs-rate"
                        v-model="form.rate"
                        type="text"
                        class="input input-bordered w-full mt-1"
                        placeholder="Например, 95.35"
                        @input="clearError('settings.rate')"
                    />
                    <InputError :message="errorMessage('settings.rate')" class="mt-1" />
                </div>

                <div v-else class="rounded-lg border border-base-300 bg-base-100/50 p-4 space-y-4">
                    <div v-if="loadingOptions" class="text-sm text-base-content/60">
                        <span class="loading loading-spinner loading-xs"></span> Загрузка методов…
                    </div>

                    <div v-if="form.type === 'bybit'">
                        <InputLabel for="rs-amount" value="Объём" />
                        <NumberInput
                            id="rs-amount"
                            v-model="form.amount"
                            type="text"
                            class="input input-bordered w-full mt-1"
                            placeholder="Мин. объём лимита на обмен"
                        />
                    </div>

                    <div v-if="form.type === 'binance'">
                        <InputLabel value="Страна" class="mb-1" />
                        <Multiselect
                            v-model="form.country"
                            :options="countryOptions"
                            label-key="name"
                            value-key="id"
                            placeholder="Выберите страну"
                            single-select
                            allow-toggle-off
                        />
                        <InputHelper model-value="Повторный клик по выбранной стране снимает выбор." />
                    </div>

                    <div>
                        <InputLabel value="Платёжные методы" class="mb-1" />
                        <Multiselect
                            v-model="form.payment_methods"
                            :options="paymentMethodOptions"
                            label-key="name"
                            value-key="id"
                            placeholder="Выберите один или несколько методов"
                        />
                        <InputHelper model-value="Если ничего не выбрать, берём объявления со всеми методами." />
                    </div>

                    <div>
                        <InputLabel value="Количество объявлений" />
                        <NumberInput
                            v-model="form.ad_quantity"
                            type="text"
                            class="input input-bordered w-full mt-1"
                            placeholder="Например, 50"
                        />
                    </div>

                    <div v-if="form.type === 'bybit'">
                        <InputLabel value="Мин. количество сделок" />
                        <NumberInput
                            v-model="form.min_recent_orders"
                            type="text"
                            class="input input-bordered w-full mt-1"
                            placeholder="Например, 100"
                        />
                    </div>

                    <div v-if="form.type === 'binance'">
                        <InputLabel value="Мин. количество сделок за месяц" />
                        <NumberInput
                            v-model="form.min_month_orders"
                            type="text"
                            class="input input-bordered w-full mt-1"
                            placeholder="Например, 100"
                        />
                    </div>
                </div>

                <div
                    v-if="previewResult"
                    class="alert"
                    :class="{
                        'alert-success': previewResult.status === 'success',
                        'alert-warning': previewResult.status === 'empty',
                        'alert-error': previewResult.status === 'failed',
                    }"
                    role="alert"
                >
                    <div class="text-sm">
                        <template v-if="previewResult.status === 'success'">
                            Курс получен: <span class="font-semibold">{{ previewResult.rate }}</span>
                            <span class="opacity-70"> ({{ form.quote_currency.toUpperCase() }} за 1 USDT, сторона {{ previewResult.side }})</span>
                        </template>
                        <template v-else-if="previewResult.status === 'empty'">
                            Подходящих объявлений не найдено — курс пустой. Проверьте фильтры (методы, объём, количество сделок).
                        </template>
                        <template v-else>
                            Ошибка проверки: {{ previewResult.error || 'не удалось получить курс.' }}
                        </template>
                    </div>
                </div>
            </form>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">Отмена</button>
            <button
                type="button"
                class="btn btn-sm btn-outline"
                :class="{ 'btn-disabled': previewing }"
                :disabled="previewing"
                @click="preview"
            >
                <span v-if="previewing" class="loading loading-spinner loading-xs"></span>
                <span v-else>Проверить курс</span>
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing || !form.name?.trim() }"
                :disabled="processing || !form.name?.trim()"
                @click="submit"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
