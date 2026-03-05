<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import Select from "@/Components/Select.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import { useModalStore } from "@/store/modal.js";
import { storeToRefs } from "pinia";

const props = defineProps({
    merchants: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Array,
        default: () => [],
    },
});

const modalStore = useModalStore();
const { antiFraudSettingModal } = storeToRefs(modalStore);

const usedMerchantIds = computed(() => {
    return new Set((props.settings || []).map((setting) => setting?.merchant_id).filter(Boolean));
});

const merchantOptions = computed(() => {
    return props.merchants
        .filter((merchant) => {
            if (!merchant?.id) {
                return false;
            }

            if (form.merchant_id && Number(form.merchant_id) === merchant.id) {
                return true;
            }

            return !usedMerchantIds.value.has(merchant.id);
        })
        .map((merchant) => ({
            value: merchant.id,
            name: merchant.name || merchant.uuid || `#${merchant.id}`,
        }));
});

const buildRateLimits = (limits) => {
    if (Array.isArray(limits) && limits.length) {
        return limits.map((limit) => ({
            count: limit?.count ?? '',
            minutes: limit?.minutes ?? '',
        }));
    }

    return [{ count: '', minutes: '' }];
};

const emptyForm = () => ({
    id: null,
    merchant_id: '',
    enabled: false,
    primary_max_pending: '',
    primary_rate_limits: [{ count: '', minutes: '' }],
    primary_failed_limit: '',
    primary_block_days: '',
    secondary_enabled: true,
    secondary_max_pending: '',
    secondary_rate_limits: [{ count: '', minutes: '' }],
    secondary_failed_limit: '',
    secondary_block_days: '',
});

const form = useForm(emptyForm());

const fillForm = (setting) => {
    form.id = setting?.id ?? null;
    form.merchant_id = setting?.merchant_id ?? '';
    form.enabled = !!setting?.enabled;
    form.primary_max_pending = setting?.primary_max_pending ?? '';
    form.primary_rate_limits = buildRateLimits(setting?.primary_rate_limits);
    form.primary_failed_limit = setting?.primary_failed_limit ?? '';
    form.primary_block_days = setting?.primary_block_days ?? '';
    form.secondary_enabled = setting?.secondary_enabled ?? true;
    form.secondary_max_pending = setting?.secondary_max_pending ?? '';
    form.secondary_rate_limits = buildRateLimits(setting?.secondary_rate_limits);
    form.secondary_failed_limit = setting?.secondary_failed_limit ?? '';
    form.secondary_block_days = setting?.secondary_block_days ?? '';
};

const resetForm = () => {
    Object.assign(form, emptyForm());
    form.clearErrors();
};

const resetFormForMerchant = (merchantId) => {
    const fresh = emptyForm();
    Object.assign(form, fresh);
    form.merchant_id = merchantId;
    form.clearErrors();
};

const close = () => {
    modalStore.closeModal('antiFraudSetting');
};

watch(
    () => antiFraudSettingModal.value.showed,
    (showed) => {
        if (!showed) {
            resetForm();
            return;
        }

        const { setting, merchantId } = antiFraudSettingModal.value.params ?? {};

        if (setting) {
            fillForm(setting);
            return;
        }

        if (merchantId) {
            resetFormForMerchant(merchantId);
        } else {
            resetForm();
        }
    }
);

const addRateLimit = (type) => {
    const key = type === 'primary' ? 'primary_rate_limits' : 'secondary_rate_limits';
    form[key] = [...(form[key] || []), { count: '', minutes: '' }];
};

const removeRateLimit = (type, index) => {
    const key = type === 'primary' ? 'primary_rate_limits' : 'secondary_rate_limits';
    if ((form[key] || []).length <= 1) {
        return;
    }
    form[key] = form[key].filter((_, idx) => idx !== index);
};

const normalizeRateLimits = (limits) => {
    return (limits || [])
        .filter((limit) => limit?.count && limit?.minutes)
        .map((limit) => ({
            count: Number(limit.count),
            minutes: Number(limit.minutes),
        }));
};

const submit = () => {
    if (!form.merchant_id || form.processing) {
        return;
    }

    form.transform((data) => ({
        ...data,
        primary_rate_limits: normalizeRateLimits(data.primary_rate_limits),
        secondary_rate_limits: normalizeRateLimits(data.secondary_rate_limits),
    }));

    if (form.id) {
        form.patch(route('admin.anti-fraud.settings.update', form.id), {
            preserveScroll: true,
            onSuccess: close,
        });
        return;
    }

    form.post(route('admin.anti-fraud.settings.store'), {
        preserveScroll: true,
        onSuccess: close,
    });
};
</script>

<template>
    <Modal :show="antiFraudSettingModal.showed" maxWidth="7xl" @close="close">
        <ModalHeader title="Антифрод — настройки" @close="close" />

        <ModalBody>
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <InputLabel
                                value="Мерчант"
                                :error="!!form.errors.merchant_id"
                            />
                            <Select
                                v-model="form.merchant_id"
                                :items="merchantOptions"
                                value="value"
                                name="name"
                                default_title="Выберите мерчанта"
                                :required="false"
                                :error="!!form.errors.merchant_id"
                                class="mt-1"
                            />
                            <InputError class="mt-2" :message="form.errors.merchant_id" />
                            <p class="text-xs text-base-content/60 mt-2">
                                Один мерчант — один набор настроек.
                            </p>
                        </div>

                        <label class="label cursor-pointer justify-start gap-3">
                            <span class="label-text">Антифрод включен</span>
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.enabled"
                                :disabled="form.processing"
                            />
                        </label>
                        <p class="text-xs text-base-content/60">
                            Если выключить — правила не применяются.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg bg-base-200 p-4 text-sm text-base-content/70">
                            <p>Первичный трафик — клиент без успешных сделок.</p>
                            <p class="mt-1">Вторичный — клиент с хотя бы одной успешной сделкой.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="font-semibold">Первичный трафик</h4>
                        <div>
                            <InputLabel value="Макс. активных сделок" :error="!!form.errors.primary_max_pending" />
                            <NumberInput
                                v-model="form.primary_max_pending"
                                class="mt-1 block w-full"
                                min="0"
                                :error="!!form.errors.primary_max_pending"
                            />
                            <InputError class="mt-2" :message="form.errors.primary_max_pending" />
                            <p class="text-xs text-base-content/60 mt-2">
                                Сколько pending-сделок может быть одновременно.
                            </p>
                        </div>

                        <div>
                            <InputLabel value="Лимиты по интервалам" />
                            <div class="space-y-2 mt-2">
                                <div
                                    v-for="(limit, index) in form.primary_rate_limits"
                                    :key="`primary-${index}`"
                                    class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end"
                                >
                                    <NumberInput
                                        v-model="limit.count"
                                        min="1"
                                        class="block w-full"
                                        placeholder="Кол-во"
                                    />
                                    <NumberInput
                                        v-model="limit.minutes"
                                        min="1"
                                        class="block w-full"
                                        placeholder="Минут"
                                    />
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-ghost text-error"
                                        @click="removeRateLimit('primary', index)"
                                        :disabled="form.primary_rate_limits.length <= 1"
                                    >
                                        Удалить
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline" @click="addRateLimit('primary')">
                                    Добавить интервал
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.primary_rate_limits" />
                            <p class="text-xs text-base-content/60 mt-2">
                                Лимит означает: не более N созданных сделок за M минут (N / M).
                                Пример: 3 / 1м, 10 / 5м, 20 / 60м.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Лимит неуспешных подряд" :error="!!form.errors.primary_failed_limit" />
                                <NumberInput
                                    v-model="form.primary_failed_limit"
                                    class="mt-1 block w-full"
                                    min="0"
                                    :error="!!form.errors.primary_failed_limit"
                                />
                                <InputError class="mt-2" :message="form.errors.primary_failed_limit" />
                                <p class="text-xs text-base-content/60 mt-2">
                                    После этого числа неуспешных подряд клиент блокируется.
                                </p>
                            </div>
                            <div>
                                <InputLabel value="Блокировка (дней)" :error="!!form.errors.primary_block_days" />
                                <NumberInput
                                    v-model="form.primary_block_days"
                                    class="mt-1 block w-full"
                                    min="0"
                                    :error="!!form.errors.primary_block_days"
                                />
                                <InputError class="mt-2" :message="form.errors.primary_block_days" />
                                <p class="text-xs text-base-content/60 mt-2">
                                    Сколько дней клиент будет заблокирован.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-semibold">Вторичный трафик</h4>
                            <label class="label cursor-pointer gap-3">
                                <span class="label-text text-sm">Фильтры включены</span>
                                <input
                                    type="checkbox"
                                    class="toggle toggle-primary toggle-sm"
                                    v-model="form.secondary_enabled"
                                    :disabled="form.processing"
                                />
                            </label>
                        </div>
                        <p class="text-xs text-base-content/60">
                            Если выключить, ограничения для вторичного трафика не применяются.
                        </p>
                        <div v-if="form.secondary_enabled" class="space-y-4">
                        <div>
                            <InputLabel value="Макс. активных сделок" :error="!!form.errors.secondary_max_pending" />
                            <NumberInput
                                v-model="form.secondary_max_pending"
                                class="mt-1 block w-full"
                                min="0"
                                :error="!!form.errors.secondary_max_pending"
                            />
                            <InputError class="mt-2" :message="form.errors.secondary_max_pending" />
                            <p class="text-xs text-base-content/60 mt-2">
                                Отдельный лимит pending-сделок для вторичного трафика.
                            </p>
                        </div>

                        <div>
                            <InputLabel value="Лимиты по интервалам" />
                            <div class="space-y-2 mt-2">
                                <div
                                    v-for="(limit, index) in form.secondary_rate_limits"
                                    :key="`secondary-${index}`"
                                    class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end"
                                >
                                    <NumberInput
                                        v-model="limit.count"
                                        min="1"
                                        class="block w-full"
                                        placeholder="Кол-во"
                                    />
                                    <NumberInput
                                        v-model="limit.minutes"
                                        min="1"
                                        class="block w-full"
                                        placeholder="Минут"
                                    />
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-ghost text-error"
                                        @click="removeRateLimit('secondary', index)"
                                        :disabled="form.secondary_rate_limits.length <= 1"
                                    >
                                        Удалить
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline" @click="addRateLimit('secondary')">
                                    Добавить интервал
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.secondary_rate_limits" />
                            <p class="text-xs text-base-content/60 mt-2">
                                Лимит означает: не более N созданных сделок за M минут (N / M).
                                Можно задавать несколько ограничений одновременно.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Лимит неуспешных подряд" :error="!!form.errors.secondary_failed_limit" />
                                <NumberInput
                                    v-model="form.secondary_failed_limit"
                                    class="mt-1 block w-full"
                                    min="0"
                                    :error="!!form.errors.secondary_failed_limit"
                                />
                                <InputError class="mt-2" :message="form.errors.secondary_failed_limit" />
                                <p class="text-xs text-base-content/60 mt-2">
                                    Блокируем только если неуспешные сделки идут подряд.
                                </p>
                            </div>
                            <div>
                                <InputLabel value="Блокировка (дней)" :error="!!form.errors.secondary_block_days" />
                                <NumberInput
                                    v-model="form.secondary_block_days"
                                    class="mt-1 block w-full"
                                    min="0"
                                    :error="!!form.errors.secondary_block_days"
                                />
                                <InputError class="mt-2" :message="form.errors.secondary_block_days" />
                                <p class="text-xs text-base-content/60 mt-2">
                                    Период блокировки для вторичного трафика.
                                </p>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </form>
        </ModalBody>

        <ModalFooter>
            <PrimaryButton type="button" :disabled="form.processing || !form.merchant_id" @click="submit">
                Сохранить
            </PrimaryButton>
            <button type="button" class="btn btn-ghost" :disabled="form.processing" @click="resetForm">
                Сбросить
            </button>
        </ModalFooter>
    </Modal>
</template>
