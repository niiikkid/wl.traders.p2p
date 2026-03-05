<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia';
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Multiselect from "@/Components/Form/Multiselect.vue";
import { ref, watch } from "vue";
import { router } from '@inertiajs/vue3';

const modalStore = useModalStore();
const { supportEditModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});

const support = ref(null);
const merchants = ref([]);
const supportMerchantIds = ref([]);

const form = ref({
    email: '',
    banned: false,
    merchant_ids: [],
});

const resetForm = () => {
    form.value = {
        email: '',
        banned: false,
        merchant_ids: [],
    };
    errors.value = {};
    support.value = null;
    merchants.value = [];
    supportMerchantIds.value = [];
};

const close = () => {
    modalStore.closeModal('supportEdit');
};

const loadData = () => {
    if (!supportEditModal.value.params?.supportId) return;
    loading.value = true;
    axios.get(route('merchant.support.edit-data', supportEditModal.value.params.supportId))
        .then(response => {
            const data = response.data?.data || response.data || {};
            support.value = data.support;
            merchants.value = data.merchants || [];
            supportMerchantIds.value = data.supportMerchantIds || [];
            form.value.email = support.value.email || '';
            form.value.banned = !!support.value.banned_at;
            form.value.merchant_ids = [...supportMerchantIds.value];
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const submit = () => {
    if (!support.value) return;
    processing.value = true;
    errors.value = {};
    axios.patch(route('merchant.support.update', support.value.id), form.value, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200 || response.status === 204) {
                close();
                resetForm();
                router.reload({ only: ['supports'] });
            }
        })
        .catch(error => {
            processing.value = false;
            if (error.response && error.response.data && error.response.data.errors) {
                errors.value = error.response.data.errors;
            }
        });
};

watch(
    () => supportEditModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
            loadData();
        } else {
            resetForm();
        }
    }
);
</script>

<template>
    <Modal :show="supportEditModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Редактирование разработчика" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="mt-2 space-y-6">
                <div>
                    <InputLabel
                        for="email"
                        value="Логин"
                        :error="!!errors.email?.[0]"
                    />
                    <TextInput
                        id="email"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        :error="!!errors.email?.[0]"
                        @input="errors.email = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.email?.[0]" />
                </div>

                <div>
                    <InputLabel
                        for="merchant_ids"
                        value="Доступные мерчанты"
                        :error="!!errors.merchant_ids?.[0]"
                    />
                    <Multiselect
                        id="merchant_ids"
                        v-model="form.merchant_ids"
                        :options="merchants"
                        label-key="label"
                        value-key="value"
                        :enable-search="true"
                        placeholder="Выберите доступные мерчанты"
                        @input="errors.merchant_ids = null"
                    />
                    <InputError class="mt-1" :message="errors.merchant_ids?.[0]" />
                </div>

                <div class="block">
                    <label class="flex items-center">
                        <input
                            type="checkbox"
                            class="checkbox checkbox-primary"
                            v-model="form.banned"
                            :disabled="processing"
                        >
                        <span class="ml-2 text-sm text-base-content">Заблокировать</span>
                    </label>
                </div>
            </form>
        </ModalBody>

        <ModalFooter>
            <button @click="close" type="button" class="btn btn-sm">
                Отмена
            </button>
            <button @click="submit" type="button" class="btn btn-sm btn-primary" :class="{ 'btn-disabled': processing }" :disabled="processing">
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>


