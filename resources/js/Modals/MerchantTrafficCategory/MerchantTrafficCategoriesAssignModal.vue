<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import Multiselect from '@/Components/Form/Multiselect.vue';
import InputError from '@/Components/InputError.vue';
import { useMerchantTrafficCategories } from '@/composables/useMerchantTrafficCategories.js';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';

const modalStore = useModalStore();
const { merchantTrafficCategoriesAssignModal } = storeToRefs(modalStore);
const { categories, loading, fetchCategories, categoryOptions } = useMerchantTrafficCategories();

const merchant = ref(null);
const selectedCategoryIds = ref([]);
const processing = ref(false);
const errors = ref({});

const title = computed(() => {
    if (!merchant.value) {
        return 'Категории мерчанта';
    }

    return `Категории — ${merchant.value.name ?? `#${merchant.value.id}`}`;
});

const close = () => {
    modalStore.closeModal('merchantTrafficCategoriesAssign');
};

const resetState = () => {
    merchant.value = null;
    selectedCategoryIds.value = [];
    processing.value = false;
    errors.value = {};
};

const normalizeErrors = (rawErrors) => {
    const normalized = {};

    for (const [key, value] of Object.entries(rawErrors || {})) {
        normalized[key] = Array.isArray(value) ? value[0] : value;
    }

    return normalized;
};

const loadData = async () => {
    const params = merchantTrafficCategoriesAssignModal.value.params;

    if (!params?.merchant) {
        return;
    }

    merchant.value = params.merchant;
    selectedCategoryIds.value = [...(params.merchant.categories ?? [])];

    await fetchCategories(true);
};

const save = () => {
    if (!merchant.value || processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    axios.patch(route('admin.merchants.categories.update', merchant.value.id), {
        category_ids: selectedCategoryIds.value,
    }, {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            if (!response.data?.success) {
                return;
            }

            const updatedMerchant = response.data?.data?.merchant;

            const callback = merchantTrafficCategoriesAssignModal.value.params?.onUpdated;

            if (typeof callback === 'function') {
                callback(updatedMerchant);
            }

            close();
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                errors.value = normalizeErrors(error.response.data.errors);
            }
        })
        .finally(() => {
            processing.value = false;
        });
};

watch(
    () => merchantTrafficCategoriesAssignModal.value.showed,
    async (showed) => {
        if (showed) {
            await loadData();
        } else {
            resetState();
        }
    },
);
</script>

<template>
    <Modal :show="merchantTrafficCategoriesAssignModal.showed" maxWidth="lg" @close="close">
        <ModalHeader :title="title" @close="close" />
        <ModalBody>
            <div v-if="loading && !categories.length" class="py-6 text-center text-sm text-base-content/70">
                <span class="loading loading-spinner loading-sm mr-2 align-middle"></span>
                <span class="align-middle">Загрузка категорий…</span>
            </div>
            <div v-else class="space-y-3">
                <p class="text-sm text-base-content/70">
                    Если у мерчанта нет категорий, его заявки доступны всем трейдерам без фильтрации по категориям.
                </p>

                <div>
                    <Multiselect
                        v-model="selectedCategoryIds"
                        :options="categoryOptions()"
                        label-key="name"
                        value-key="id"
                        :enable-search="true"
                        placeholder="Выберите категории"
                        :disabled="processing"
                    />
                    <InputError :message="errors.category_ids" class="mt-2" />
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" :disabled="processing" @click="close">
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing }"
                :disabled="processing || loading"
                @click="save"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
