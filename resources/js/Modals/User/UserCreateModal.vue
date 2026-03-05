<script setup>
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import { storeToRefs } from 'pinia'
import { useModalStore } from "@/store/modal.js";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Select from "@/Components/Select.vue";
import {ref, watch} from "vue";
import { router } from '@inertiajs/vue3';
import Multiselect from "@/Components/Form/Multiselect.vue";

const modalStore = useModalStore();
const { userCreateModal } = storeToRefs(modalStore);

const roles = ref([]);
const teamLeaders = ref([]);
const loading = ref(false);
const processing = ref(false);
const errors = ref({});

const form = ref({
    login: '',
    password: '',
    password_confirmation: '',
    role_id: 0,
    team_leader_id: [],
});

const resetForm = () => {
    form.value = {
        login: '',
        password: '',
        password_confirmation: '',
        role_id: 0,
        team_leader_id: [],
    };
    errors.value = {};
};

const close = () => {
    modalStore.closeModal('userCreate');
};

const loadRoles = () => {
    loading.value = true;
    Promise.all([
        axios.get(route('admin.users.roles')),
        axios.get(route('admin.users.team-leaders')),
    ])
    .then(([rolesResponse, leadersResponse]) => {
        roles.value = rolesResponse.data?.data || rolesResponse.data || [];
        teamLeaders.value = (leadersResponse.data?.data || leadersResponse.data || []).map(item => ({
            value: item.id,
            label: item.email,
        }));
    })
    .finally(() => {
        loading.value = false;
    });
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    const payload = {
        ...form.value,
        team_leader_id: Array.isArray(form.value.team_leader_id) ? form.value.team_leader_id[0] ?? null : form.value.team_leader_id,
    };

    axios.post(route('admin.users.store'), payload, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200 || response.status === 201) {
                close();
                resetForm();
                // Обновим список пользователей (через Inertia partial reload)
                router.reload({ only: ['users'] });
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
    () => userCreateModal.value.showed,
    (state) => {
        if (state) {
            resetForm();
            loadRoles();
        } else {
            resetForm();
            roles.value = [];
        }
    }
);
</script>

<template>
    <Modal :show="userCreateModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" title="Создание пользователя" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel
                        for="login"
                        value="Логин"
                        :error="!!errors.login?.[0]"
                    />
                    <TextInput
                        id="login"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.login"
                        required
                        autocomplete="username"
                        :error="!!errors.login?.[0]"
                        @input="errors.login = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.login?.[0]" />
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

                <div>
                    <InputLabel
                        for="roles"
                        value="Роль"
                        :error="!!errors.role_id?.[0]"
                        class="mb-1"
                    />
                    <Select
                        v-model="form.role_id"
                        :error="!!errors.role_id?.[0]"
                        :items="roles"
                        value="id"
                        name="name"
                        default_title="Выберите роль"
                        @change="errors.role_id = null"
                        :disabled="processing"
                    ></Select>
                    <InputError class="mt-1" :message="errors.role_id?.[0]" />
                </div>

                <div v-if="form.role_id === 2">
                    <InputLabel
                        for="team_leader_id"
                        value="Team Leader"
                        :error="!!errors.team_leader_id?.[0]"
                    />
                    <Multiselect
                        v-model="form.team_leader_id"
                        :options="teamLeaders"
                        :enable-search="true"
                        :single-select="true"
                        label-key="label"
                        value-key="value"
                        placeholder="Выберите Team Leader"
                        :disabled="processing"
                        @change="errors.team_leader_id = null"
                    />
                    <InputError class="mt-1" :message="errors.team_leader_id?.[0]" />
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


