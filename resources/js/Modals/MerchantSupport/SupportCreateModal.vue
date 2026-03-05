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
const { supportCreateModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const merchants = ref([]);

const form = ref({
    email: '',
    password: '',
    password_confirmation: '',
    merchant_ids: [],
});

const resetForm = () => {
    form.value = {
        email: '',
        password: '',
        password_confirmation: '',
        merchant_ids: [],
    };
    errors.value = {};
};

const close = () => {
    modalStore.closeModal('supportCreate');
};

const loadData = () => {
    loading.value = true;
    axios.get(route('merchant.support.create-data'))
        .then(response => {
            const data = response.data?.data || response.data || {};
            merchants.value = data.merchants || [];
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const submit = () => {
    processing.value = true;
    errors.value = {};
    axios.post(route('merchant.support.store'), form.value, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200 || response.status === 201) {
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
    () => supportCreateModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
            loadData();
        } else {
            resetForm();
            merchants.value = [];
        }
    }
);
</script>

<template>
    <Modal :show="supportCreateModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Добавление разработчика" />

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

                <div>
                    <InputLabel
                        for="password"
                        value="Пароль"
                        :error="!!errors.password?.[0]"
                    />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        :error="!!errors.password?.[0]"
                        @input="errors.password = null"
                        :disabled="processing"
                    />
                    <InputError :message="errors.password?.[0]" class="mt-1" />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Подтвердите пароль"
                    />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        :disabled="processing"
                    />
                    <InputError :message="errors.password_confirmation?.[0]" class="mt-1" />
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


