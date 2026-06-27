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
import { ref, watch, computed } from "vue";
import { router } from '@inertiajs/vue3';
import TextArea from "@/Components/TextArea.vue";
import UserFormSection from "@/Modals/User/Partials/UserFormSection.vue";
import UserFormToggle from "@/Modals/User/Partials/UserFormToggle.vue";
import UserEditTraderFields from "@/Modals/User/Partials/UserEditTraderFields.vue";
import UserEditTeamLeaderFields from "@/Modals/User/Partials/UserEditTeamLeaderFields.vue";
import UserEditSupportFields from "@/Modals/User/Partials/UserEditSupportFields.vue";

const modalStore = useModalStore();
const { userEditModal } = storeToRefs(modalStore);

const roles = ref([]);
const teamLeaders = ref([]);
const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const user = ref(null);

const form = ref({
    login: '',
    telegram_username: '',
    role_id: 0,
    banned: false,
    ban_reason: '',
    stop_traffic: false,
    can_work_without_device: false,
    can_set_order_amount_limits: false,
    payouts_enabled: true,
    payout_hold_enabled: true,
    payout_hold_minutes: 60,
    payout_active_payouts_limit: 1,
    referral_commission_percentage: 0,
    team_leader_split_from_service_percent: 0,
    payout_referral_commission_percentage: 0,
    payout_team_leader_split_from_service_percent: 0,
    reserve_balance_limit: null,
    max_min_order_amount: null,
    team_leader_extended_access_enabled: false,
    team_leader_flexible_trader_commission_enabled: false,
    team_leader_flexible_trader_commission_min: null,
    team_leader_flexible_trader_commission_max: null,
    support_can_view_deposits: false,
    support_can_edit_order_amount: false,
    support_can_use_manual_control_acq: false,
    team_leader_id: [],
    team_leader_insurance_mode: 'trader_reserve',
    team_leader_trader_limit: null,
    team_leader_reserve_balance_limit: null,
    team_leader_reserve_stop_threshold: null,
});

const isAdmin = (roleId) => roleId === 1;
const isTrader = (roleId) => roleId === 2;
const isMerchant = (roleId) => roleId === 3;
const isSupport = (roleId) => roleId === 4;
const isTeamLeader = (roleId) => roleId === 5;
const hasPayoutsToggle = (roleId) => isTrader(roleId) || isMerchant(roleId) || isAdmin(roleId);
const canManageSupportFeatures = (roleId) => isSupport(roleId) || isAdmin(roleId);

const modalTitle = computed(() => {
    if (!user.value) {
        return 'Редактирование пользователя';
    }

    return `Редактирование — ${user.value.login || user.value.email}`;
});

const canChangeTeamLeaderInsuranceMode = computed(() => {
    if (!user.value || !isTeamLeader(form.value.role_id)) {
        return true;
    }

    return (user.value.connected_trader_count ?? 0) === 0;
});

const close = () => {
    modalStore.closeModal('userEdit');
};

const resetState = () => {
    user.value = null;
    roles.value = [];
    errors.value = {};
    form.value = {
        login: '',
        telegram_username: '',
        role_id: 0,
        banned: false,
        ban_reason: '',
        stop_traffic: false,
        can_work_without_device: false,
        can_set_order_amount_limits: false,
        payouts_enabled: true,
        payout_hold_enabled: true,
        payout_hold_minutes: 60,
        payout_active_payouts_limit: 1,
        referral_commission_percentage: 0,
        team_leader_split_from_service_percent: 0,
        payout_referral_commission_percentage: 0,
        payout_team_leader_split_from_service_percent: 0,
        reserve_balance_limit: null,
        max_min_order_amount: null,
        team_leader_extended_access_enabled: false,
        team_leader_flexible_trader_commission_enabled: false,
        team_leader_flexible_trader_commission_min: null,
        team_leader_flexible_trader_commission_max: null,
        support_can_view_deposits: false,
        support_can_edit_order_amount: false,
        support_can_use_manual_control_acq: false,
        team_leader_id: [],
        team_leader_insurance_mode: 'trader_reserve',
        team_leader_trader_limit: null,
        team_leader_reserve_balance_limit: null,
        team_leader_reserve_stop_threshold: null,
    };
};

const loadRoles = () => {
    return Promise.all([
        axios.get(route('admin.users.roles')),
        axios.get(route('admin.users.team-leaders')),
    ]).then(([rolesResponse, leadersResponse]) => {
        roles.value = (rolesResponse.data?.data || rolesResponse.data || [])
            .filter((role) => !['Analyst', 'Agent'].includes(role.name));
        teamLeaders.value = (leadersResponse.data?.data || leadersResponse.data || []).map(item => ({
            value: item.id,
            label: item.email,
        }));
    });
};

const loadUser = () => {
    const id = userEditModal.value.params.user?.id || userEditModal.value.params.user_id;
    return axios.get(route('admin.users.show', id))
        .then(response => {
            const data = response.data?.data || response.data;
            user.value = data;
            form.value.login = data.email;
            form.value.telegram_username = data.telegram_username || '';
            form.value.role_id = data.role.id;
            form.value.banned = !!data.banned_at;
            form.value.ban_reason = data.ban_reason || '';
            form.value.stop_traffic = !!data.stop_traffic;
            form.value.can_work_without_device = !!data.can_work_without_device;
            form.value.can_set_order_amount_limits = !!data.can_set_order_amount_limits;
            form.value.payouts_enabled = data.payouts_enabled ?? true;
            form.value.payout_hold_enabled = data.payout_hold_enabled ?? true;
            form.value.payout_hold_minutes = data.payout_hold_minutes ?? 60;
            form.value.payout_active_payouts_limit = data.payout_active_payouts_limit ?? 1;
            form.value.referral_commission_percentage = data.referral_commission_percentage || 0;
            form.value.team_leader_split_from_service_percent = data.team_leader_split_from_service_percent ?? 0;
            form.value.payout_referral_commission_percentage = data.payout_referral_commission_percentage
                ?? data.referral_commission_percentage
                ?? 0;
            form.value.payout_team_leader_split_from_service_percent = data.payout_team_leader_split_from_service_percent
                ?? data.team_leader_split_from_service_percent
                ?? 0;
            form.value.reserve_balance_limit = data.reserve_balance_limit;
            form.value.max_min_order_amount = data.max_min_order_amount;
            form.value.team_leader_extended_access_enabled = !!data.team_leader_extended_access_enabled;
            form.value.team_leader_flexible_trader_commission_enabled = !!data.team_leader_flexible_trader_commission_enabled;
            form.value.team_leader_flexible_trader_commission_min = data.team_leader_flexible_trader_commission_min;
            form.value.team_leader_flexible_trader_commission_max = data.team_leader_flexible_trader_commission_max;
            form.value.support_can_view_deposits = !!data.support_can_view_deposits;
            form.value.support_can_edit_order_amount = !!data.support_can_edit_order_amount;
            form.value.support_can_use_manual_control_acq = !!data.support_can_use_manual_control_acq;
            form.value.team_leader_id = data.team_leader_id ? [data.team_leader_id] : [];
            form.value.team_leader_insurance_mode = data.team_leader_insurance_mode ?? 'trader_reserve';
            form.value.team_leader_trader_limit = data.team_leader_trader_limit;
            form.value.team_leader_reserve_balance_limit = data.team_leader_reserve_balance_limit;
            form.value.team_leader_reserve_stop_threshold = data.team_leader_reserve_stop_threshold;
        });
};

const loadData = () => {
    loading.value = true;
    Promise.all([loadRoles(), loadUser()])
        .finally(() => {
            loading.value = false;
        });
};

const submit = () => {
    if (!user.value) return;
    processing.value = true;
    errors.value = {};

    const payload = {
        ...form.value,
        priority_payout_access_enabled: !!user.value?.priority_payout_access_enabled,
        team_leader_id: Array.isArray(form.value.team_leader_id) ? form.value.team_leader_id[0] ?? null : form.value.team_leader_id,
    };

    axios.patch(route('admin.users.update', user.value.id), payload, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => {
            processing.value = false;
            if (response.data?.success || response.status === 200) {
                close();
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

const reset2fa = () => {
    if (!user.value) return;
    processing.value = true;
    axios.delete(route('admin.users.reset-2fa', user.value.id), {
        headers: { 'Accept': 'application/json' }
    })
        .then(() => {
            processing.value = false;
        })
        .catch(() => {
            processing.value = false;
        });
};

watch(
    () => userEditModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            loadData();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="userEditModal.showed" maxWidth="4xl" @close="close">
        <ModalHeader :title="modalTitle" @close="close" />

        <ModalBody>
            <div v-if="loading" class="flex justify-center py-8">
                <span class="loading loading-spinner loading-md" />
            </div>

            <form v-else class="space-y-3" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:items-start">
                    <UserFormSection compact title="Учётная запись">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
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

                            <div v-if="user && user.id !== 1" class="sm:col-span-2">
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
                        </div>
                    </UserFormSection>

                    <UserFormSection compact title="Статус">
                        <UserFormToggle
                            v-model="form.banned"
                            label="Заблокирован"
                            toggle-class="toggle-error"
                            :disabled="processing"
                        />

                        <div v-if="form.banned">
                            <InputLabel
                                for="ban_reason"
                                value="Причина блокировки"
                                :error="!!errors.ban_reason?.[0]"
                            />
                            <TextArea
                                id="ban_reason"
                                v-model="form.ban_reason"
                                class="mt-1 block w-full"
                                :rows="2"
                                placeholder="Необязательно"
                                :error="!!errors.ban_reason?.[0]"
                                :disabled="processing"
                                @input="errors.ban_reason = null"
                            />
                            <InputError class="mt-1" :message="errors.ban_reason?.[0]" />
                        </div>
                    </UserFormSection>
                </div>

                <div
                    class="grid grid-cols-1 gap-3"
                    :class="{
                        'xl:grid-cols-2 xl:items-start': (isTrader(form.role_id) || isAdmin(form.role_id)) && (isTeamLeader(form.role_id) || isAdmin(form.role_id) || canManageSupportFeatures(form.role_id)),
                    }"
                >
                    <UserEditTraderFields
                        :form="form"
                        :errors="errors"
                        :processing="processing"
                        :user="user"
                        :team-leaders="teamLeaders"
                        :show-trader-settings="isTrader(form.role_id) || isAdmin(form.role_id)"
                        :show-payout-settings="hasPayoutsToggle(form.role_id)"
                        :show-trader-payout-details="isTrader(form.role_id) || isAdmin(form.role_id)"
                    />

                    <div class="space-y-3">
                        <UserEditTeamLeaderFields
                            v-if="isTeamLeader(form.role_id) || isAdmin(form.role_id)"
                            :form="form"
                            :errors="errors"
                            :processing="processing"
                            :user="user"
                            :is-team-leader-role="isTeamLeader(form.role_id)"
                            :is-admin-role="isAdmin(form.role_id)"
                            :can-change-team-leader-insurance-mode="canChangeTeamLeaderInsuranceMode"
                        />

                        <UserEditSupportFields
                            v-if="canManageSupportFeatures(form.role_id)"
                            :form="form"
                            :errors="errors"
                            :processing="processing"
                        />
                    </div>
                </div>
            </form>

            <div
                v-if="!loading && user?.has_2fa === true"
                class="mt-3 rounded-lg border border-base-300 p-3"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium">Двухфакторная аутентификация</p>
                        <p class="text-xs text-base-content/60">
                            Сброс позволит настроить 2FA заново
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-error btn-sm"
                        :class="{ 'btn-disabled': processing }"
                        :disabled="processing"
                        @click="reset2fa"
                    >
                        Сбросить 2FA
                    </button>
                </div>
            </div>
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
