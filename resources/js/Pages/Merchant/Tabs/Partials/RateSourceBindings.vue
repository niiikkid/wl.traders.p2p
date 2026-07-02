<script setup>
import InputError from '@/Components/InputError.vue';
import SaveButton from '@/Components/Form/SaveButton.vue';
import { computed, reactive, ref, watch, onMounted } from 'vue';

const props = defineProps({
    merchantId: { type: [Number, String], default: null },
    currencies: { type: Array, default: () => [] },
    bindings: { type: Array, default: () => [] },
});

const emit = defineEmits(['updated']);

const DIRECTIONS = [
    { key: 'pay_in', label: 'Приём (pay-in)' },
    { key: 'pay_out', label: 'Выплаты (pay-out)' },
];

const MERCHANT_API_VALUE = 'merchant_api';

const sources = ref([]);
const loading = ref(false);
const form = reactive({ processing: false, recentlySuccessful: false, errors: {}, _timer: null });

// selection[currency][direction] = '' | 'merchant_api' | 'source:<id>'
const selection = reactive({});

const geoCurrencies = computed(() => (props.currencies ?? [])
    .map((code) => String(code).toLowerCase())
    .filter((code, index, all) => all.indexOf(code) === index));

const sourcesFor = (currency, direction) => sources.value.filter(
    (source) => source.quote_currency === currency && source.direction === direction,
);

const initSelection = () => {
    geoCurrencies.value.forEach((currency) => {
        if (!selection[currency]) {
            selection[currency] = { pay_in: '', pay_out: '' };
        }
    });

    (props.bindings ?? []).forEach((binding) => {
        const currency = String(binding.currency).toLowerCase();
        if (!selection[currency]) {
            selection[currency] = { pay_in: '', pay_out: '' };
        }
        if (binding.mode === MERCHANT_API_VALUE) {
            selection[currency][binding.direction] = MERCHANT_API_VALUE;
        } else if (binding.source_id) {
            selection[currency][binding.direction] = `source:${binding.source_id}`;
        }
    });
};

const loadSources = () => {
    loading.value = true;
    axios.get(route('admin.rate-sources.options'))
        .then(({ data }) => {
            sources.value = data?.data?.sources ?? [];
        })
        .finally(() => {
            loading.value = false;
        });
};

const buildBindings = () => {
    const result = [];
    geoCurrencies.value.forEach((currency) => {
        DIRECTIONS.forEach(({ key }) => {
            const value = selection[currency]?.[key] ?? '';
            if (!value) {
                return;
            }
            if (value === MERCHANT_API_VALUE) {
                result.push({ currency, direction: key, mode: MERCHANT_API_VALUE });
                return;
            }
            const sourceId = Number(String(value).replace('source:', ''));
            if (!Number.isNaN(sourceId)) {
                result.push({ currency, direction: key, mode: 'source', source_id: sourceId });
            }
        });
    });
    return result;
};

const submit = () => {
    if (!props.merchantId || form.processing) {
        return;
    }
    form.processing = true;
    form.errors = {};

    axios.patch(route('admin.merchants.rate-sources.update', props.merchantId), {
        bindings: buildBindings(),
    }, { headers: { Accept: 'application/json' } })
        .then(({ data }) => {
            if (data?.merchant) {
                emit('updated', data.merchant);
            }
            form.recentlySuccessful = true;
            if (form._timer) clearTimeout(form._timer);
            form._timer = setTimeout(() => { form.recentlySuccessful = false; }, 2000);
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                form.errors = error.response.data.errors;
            }
        })
        .finally(() => {
            form.processing = false;
        });
};

watch(() => props.bindings, initSelection, { deep: true });
watch(() => props.currencies, initSelection, { deep: true });

onMounted(() => {
    initSelection();
    loadSources();
});
</script>

<template>
    <div class="space-y-3 rounded-lg bg-base-200/60 p-2.5 sm:p-3">
        <p class="text-xs text-base-content/70">
            Привяжите конкретные источники курсов к валютам мерчанта по направлениям. «От мерчанта (API)» — курс приходит в запросе и проверяется по опорному курсу из вкладки «Гео».
        </p>

        <div v-if="loading" class="text-sm text-base-content/60">
            <span class="loading loading-spinner loading-xs"></span> Загрузка источников…
        </div>

        <p v-else-if="!geoCurrencies.length" class="text-xs text-base-content/70">
            Сначала добавьте валюты во вкладке «Гео».
        </p>

        <div v-else class="space-y-2">
            <div
                v-for="currency in geoCurrencies"
                :key="currency"
                class="rounded-lg bg-base-100 p-2.5 ring-1 ring-base-content/5"
            >
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content">
                    {{ currency.toUpperCase() }}
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div v-for="direction in DIRECTIONS" :key="direction.key">
                        <label class="mb-0.5 block text-[11px] text-base-content/60">{{ direction.label }}</label>
                        <select
                            v-model="selection[currency][direction.key]"
                            class="select select-bordered select-sm w-full"
                        >
                            <option value="">Не задано (legacy / гео)</option>
                            <option :value="MERCHANT_API_VALUE">От мерчанта (API)</option>
                            <option
                                v-for="source in sourcesFor(currency, direction.key)"
                                :key="source.id"
                                :value="`source:${source.id}`"
                            >
                                {{ source.name || source.pair }} ({{ source.type }})
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <InputError
                :message="Array.isArray(form.errors.bindings) ? form.errors.bindings.join(' ') : form.errors.bindings"
                class="mt-1"
            />

            <SaveButton :disabled="form.processing" :saved="form.recentlySuccessful" size="xs" />
        </div>
    </div>
</template>
