<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Select from '@/Components/Select.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UserFormSection from '@/Modals/User/Partials/UserFormSection.vue';
import UserFormToggle from '@/Modals/User/Partials/UserFormToggle.vue';
import AntiFraudTrafficFields from '@/Modals/Admin/Partials/AntiFraudTrafficFields.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';

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

const isEditing = computed(() => !!form.id);

const modalTitle = computed(() => {
    if (isEditing.value) {
        const merchant = props.merchants.find((item) => item.id === Number(form.merchant_id));
        const merchantName = merchant?.name || merchant?.uuid;

        return merchantName
            ? `Антифрод — ${merchantName}`
            : 'Антифрод — редактирование';
    }

    return 'Антифрод — новая настройка';
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
    <Modal :show="antiFraudSettingModal.showed" maxWidth="5xl" @close="close">
        <ModalHeader :title="modalTitle" @close="close" />

        <ModalBody>
            <form class="space-y-4" @submit.prevent="submit">
                <UserFormSection
                    title="Общие"
                    description="Один мерчант — один набор настроек. Первичный трафик — клиент без успешных сделок, вторичный — с хотя бы одной успешной."
                    compact
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                            <InputError class="mt-1" :message="form.errors.merchant_id" />
                        </div>

                        <UserFormToggle
                            v-model="form.enabled"
                            label="Антифрод включен"
                            hint="Если выключить — правила не применяются."
                            :disabled="form.processing"
                        />
                    </div>
                </UserFormSection>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <UserFormSection
                        title="Первичный трафик"
                        compact
                    >
                        <AntiFraudTrafficFields
                            type="primary"
                            :form="form"
                            @add-rate-limit="addRateLimit"
                            @remove-rate-limit="removeRateLimit"
                        />
                    </UserFormSection>

                    <UserFormSection
                        title="Вторичный трафик"
                        compact
                    >
                        <div class="space-y-3">
                            <UserFormToggle
                                v-model="form.secondary_enabled"
                                label="Фильтры включены"
                                hint="Если выключить, ограничения для вторичного трафика не применяются."
                                :disabled="form.processing"
                            />

                            <AntiFraudTrafficFields
                                v-if="form.secondary_enabled"
                                type="secondary"
                                :form="form"
                                @add-rate-limit="addRateLimit"
                                @remove-rate-limit="removeRateLimit"
                            />
                        </div>
                    </UserFormSection>
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
