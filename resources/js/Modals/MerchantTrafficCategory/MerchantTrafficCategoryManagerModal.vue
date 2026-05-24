<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import ModalFooter from '@/Components/Modals/Components/ModalFooter.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import { useMerchantTrafficCategories } from '@/composables/useMerchantTrafficCategories.js';
import { useModalStore } from '@/store/modal.js';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';

const modalStore = useModalStore();
const { merchantTrafficCategoryManagerModal } = storeToRefs(modalStore);
const {
    categories,
    merchantTrafficCategoriesEnabled,
    loading,
    fetchCategories,
    invalidateCategories,
} = useMerchantTrafficCategories();

const panelMode = ref('list');
const selectedCategoryId = ref(null);
const processing = ref(false);
const globalToggleProcessing = ref(false);
const errors = ref({});

const editorForm = ref({
    name: '',
    description: '',
    enabled_by_default: false,
});

const selectedCategory = computed(() => {
    if (!selectedCategoryId.value) {
        return null;
    }

    return categories.value.find((category) => Number(category.id) === Number(selectedCategoryId.value)) ?? null;
});

const modalTitle = computed(() => {
    if (panelMode.value === 'create') {
        return 'Новая категория';
    }

    if (panelMode.value === 'edit') {
        return 'Редактирование категории';
    }

    return 'Категории трафика';
});

const showEditor = computed(() => ['create', 'edit'].includes(panelMode.value));

const globalStatusBadgeClass = computed(() => (
    merchantTrafficCategoriesEnabled.value
        ? 'badge-success'
        : 'badge-ghost'
));

const globalStatusLabel = computed(() => (
    merchantTrafficCategoriesEnabled.value ? 'Категории включены' : 'Категории выключены'
));

const normalizeErrors = (rawErrors) => {
    const normalized = {};

    for (const [key, value] of Object.entries(rawErrors || {})) {
        normalized[key] = Array.isArray(value) ? value[0] : value;
    }

    return normalized;
};

const resetEditor = () => {
    panelMode.value = 'list';
    selectedCategoryId.value = null;
    editorForm.value = {
        name: '',
        description: '',
        enabled_by_default: false,
    };
    processing.value = false;
    errors.value = {};
};

const close = () => {
    modalStore.closeModal('merchantTrafficCategoryManager');
};

const loadCategories = async () => {
    await fetchCategories(true);
};

const selectCategory = (category) => {
    selectedCategoryId.value = category.id;
    panelMode.value = 'edit';
    editorForm.value = {
        name: category.name || '',
        description: category.description || '',
        enabled_by_default: Boolean(category.enabled_by_default),
    };
    errors.value = {};
};

const startCreate = () => {
    selectedCategoryId.value = null;
    panelMode.value = 'create';
    editorForm.value = {
        name: '',
        description: '',
        enabled_by_default: false,
    };
    errors.value = {};
};

const cancelEditor = () => {
    if (panelMode.value === 'create') {
        resetEditor();

        return;
    }

    if (selectedCategory.value) {
        selectCategory(selectedCategory.value);

        return;
    }

    panelMode.value = 'list';
    errors.value = {};
};

const notifyCategoriesChanged = () => {
    const callback = merchantTrafficCategoryManagerModal.value.params?.onCategoriesChanged;

    if (typeof callback === 'function') {
        callback();
    }
};

const saveCategory = () => {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    const payload = {
        name: editorForm.value.name.trim(),
        description: editorForm.value.description.trim(),
        enabled_by_default: editorForm.value.enabled_by_default,
    };

    const request = panelMode.value === 'edit'
        ? axios.patch(route('admin.traffic-categories.update', selectedCategoryId.value), payload, {
            headers: { Accept: 'application/json' },
        })
        : axios.post(route('admin.traffic-categories.store'), payload, {
            headers: { Accept: 'application/json' },
        });

    request
        .then(async (response) => {
            if (!response.data?.success) {
                return;
            }

            invalidateCategories();
            await loadCategories();
            notifyCategoriesChanged();

            const saved = response.data?.data;

            if (saved?.id) {
                selectCategory(saved);
            }
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

const confirmDeleteCategory = () => {
    if (!selectedCategory.value || processing.value) {
        return;
    }

    modalStore.openConfirmModal({
        title: `Удалить категорию «${selectedCategory.value.name}»?`,
        body: 'Категория удалится у мерчантов и трейдеров. Если у мерчанта не останется категорий, его заявки снова будут доступны всем трейдерам.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            processing.value = true;
            errors.value = {};

            axios.delete(route('admin.traffic-categories.destroy', selectedCategory.value.id), {
                headers: { Accept: 'application/json' },
            })
                .then(async (response) => {
                    if (!response.data?.success) {
                        return;
                    }

                    invalidateCategories();
                    await loadCategories();
                    notifyCategoriesChanged();
                    resetEditor();
                })
                .finally(() => {
                    processing.value = false;
                });
        },
    });
};

const confirmApplyToAllTraders = () => {
    if (!selectedCategory.value || processing.value) {
        return;
    }

    modalStore.openConfirmModal({
        title: 'Применить ко всем трейдерам?',
        body: 'Если категория включена по умолчанию, она включится всем трейдерам. Если выключена — выключится всем трейдерам.',
        confirm_button_name: 'Применить',
        confirm: () => {
            processing.value = true;

            axios.post(route('admin.traffic-categories.apply-to-all-traders', selectedCategory.value.id), {}, {
                headers: { Accept: 'application/json' },
            })
                .then(async (response) => {
                    if (!response.data?.success) {
                        return;
                    }

                    invalidateCategories();
                    await loadCategories();
                })
                .finally(() => {
                    processing.value = false;
                });
        },
    });
};

const toggleGlobalFeature = () => {
    if (globalToggleProcessing.value) {
        return;
    }

    const nextEnabled = !merchantTrafficCategoriesEnabled.value;

    globalToggleProcessing.value = true;

    axios.patch(route('admin.traffic-categories.settings.enabled.update'), {
        enabled: nextEnabled,
    }, {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            if (!response.data?.success) {
                return;
            }

            merchantTrafficCategoriesEnabled.value = Boolean(
                response.data?.data?.merchant_traffic_categories_enabled,
            );
            notifyCategoriesChanged();
        })
        .finally(() => {
            globalToggleProcessing.value = false;
        });
};

watch(
    () => merchantTrafficCategoryManagerModal.value.showed,
    async (showed) => {
        if (showed) {
            resetEditor();
            await loadCategories();
        } else {
            resetEditor();
        }
    },
);
</script>

<template>
    <Modal :show="merchantTrafficCategoryManagerModal.showed" maxWidth="4xl" @close="close">
        <ModalHeader :title="modalTitle" @close="close" />
        <ModalBody>
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200/50 p-3">
                    <div class="space-y-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">Глобальная фильтрация</span>
                            <span class="badge badge-sm" :class="globalStatusBadgeClass">
                                {{ globalStatusLabel }}
                            </span>
                        </div>
                        <p class="text-xs text-base-content/70 max-w-2xl">
                            Когда категории выключены, они не влияют на раздачу трафика. Трейдеры их не видят. Можно спокойно настроить категории заранее и включить позже.
                        </p>
                    </div>
                    <label class="label cursor-pointer gap-2 p-0 shrink-0">
                        <span class="label-text text-xs">Включить фильтрацию</span>
                        <input
                            type="checkbox"
                            class="toggle toggle-primary toggle-sm"
                            :checked="merchantTrafficCategoriesEnabled"
                            :disabled="globalToggleProcessing || loading"
                            @change="toggleGlobalFeature"
                        />
                    </label>
                </div>

                <div v-if="loading && !categories.length" class="py-8 text-center text-sm text-base-content/70">
                    Загрузка категорий…
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="btn btn-xs btn-primary w-full min-h-8 h-8"
                            :disabled="processing"
                            @click="startCreate"
                        >
                            Создать категорию
                        </button>

                        <div v-if="!categories.length" class="text-xs text-base-content/70 py-4">
                            Категории ещё не созданы.
                        </div>

                        <div v-else class="space-y-1 max-h-[24rem] overflow-y-auto pr-1">
                            <button
                                v-for="category in categories"
                                :key="category.id"
                                type="button"
                                class="w-full text-left rounded-box border px-2 py-1.5 transition-colors"
                                :class="Number(selectedCategoryId) === Number(category.id)
                                    ? 'border-primary bg-primary/10'
                                    : 'border-base-300 hover:border-base-content/30'"
                                :disabled="processing"
                                @click="selectCategory(category)"
                            >
                                <div class="font-medium text-xs truncate">
                                    {{ category.name }}
                                </div>
                                <div class="text-[11px] text-base-content/60 mt-0.5">
                                    {{ category.enabled_by_default ? 'По умолчанию: вкл.' : 'По умолчанию: выкл.' }}
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div
                            v-if="panelMode === 'list' && !showEditor"
                            class="text-sm text-base-content/70 py-10 text-center"
                        >
                            Выберите категорию слева или создайте новую.
                        </div>

                        <div v-else class="space-y-4">
                            <div>
                                <InputLabel
                                    for="traffic_category_name"
                                    value="Название"
                                    :error="!!errors.name"
                                    class="mb-0.5 [&_.label-text]:text-xs"
                                />
                                <TextInput
                                    id="traffic_category_name"
                                    v-model="editorForm.name"
                                    type="text"
                                    class="w-full input-sm h-8 min-h-8 text-sm"
                                    :class="{ 'input-error': !!errors.name }"
                                    autocomplete="off"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.name" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel
                                    for="traffic_category_description"
                                    value="Описание"
                                    :error="!!errors.description"
                                    class="mb-0.5 [&_.label-text]:text-xs"
                                />
                                <TextArea
                                    id="traffic_category_description"
                                    v-model="editorForm.description"
                                    :rows="4"
                                    :error="!!errors.description"
                                    :disabled="processing"
                                />
                                <InputError :message="errors.description" class="mt-1" />
                            </div>

                            <div class="flex items-start gap-3">
                                <input
                                    id="traffic_category_enabled_by_default"
                                    v-model="editorForm.enabled_by_default"
                                    type="checkbox"
                                    class="checkbox checkbox-sm mt-0.5"
                                    :disabled="processing"
                                />
                                <div>
                                    <label for="traffic_category_enabled_by_default" class="label cursor-pointer justify-start p-0">
                                        <span class="label-text text-sm font-medium">Включать новым трейдерам по умолчанию</span>
                                    </label>
                                    <p class="text-xs text-base-content/70 mt-1">
                                        Это влияет только на новых трейдеров. Уже выбранные настройки трейдеров не меняются.
                                    </p>
                                    <InputError :message="errors.enabled_by_default" class="mt-1" />
                                </div>
                            </div>

                            <div
                                v-if="panelMode === 'edit' && selectedCategory"
                                class="flex flex-wrap gap-2 pt-1 border-t border-base-300"
                            >
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline"
                                    :disabled="processing"
                                    @click="confirmApplyToAllTraders"
                                >
                                    Применить ко всем трейдерам
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-error btn-outline"
                                    :disabled="processing"
                                    @click="confirmDeleteCategory"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" :disabled="processing" @click="close">
                Закрыть
            </button>

            <template v-if="showEditor">
                <button type="button" class="btn btn-sm btn-ghost" :disabled="processing" @click="cancelEditor">
                    Отмена
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                    @click="saveCategory"
                >
                    Сохранить
                </button>
            </template>
        </ModalFooter>
    </Modal>
</template>
