<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import { useModalStore } from "@/store/modal.js";
import { storeToRefs } from "pinia";
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
});

const modalStore = useModalStore();
const { paymentDetailTagManageModal } = storeToRefs(modalStore);

const processingIds = ref({});
const errors = ref({});
const tags = ref([]);

const createProcessing = ref(false);
const createErrors = ref({});
const createForm = ref({
    name: '',
    color: '#6366f1',
});

const resetCreateForm = () => {
    createProcessing.value = false;
    createErrors.value = {};
    createForm.value = {
        name: '',
        color: '#6366f1',
    };
};

const resetState = () => {
    processingIds.value = {};
    errors.value = {};
    tags.value = (props.tags || []).map((tag) => ({
        id: tag.id,
        name: tag.name,
        color: tag.color,
    }));
    resetCreateForm();
};

const createTag = () => {
    createProcessing.value = true;
    createErrors.value = {};

    axios.post(route('payment-detail-tags.store'), createForm.value, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            createProcessing.value = false;
            if (res.data?.success || res.status === 200 || res.status === 201) {
                resetCreateForm();
                router.reload({ only: ['paymentDetails', 'paymentDetailTags'] });
            }
        })
        .catch((error) => {
            createProcessing.value = false;
            if (error.response && error.response.data && error.response.data.errors) {
                createErrors.value = error.response.data.errors;
            }
        });
};

const close = () => {
    modalStore.closeModal('paymentDetailTagManage');
};

const setProcessing = (id, state) => {
    processingIds.value = {
        ...processingIds.value,
        [id]: state,
    };
};

const updateTag = (tag) => {
    setProcessing(tag.id, true);
    errors.value = { ...errors.value, [tag.id]: {} };

    axios.patch(route('payment-detail-tags.update', tag.id), {
        name: tag.name,
        color: tag.color,
    }, {
        headers: { 'Accept': 'application/json' }
    })
        .then((res) => {
            setProcessing(tag.id, false);
            if (res.data?.success || res.status === 200) {
                router.reload({ only: ['paymentDetails', 'paymentDetailTags'] });
            }
        })
        .catch((error) => {
            setProcessing(tag.id, false);
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = { ...errors.value, [tag.id]: error.response.data.errors };
            }
        });
};

const confirmDeleteTag = (tag) => {
    modalStore.openConfirmModal({
        title: `Удалить тег "${tag.name}"?`,
        body: 'Тег будет удалён и отвязан от всех реквизитов.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            setProcessing(tag.id, true);
            axios.delete(route('payment-detail-tags.destroy', tag.id), {
                headers: { 'Accept': 'application/json' }
            })
                .then((res) => {
                    setProcessing(tag.id, false);
                    if (res.data?.success || res.status === 200) {
                        tags.value = tags.value.filter((item) => item.id !== tag.id);
                        router.reload({ only: ['paymentDetails', 'paymentDetailTags'] });
                    }
                })
                .catch(() => {
                    setProcessing(tag.id, false);
                });
        }
    });
};

watch(
    () => paymentDetailTagManageModal.value.showed,
    (state) => {
        if (state) {
            resetState();
        } else {
            resetState();
        }
    }
);

watch(
    () => props.tags,
    (newTags) => {
        if (!paymentDetailTagManageModal.value.showed) {
            return;
        }

        tags.value = (newTags || []).map((tag) => ({
            id: tag.id,
            name: tag.name,
            color: tag.color,
        }));
    }
);
</script>

<template>
    <Modal :show="paymentDetailTagManageModal.showed" @close="close" maxWidth="lg">
        <ModalHeader @close="close" title="Теги" />
        <ModalBody>
            <div class="rounded-box border border-base-300 p-3 mb-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-base-content/60 mb-2">
                    Новый тег
                </div>
                <form @submit.prevent="createTag" class="grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
                    <div>
                        <InputLabel
                            for="create_tag_name"
                            value="Название (до 10 символов)"
                            :error="!!createErrors.name?.[0]"
                            class="mb-1"
                        />
                        <TextInput
                            id="create_tag_name"
                            v-model="createForm.name"
                            type="text"
                            maxlength="10"
                            class="w-full"
                            :error="!!createErrors.name?.[0]"
                            @input="createErrors.name = null"
                            :disabled="createProcessing"
                        />
                        <InputError :message="createErrors.name?.[0]" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel
                            for="create_tag_color"
                            value="Цвет"
                            :error="!!createErrors.color?.[0]"
                            class="mb-1 mr-3"
                        />
                        <input
                            id="create_tag_color"
                            v-model="createForm.color"
                            type="color"
                            class="input input-bordered w-10 h-10 p-1"
                            :disabled="createProcessing"
                        />
                        <InputError :message="createErrors.color?.[0]" class="mt-2" />
                    </div>
                    <div class="flex items-center md:justify-end">
                        <button
                            type="submit"
                            class="btn btn-sm btn-primary"
                            :class="{ 'btn-disabled': createProcessing }"
                            :disabled="createProcessing"
                        >
                            Создать
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="!tags.length" class="text-center text-sm text-base-content/70 py-6">
                Теги пока не созданы
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="tag in tags"
                    :key="tag.id"
                    class="rounded-box border border-base-300 p-3"
                >
                    <div class="grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <InputLabel
                                :for="`tag_name_${tag.id}`"
                                value="Название"
                                :error="!!errors[tag.id]?.name?.[0]"
                                class="mb-1"
                            />
                            <TextInput
                                :id="`tag_name_${tag.id}`"
                                v-model="tag.name"
                                type="text"
                                maxlength="10"
                                class="w-full"
                                :error="!!errors[tag.id]?.name?.[0]"
                                :disabled="processingIds[tag.id]"
                            />
                            <InputError :message="errors[tag.id]?.name?.[0]" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel
                                :for="`tag_color_${tag.id}`"
                                value="Цвет"
                                :error="!!errors[tag.id]?.color?.[0]"
                                class="mb-1 mr-3"
                            />
                            <input
                                :id="`tag_color_${tag.id}`"
                                v-model="tag.color"
                                type="color"
                                class="input input-bordered w-10 h-10 p-1"
                                :disabled="processingIds[tag.id]"
                            />
                            <InputError :message="errors[tag.id]?.color?.[0]" class="mt-2" />
                        </div>
                        <div class="flex items-center gap-2 md:justify-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline"
                                @click="updateTag(tag)"
                                :class="{ 'btn-disabled': processingIds[tag.id] }"
                                :disabled="processingIds[tag.id]"
                            >
                                Сохранить
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline btn-error"
                                @click="confirmDeleteTag(tag)"
                                :class="{ 'btn-disabled': processingIds[tag.id] }"
                                :disabled="processingIds[tag.id]"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">Закрыть</button>
        </ModalFooter>
    </Modal>
</template>
