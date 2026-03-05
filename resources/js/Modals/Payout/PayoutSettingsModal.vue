<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputHelper from "@/Components/InputHelper.vue";
import { storeToRefs } from "pinia";
import { useModalStore } from "@/store/modal.js";
import { computed, ref, watch } from "vue";

const modalStore = useModalStore();
const { payoutSettingsModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const currencies = ref([]);
const form = ref({
    settings: {},
});

const close = () => {
    modalStore.closeModal('payoutSettings');
};

const errorMessage = (code, field) => {
    const key = `settings.${code}.${field}`;
    return errors.value?.[key]?.[0] ?? null;
};

const resolveCode = (currency) => (currency?.code || '').toLowerCase();

const setDefaults = (payload) => {
    const list = payload?.currencies || [];
    const settings = payload?.settings || {};

    currencies.value = list;
    const nextSettings = {};

    list.forEach((currency) => {
        const code = resolveCode(currency);
        const current = settings?.[code] || settings?.[code.toUpperCase()] || {};
        nextSettings[code] = {
            total_commission_rate: current.total_commission_rate ?? 5,
            trader_commission_rate: current.trader_commission_rate ?? 4,
            reservation_time_for_payouts: current.reservation_time_for_payouts ?? 20,
        };
    });

    form.value.settings = nextSettings;
};

const loadData = () => {
    loading.value = true;
    axios.get(route('admin.payouts.settings-data'))
        .then((response) => {
            const data = response.data?.data || response.data || {};
            setDefaults(data);
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.patch(route('admin.payouts.settings.update'), {
        settings: form.value.settings,
    }, {
        headers: { 'Accept': 'application/json' },
    })
        .then((response) => {
            processing.value = false;
            if (response.data?.success || response.status === 200) {
                close();
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { message: [error.response.data.message] };
            }
        });
};

const settingsList = computed(() => currencies.value.map((currency) => {
    const code = resolveCode(currency);
    return {
        currency,
        code,
        settings: form.value.settings?.[code] ?? {},
    };
}));

watch(
    () => payoutSettingsModal.value.showed,
    (state) => {
        if (state) {
            errors.value = {};
            loadData();
        } else {
            errors.value = {};
            currencies.value = [];
            form.value.settings = {};
        }
    }
);
</script>

<template>
    <Modal :show="payoutSettingsModal.showed" @close="close" maxWidth="4xl">
        <ModalHeader @close="close" title="Настройки выплат по валютам" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <div v-else class="space-y-4">
                <div v-if="errors.message?.[0]" class="alert alert-error text-sm">
                    {{ errors.message?.[0] }}
                </div>

                <div class="text-sm text-base-content/70">
                    Указанные параметры используются только если платежный метод не выбран.
                </div>

                <div class="space-y-4">
                    <div
                        v-for="item in settingsList"
                        :key="item.code"
                        class="rounded-box border border-base-300 bg-base-100/60 p-4 space-y-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-base-content">
                                {{ item.currency.code }} — {{ item.currency.name }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel
                                    :for="`total-${item.code}`"
                                    value="Total комиссия (%)"
                                    :error="!!errorMessage(item.code, 'total_commission_rate')"
                                />
                                <NumberInput
                                    :id="`total-${item.code}`"
                                    v-model="form.settings[item.code].total_commission_rate"
                                    class="mt-1 block w-full"
                                    step="0.1"
                                    placeholder="5"
                                />
                                <InputError :message="errorMessage(item.code, 'total_commission_rate')" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel
                                    :for="`trader-${item.code}`"
                                    value="Комиссия трейдера (%)"
                                    :error="!!errorMessage(item.code, 'trader_commission_rate')"
                                />
                                <NumberInput
                                    :id="`trader-${item.code}`"
                                    v-model="form.settings[item.code].trader_commission_rate"
                                    class="mt-1 block w-full"
                                    step="0.1"
                                    placeholder="4"
                                />
                                <InputError :message="errorMessage(item.code, 'trader_commission_rate')" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel
                                    :for="`time-${item.code}`"
                                    value="Время на выплату (мин)"
                                    :error="!!errorMessage(item.code, 'reservation_time_for_payouts')"
                                />
                                <NumberInput
                                    :id="`time-${item.code}`"
                                    v-model="form.settings[item.code].reservation_time_for_payouts"
                                    class="mt-1 block w-full"
                                    placeholder="20"
                                />
                                <InputError :message="errorMessage(item.code, 'reservation_time_for_payouts')" class="mt-2" />
                                <InputHelper
                                    v-if="!errorMessage(item.code, 'reservation_time_for_payouts')"
                                    model-value="Сколько минут даётся трейдеру на отправку."
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">
                Отмена
            </button>
            <button
                @click="submit"
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing }"
                :disabled="processing"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
