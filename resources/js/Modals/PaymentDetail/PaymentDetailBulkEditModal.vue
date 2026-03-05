<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import Select from "@/Components/Select.vue";
import NumberInputBlock from "@/Components/Form/NumberInputBlock.vue";
import { useModalStore } from "@/store/modal.js";
import { useViewStore } from "@/store/view.js";
import { storeToRefs } from "pinia";
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
});

const modalStore = useModalStore();
const { paymentDetailBulkEditModal } = storeToRefs(modalStore);
const viewStore = useViewStore();

const processing = ref(false);
const errors = ref({});

const currentUser = usePage().props.auth?.user;
const isAdminUser = computed(() => usePage().props.auth?.is_admin === true || usePage().props.auth?.role?.name === 'Super Admin');
const isVipUser = computed(() => {
    if (isAdminUser.value && viewStore.isAdminViewMode) {
        return true;
    }

    return currentUser?.is_vip === true || currentUser?.is_vip === 1 || currentUser?.is_temp_vip_active;
});

const scope = ref('all');
const tagId = ref(null);
const selectedFields = ref([]);

const form = ref({
    is_active: null,
    daily_limit: '',
    daily_successful_orders_limit: '',
    max_pending_orders_quantity: '',
    min_order_amount: '',
    max_order_amount: '',
    order_interval_minutes: '',
});

const scopeOptions = computed(() => [
    { id: 'all', name: 'Все реквизиты' },
    { id: 'tag', name: 'Реквизиты с тегом' },
    { id: 'without_tags', name: 'Реквизиты без тегов' },
]);

const tagOptions = computed(() => {
    return (props.tags || []).map((tag) => ({
        id: tag.id,
        name: tag.name,
    }));
});

const canEdit = computed(() => {
    if (scope.value === 'tag') {
        return !!tagId.value;
    }

    return true;
});

const fieldsDisabled = computed(() => processing.value || !canEdit.value);

const hasField = (field) => selectedFields.value.includes(field);

const resetState = () => {
    errors.value = {};
    processing.value = false;
    scope.value = 'all';
    tagId.value = null;
    selectedFields.value = [];
    form.value = {
        is_active: null,
        daily_limit: '',
        daily_successful_orders_limit: '',
        max_pending_orders_quantity: '',
        min_order_amount: '',
        max_order_amount: '',
        order_interval_minutes: '',
    };
};
const close = () => {
    modalStore.closeModal('paymentDetailBulkEdit');
};

const ensureBooleanValue = () => {
    if (hasField('is_active') && form.value.is_active === null) {
        errors.value = { ...errors.value, is_active: ['Выберите значение активности'] };
        return false;
    }

    return true;
};

const buildPayload = () => {
    const payload = {
        scope: scope.value,
        tag_id: scope.value === 'tag' ? tagId.value : null,
        fields: selectedFields.value,
    };

    if (hasField('is_active')) payload.is_active = form.value.is_active;
    if (hasField('daily_limit')) payload.daily_limit = form.value.daily_limit;
    if (hasField('daily_successful_orders_limit')) payload.daily_successful_orders_limit = form.value.daily_successful_orders_limit;
    if (hasField('max_pending_orders_quantity')) payload.max_pending_orders_quantity = form.value.max_pending_orders_quantity;
    if (hasField('min_order_amount')) payload.min_order_amount = form.value.min_order_amount;
    if (hasField('max_order_amount')) payload.max_order_amount = form.value.max_order_amount;
    if (hasField('order_interval_minutes')) payload.order_interval_minutes = form.value.order_interval_minutes;

    return payload;
};

watch(scope, (value) => {
    if (value !== 'tag') {
        tagId.value = null;
        errors.value.tag_id = null;
        return;
    }

    if (value === 'tag' && !tagOptions.value.length) {
        scope.value = 'all';
        tagId.value = null;
        errors.value._error = ['Теги не созданы — выберите «Все реквизиты».'];
    }
});

const submit = () => {
    errors.value = {};

    if (!selectedFields.value.length) {
        errors.value = { _error: ['Выберите хотя бы одно поле для изменения'] };
        return;
    }

    if (!canEdit.value) {
        errors.value = { _error: ['Сначала выберите, какие реквизиты редактируем'] };
        return;
    }

    if (!ensureBooleanValue()) {
        return;
    }

    processing.value = true;

    axios.patch(route('payment-details.bulk-update'), buildPayload(), {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            processing.value = false;
            if (res.data?.success || res.status === 200) {
                close();
                router.reload({ only: ['paymentDetails'] });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response && error.response.data) {
                if (error.response.data.errors) {
                    errors.value = error.response.data.errors;
                } else if (error.response.data.message) {
                    errors.value = { _error: [error.response.data.message] };
                }
            }
        });
};

watch(
    () => paymentDetailBulkEditModal.value.showed,
    async (state) => {
        if (state) {
            resetState();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="paymentDetailBulkEditModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Массовая настройка" />
        <ModalBody>
            <div class="space-y-6">
                <div class="rounded-box border border-base-300 p-4 space-y-4">
                    <div class="text-sm font-medium">
                        Какие реквизиты редактируем
                    </div>
                    <div>
                        <InputLabel
                            for="bulk_scope"
                            value="Выбор реквизитов"
                            class="mb-1"
                        />
                        <Select
                            id="bulk_scope"
                            v-model="scope"
                            :items="scopeOptions"
                            value="id"
                            name="name"
                            :disabled="processing"
                        />
                    </div>
                    <div v-if="scope === 'tag'">
                        <InputLabel
                            for="bulk_tag_id"
                            value="Тег"
                            :error="!!errors.tag_id?.[0]"
                            class="mb-1"
                        />
                        <Select
                            id="bulk_tag_id"
                            v-model="tagId"
                            :items="tagOptions"
                            value="id"
                            name="name"
                            default_title="Выберите тег"
                            :error="!!errors.tag_id?.[0]"
                            :disabled="processing"
                            @change="errors.tag_id = null"
                        />
                        <InputError :message="errors.tag_id?.[0]" class="mt-2" />
                        <div v-if="!tagOptions.length" class="text-xs text-base-content/70 mt-2">
                            Теги не созданы — можно выбрать только «Все реквизиты».
                        </div>
                    </div>
                    <div class="text-xs text-base-content/60">
                        Редактирование полей доступно после выбора набора реквизитов.
                    </div>
                </div>

                <InputError :message="errors._error?.[0]" />

                <div class="space-y-6" :class="{ 'opacity-60 pointer-events-none': !canEdit }">
                    <div v-if="isVipUser" class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Лимит на сумму сделки
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="min_order_amount" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Минимум</span>
                                </label>
                                <div v-if="hasField('min_order_amount')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.min_order_amount"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="min_order_amount"
                                        label="Минимум"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="max_order_amount" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Максимум</span>
                                </label>
                                <div v-if="hasField('max_order_amount')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.max_order_amount"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="max_order_amount"
                                        label="Максимум"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>
                    <div v-else class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-2">
                            Лимит на сумму сделки
                        </div>
                        <div class="text-xs text-base-content/60">
                            Поля доступны только для VIP.
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Дневные лимиты
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="daily_limit" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Объем сделок</span>
                                </label>
                                <div v-if="hasField('daily_limit')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.daily_limit"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="daily_limit"
                                        label="Объем сделок"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="daily_successful_orders_limit" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Количество сделок</span>
                                </label>
                                <div v-if="hasField('daily_successful_orders_limit')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.daily_successful_orders_limit"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="daily_successful_orders_limit"
                                        label="Количество сделок"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <div class="text-sm font-medium mb-3">
                            Ограничения активности
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="max_pending_orders_quantity" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Макс. активных</span>
                                </label>
                                <div v-if="hasField('max_pending_orders_quantity')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.max_pending_orders_quantity"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="max_pending_orders_quantity"
                                        label="Макс. активных"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="label cursor-pointer justify-start gap-3 items-start w-full">
                                    <input type="checkbox" class="checkbox checkbox-sm" value="order_interval_minutes" v-model="selectedFields" :disabled="fieldsDisabled" />
                                    <span class="label-text">Интервал (мин)</span>
                                </label>
                                <div v-if="hasField('order_interval_minutes')" class="grid gap-2">
                                    <NumberInputBlock
                                        v-model="form.order_interval_minutes"
                                        :form="{}"
                                        :errors="errors"
                                        :on-clear="(field) => (errors[field] = null)"
                                        field="order_interval_minutes"
                                        label="Интервал (мин)"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="text-xs text-base-content/70 mt-2">
                            Оставьте пустым для отключения лимита
                        </div>
                    </div>

                    <div>
                        <label class="label cursor-pointer mb-3 mt-3 justify-start gap-3">
                            <input type="checkbox" class="checkbox checkbox-sm" value="is_active" v-model="selectedFields" :disabled="fieldsDisabled" />
                            <span class="label-text">Реквизит включен</span>
                        </label>
                        <div v-if="hasField('is_active')" class="grid gap-2">
                            <Select
                                id="bulk_is_active"
                                v-model="form.is_active"
                                :items="[
                                    { id: true, name: 'Включен' },
                                    { id: false, name: 'Выключен' }
                                ]"
                                value="id"
                                name="name"
                                default_title="Выберите статус"
                                :error="!!errors.is_active?.[0]"
                                :disabled="processing"
                                @change="errors.is_active = null"
                            />
                            <InputError :message="errors.is_active?.[0]" />
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">Отмена</button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Применить
            </button>
        </ModalFooter>
    </Modal>
</template>
