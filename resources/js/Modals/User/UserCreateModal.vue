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
import { computed, ref, watch } from "vue";
import { router } from '@inertiajs/vue3';
import Multiselect from "@/Components/Form/Multiselect.vue";
import TeamLeaderInsuranceFields from "@/Modals/User/Partials/TeamLeaderInsuranceFields.vue";
import UserFormSection from "@/Modals/User/Partials/UserFormSection.vue";

const modalStore = useModalStore();
const { userCreateModal } = storeToRefs(modalStore);

const roles = ref([]);
const teamLeaders = ref([]);
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
    team_leader_insurance_mode: 'trader_reserve',
    team_leader_trader_limit: null,
    team_leader_reserve_balance_limit: null,
    team_leader_reserve_stop_threshold: null,
    max_min_order_amount: null,
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
        team_leader_insurance_mode: 'trader_reserve',
        team_leader_trader_limit: null,
        team_leader_reserve_balance_limit: null,
        team_leader_reserve_stop_threshold: null,
        max_min_order_amount: null,
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
            roles.value = (rolesResponse.data?.data || rolesResponse.data || [])
                .filter((role) => !['Analyst', 'Agent'].includes(role.name));
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
    <Modal :show="userCreateModal.showed" maxWidth="3xl" @close="close">
        <ModalHeader title="Создание пользователя" @close="close" />

        <ModalBody>
            <div v-if="loading" class="flex justify-center py-8">
                <span class="loading loading-spinner loading-md" />
            </div>

            <form v-else class="space-y-3" @submit.prevent="submit">
                <UserFormSection compact title="Учётная запись">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="lg:col-span-3">
                            <InputLabel
                                for="login"
                                value="Логин"
                                :error="!!errors.login?.[0]"
                            />
                            <TextInput
                                id="login"
                                v-model="form.login"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autocomplete="username"
                                :error="!!errors.login?.[0]"
                                :disabled="processing"
                                @input="errors.login = null"
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
                                :disabled="processing"
                                @input="errors.password = null"
                            />
                            <InputError class="mt-1" :message="errors.password?.[0]" />
                        </div>

                        <div>
                            <InputLabel
                                for="password_confirmation"
                                value="Подтверждение"
                            />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                :disabled="processing"
                            />
                            <InputError class="mt-1" :message="errors.password_confirmation?.[0]" />
                        </div>

                        <div>
                            <InputLabel
                                for="telegram_username"
                                value="Telegram"
                                :error="!!errors.telegram_username?.[0]"
                            />
                            <TextInput
                                id="telegram_username"
                                v-model="form.telegram_username"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="@username"
                                :error="!!errors.telegram_username?.[0]"
                                :disabled="processing"
                                @input="errors.telegram_username = null"
                            />
                            <InputError class="mt-1" :message="errors.telegram_username?.[0]" />
                        </div>
                    </div>
                </UserFormSection>

                <UserFormSection compact title="Роль и доступ">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div :class="{ 'sm:col-span-2': selectedRoleName !== 'Trader' }">
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
                                :disabled="processing"
                                @change="errors.role_id = null"
                            />
                            <InputError class="mt-1" :message="errors.role_id?.[0]" />
                        </div>

                        <template v-if="selectedRoleName === 'Trader'">
                            <div>
                                <InputLabel
                                    for="max_min_order_amount"
                                    value="Макс. мин. сумма"
                                    hint="Потолок для «Минимум» в лимитах реквизита. 0 — без ограничения."
                                    :error="!!errors.max_min_order_amount?.[0]"
                                />
                                <NumberInput
                                    id="max_min_order_amount"
                                    v-model="form.max_min_order_amount"
                                    class="mt-1 block w-full"
                                    step="1"
                                    min="0"
                                    :error="!!errors.max_min_order_amount?.[0]"
                                    :disabled="processing"
                                    @input="errors.max_min_order_amount = null"
                                />
                                <InputError class="mt-1" :message="errors.max_min_order_amount?.[0]" />
                            </div>

                            <div>
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
                        </template>
                    </div>

                    <TeamLeaderInsuranceFields
                        v-if="selectedRoleName === 'Team Leader'"
                        :form="form"
                        :errors="errors"
                        :processing="processing"
                    />
                </UserFormSection>
            </form>
        </ModalBody>

        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing }"
                :disabled="processing"
                @click="submit"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
