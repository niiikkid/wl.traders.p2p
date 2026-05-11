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
import NumberInput from "@/Components/NumberInput.vue";
import Select from "@/Components/Select.vue";
import {computed, ref, watch} from "vue";
import { router } from '@inertiajs/vue3';
import Multiselect from "@/Components/Form/Multiselect.vue";

const modalStore = useModalStore();
const { userCreateModal } = storeToRefs(modalStore);

const roles = ref([]);
const teamLeaders = ref([]);
const agents = ref([]);
const loading = ref(false);
const processing = ref(false);
const errors = ref({});

const form = ref({
    login: '',
    telegram_username: '',
    password: '',
    password_confirmation: '',
    role_id: 0,
    team_leader_id: [],
    agent_id: [],
    agent_commission_percentage: 0.2,
});

const selectedRoleName = computed(() => {
    return roles.value.find((role) => Number(role.id) === Number(form.value.role_id))?.name ?? null;
});

const resetForm = () => {
    form.value = {
        login: '',
        telegram_username: '',
        password: '',
        password_confirmation: '',
        role_id: 0,
        team_leader_id: [],
        agent_id: [],
        agent_commission_percentage: 0.2,
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
        axios.get(route('admin.users.agents')),
    ])
    .then(([rolesResponse, leadersResponse, agentsResponse]) => {
        roles.value = rolesResponse.data?.data || rolesResponse.data || [];
        teamLeaders.value = (leadersResponse.data?.data || leadersResponse.data || []).map(item => ({
            value: item.id,
            label: item.email,
        }));
        agents.value = (agentsResponse.data?.data || agentsResponse.data || []).map(item => ({
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
        agent_id: Array.isArray(form.value.agent_id) ? form.value.agent_id[0] ?? null : form.value.agent_id,
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
            agents.value = [];
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
                        for="telegram_username"
                        value="Telegram (необязательно)"
                        :error="!!errors.telegram_username?.[0]"
                    />
                    <TextInput
                        id="telegram_username"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.telegram_username"
                        placeholder="@username или username"
                        :error="!!errors.telegram_username?.[0]"
                        @input="errors.telegram_username = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.telegram_username?.[0]" />
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

                <div v-if="selectedRoleName === 'Trader'">
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

                <div v-if="selectedRoleName === 'Merchant'">
                    <InputLabel
                        for="agent_id"
                        value="Агент"
                        :error="!!errors.agent_id?.[0]"
                    />
                    <Multiselect
                        v-model="form.agent_id"
                        :options="agents"
                        :enable-search="true"
                        :single-select="true"
                        label-key="label"
                        value-key="value"
                        placeholder="Выберите агента"
                        :disabled="processing"
                        @change="errors.agent_id = null"
                    />
                    <InputError class="mt-1" :message="errors.agent_id?.[0]" />
                    <p class="mt-1 text-xs text-base-content/70">
                        Необязательно. Агент будет получать комиссию с новых успешных сделок мерчанта.
                    </p>
                </div>

                <div v-if="selectedRoleName === 'Agent'">
                    <InputLabel
                        for="agent_commission_percentage"
                        value="Комиссия агента (%)"
                        :error="!!errors.agent_commission_percentage?.[0]"
                    />
                    <NumberInput
                        id="agent_commission_percentage"
                        v-model="form.agent_commission_percentage"
                        class="mt-1 block w-full"
                        min="0"
                        max="100"
                        step="0.01"
                        :error="!!errors.agent_commission_percentage?.[0]"
                        :disabled="processing"
                        @input="errors.agent_commission_percentage = null"
                    />
                    <InputError class="mt-1" :message="errors.agent_commission_percentage?.[0]" />
                    <p class="mt-1 text-xs text-base-content/70">
                        Комиссия будет применяться к новым сделкам мерчантов, привязанных к этому агенту.
                    </p>
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


