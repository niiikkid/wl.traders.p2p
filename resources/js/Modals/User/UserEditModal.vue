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
import {ref, watch, computed} from "vue";
import DateTime from "@/Components/DateTime.vue";
import { router } from '@inertiajs/vue3';
import Multiselect from "@/Components/Form/Multiselect.vue";
import TeamLeaderInsuranceFields from "@/Modals/User/Partials/TeamLeaderInsuranceFields.vue";
import TextArea from "@/Components/TextArea.vue";

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
    is_vip: false,
    payouts_enabled: true,
    payout_hold_enabled: true,
    payout_hold_minutes: 60,
    payout_active_payouts_limit: 1,
    referral_commission_percentage: 0,
    team_leader_split_from_service_percent: 0,
    payout_referral_commission_percentage: 0,
    payout_team_leader_split_from_service_percent: 0,
    reserve_balance_limit: null,
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
const selectedRoleName = computed(() => {
    return roles.value.find((role) => Number(role.id) === Number(form.value.role_id))?.name ?? null;
});
const hasPayoutsToggle = (roleId) => isTrader(roleId) || isMerchant(roleId) || isAdmin(roleId);
const canManageSupportFeatures = (roleId) => isSupport(roleId) || isAdmin(roleId);

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
        is_vip: false,
        payouts_enabled: true,
        payout_hold_enabled: true,
        payout_hold_minutes: 60,
        payout_active_payouts_limit: 1,
        referral_commission_percentage: 0,
        team_leader_split_from_service_percent: 0,
        payout_referral_commission_percentage: 0,
        payout_team_leader_split_from_service_percent: 0,
        reserve_balance_limit: null,
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
            form.value.is_vip = !!data.is_vip;
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

const canChangeTeamLeaderInsuranceMode = computed(() => {
    if (!user.value || selectedRoleName.value !== 'Team Leader') {
        return true;
    }

    return (user.value.connected_trader_count ?? 0) === 0;
});

const teamLeaderSplitMode = computed({
    get() {
        const value = Number(form.value.team_leader_split_from_service_percent ?? 0);
        if (value <= 0) return 'trader';
        if (value >= 100) return 'admin';
        return 'split';
    },
    set(mode) {
        if (mode === 'trader') {
            form.value.team_leader_split_from_service_percent = 0;
            return;
        }

        if (mode === 'admin') {
            form.value.team_leader_split_from_service_percent = 100;
            return;
        }

        if (mode === 'split') {
            const current = Number(form.value.team_leader_split_from_service_percent ?? 0);
            if (current <= 0 || current >= 100) {
                form.value.team_leader_split_from_service_percent = 50;
            }
        }
    }
});

const payoutTeamLeaderSplitMode = computed({
    get() {
        const value = Number(form.value.payout_team_leader_split_from_service_percent ?? 0);
        if (value <= 0) return 'trader';
        if (value >= 100) return 'admin';
        return 'split';
    },
    set(mode) {
        if (mode === 'trader') {
            form.value.payout_team_leader_split_from_service_percent = 0;
            return;
        }

        if (mode === 'admin') {
            form.value.payout_team_leader_split_from_service_percent = 100;
            return;
        }

        if (mode === 'split') {
            const current = Number(form.value.payout_team_leader_split_from_service_percent ?? 0);
            if (current <= 0 || current >= 100) {
                form.value.payout_team_leader_split_from_service_percent = 50;
            }
        }
    }
});

const canConfigureFlexibleTeamLeaderCommission = computed(() => {
    return (isTeamLeader(form.value.role_id) || isAdmin(form.value.role_id))
        && !!form.value.team_leader_extended_access_enabled;
});

watch(
    () => [form.value.role_id, form.value.team_leader_extended_access_enabled],
    ([roleId, extendedEnabled]) => {
        const teamLeaderLikeRole = isTeamLeader(roleId) || isAdmin(roleId);
        if (!teamLeaderLikeRole || !extendedEnabled) {
            form.value.team_leader_flexible_trader_commission_enabled = false;
        }
    }
);

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
            // Ничего не делаем дополнительно, действие побочное
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
    <Modal :show="userEditModal.showed" @close="close" maxWidth="xl">
        <ModalHeader @close="close" :title="user ? `Редактирование пользователя - ${user.login || user.email}` : 'Редактирование пользователя'" />

        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <form v-else @submit.prevent="submit" class="space-y-6">
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

                <div v-if="user && user.id !== 1">
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

                <div class="form-control w-fit">
                    <label class="label cursor-pointer gap-3">
                        <input type="checkbox" class="toggle toggle-primary" v-model="form.banned" :disabled="processing">
                        <span class="label-text">Заблокирован</span>
                    </label>
                </div>

                <div v-if="form.banned">
                    <InputLabel
                        for="ban_reason"
                        value="Причина блокировки (необязательно)"
                        :error="!!errors.ban_reason?.[0]"
                    />
                    <TextArea
                        id="ban_reason"
                        v-model="form.ban_reason"
                        class="mt-1 block w-full"
                        :rows="3"
                        :error="!!errors.ban_reason?.[0]"
                        :disabled="processing"
                        @input="errors.ban_reason = null"
                    />
                    <InputError class="mt-1" :message="errors.ban_reason?.[0]" />
                </div>

                <div v-if="isTrader(form.role_id) || isAdmin(form.role_id)">
                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input type="checkbox" class="toggle toggle-error" v-model="form.stop_traffic" :disabled="processing">
                            <span class="label-text">Остановить трафик</span>
                        </label>
                    </div>
                    <div v-if="user?.traffic_enabled_at && !form.stop_traffic" class="text-xs opacity-70 flex items-center">
                        Трафик включен: <DateTime :data="user.traffic_enabled_at" />
                    </div>
                </div>

                <div v-if="isTrader(form.role_id) || isAdmin(form.role_id)">
                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input type="checkbox" class="toggle toggle-primary" v-model="form.can_work_without_device" :disabled="processing">
                            <span class="label-text">Работать без устройства</span>
                        </label>
                    </div>
                    <div class="mt-1 text-xs opacity-70">
                        При включении реквизиты можно создавать без привязки к устройству, страница устройств будет недоступна.
                    </div>
                </div>

                <div v-if="hasPayoutsToggle(form.role_id)">
                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.payouts_enabled"
                                :disabled="processing"
                            >
                            <span class="label-text">Выплаты включены</span>
                        </label>
                    </div>

                    <div
                        class="form-control w-fit mt-2"
                        v-if="form.payouts_enabled && (isTrader(form.role_id) || isAdmin(form.role_id))"
                    >
                        <label class="label cursor-pointer gap-3">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.payout_hold_enabled"
                                :disabled="processing"
                            >
                            <span class="label-text">Холд включён</span>
                        </label>
                    </div>

                    <div
                        class="mt-2 space-y-1"
                        v-if="form.payouts_enabled && form.payout_hold_enabled && (isTrader(form.role_id) || isAdmin(form.role_id))"
                    >
                        <InputLabel
                            for="payout_hold_minutes"
                            value="Длительность hold (минуты)"
                            :error="!!errors.payout_hold_minutes?.[0]"
                        />
                        <NumberInput
                            id="payout_hold_minutes"
                            v-model="form.payout_hold_minutes"
                            class="mt-1 block w-full max-w-xs"
                            step="1"
                            min="1"
                            :error="!!errors.payout_hold_minutes?.[0]"
                            @input="errors.payout_hold_minutes = null"
                            :disabled="processing || !form.payouts_enabled || !form.payout_hold_enabled"
                        />
                        <InputError class="mt-1" :message="errors.payout_hold_minutes?.[0]" />
                        <div class="mt-1 text-xs opacity-70">
                            Поле доступно только когда выплаты и hold включены.
                        </div>
                    </div>

                    <div
                        class="mt-4 space-y-1"
                        v-if="form.payouts_enabled && (isTrader(form.role_id) || isAdmin(form.role_id))"
                    >
                        <InputLabel
                            for="payout_active_payouts_limit"
                            value="Лимит активных выплат"
                            :error="!!errors.payout_active_payouts_limit?.[0]"
                        />
                        <NumberInput
                            id="payout_active_payouts_limit"
                            v-model="form.payout_active_payouts_limit"
                            class="mt-1 block w-full max-w-xs"
                            min="1"
                            step="1"
                            :error="!!errors.payout_active_payouts_limit?.[0]"
                            @input="errors.payout_active_payouts_limit = null"
                            :disabled="processing || !form.payouts_enabled"
                        />
                        <InputError class="mt-1" :message="errors.payout_active_payouts_limit?.[0]" />
                        <div class="mt-1 text-xs opacity-70">
                            Количество выплат, которые трейдер может вести одновременно. По умолчанию 1.
                        </div>
                    </div>
                </div>

                <div v-if="isTrader(form.role_id) || isAdmin(form.role_id)">
                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input type="checkbox" class="toggle toggle-primary" v-model="form.is_vip" :disabled="processing">
                            <span class="label-text">VIP статус</span>
                        </label>
                    </div>
                    <div class="mt-1 text-xs opacity-70">
                        VIP пользователи могут редактировать минимальную и максимальную сумму сделки
                    </div>
                </div>

                <div
                    v-if="(isTrader(form.role_id) || isAdmin(form.role_id)) && !user?.uses_team_leader_shared_reserve"
                >
                    <InputLabel
                        for="reserve_balance_limit"
                        value="Страховой депозит (USDT)"
                        :error="!!errors.reserve_balance_limit?.[0]"
                    />
                    <NumberInput
                        id="reserve_balance_limit"
                        v-model="form.reserve_balance_limit"
                        class="mt-1 block w-full"
                        step="1"
                        min="0"
                        :error="!!errors.reserve_balance_limit?.[0]"
                        @input="errors.reserve_balance_limit = null"
                        :disabled="processing"
                    />
                    <InputError class="mt-1" :message="errors.reserve_balance_limit?.[0]" />
                    <div class="mt-1 text-xs opacity-70">
                        Сумма, до которой пополнения сначала идут в резервный баланс (страховой депозит).
                    </div>
                </div>

                <div v-if="user?.uses_team_leader_shared_reserve" class="alert alert-info text-sm">
                    <span>
                        Трейдер работает через общий страховой резерв Team Leader.
                        Пополнения зачисляются на основной баланс, резервный баланс трейдера не используется.
                    </span>
                </div>

                <div v-if="isTeamLeader(form.role_id) || isAdmin(form.role_id)" class="space-y-6">
                    <TeamLeaderInsuranceFields
                        v-if="isTeamLeader(form.role_id)"
                        :form="form"
                        :errors="errors"
                        :processing="processing"
                        :can-change-insurance-mode="canChangeTeamLeaderInsuranceMode"
                        :connected-trader-count="user?.connected_trader_count ?? 0"
                    />

                    <div v-if="isTeamLeader(form.role_id) || isAdmin(form.role_id)">
                        <div class="form-control w-fit">
                            <label class="label cursor-pointer gap-3">
                                <input
                                    type="checkbox"
                                    class="toggle toggle-primary"
                                    v-model="form.team_leader_extended_access_enabled"
                                    :disabled="processing"
                                >
                                <span class="label-text">Расширенный функционал Team Leader</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs opacity-70">
                            Если выключено, тимлидер не увидит раздел "Трейдеры" и не получит доступ к расширенным страницам.
                        </div>
                    </div>

                    <div>
                        <h4 class="text-base font-semibold">Настройки сделок</h4>

                        <div v-if="canConfigureFlexibleTeamLeaderCommission" class="mt-3">
                            <div class="form-control w-fit">
                                <label class="label cursor-pointer gap-3">
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-primary"
                                        v-model="form.team_leader_flexible_trader_commission_enabled"
                                        :disabled="processing"
                                    >
                                    <span class="label-text">Гибкая комиссия по трейдерам</span>
                                </label>
                            </div>
                            <div class="mt-1 text-xs opacity-70">
                                При включении Team Leader настраивает комиссию отдельно для каждого трейдера в указанном диапазоне.
                            </div>
                            <InputError class="mt-1" :message="errors.team_leader_flexible_trader_commission_enabled?.[0]" />
                        </div>

                        <InputLabel
                            for="referral_commission_percentage"
                            value="Комиссия тимлидера по умолчанию (%)"
                            :error="!!errors.referral_commission_percentage?.[0]"
                            class="mt-3"
                        />
                        <NumberInput
                            id="referral_commission_percentage"
                            class="mt-1 block w-full"
                            v-model="form.referral_commission_percentage"
                            :error="!!errors.referral_commission_percentage?.[0]"
                            @input="errors.referral_commission_percentage = null"
                            step="0.01"
                            :disabled="processing"
                        />
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Базовая комиссия для всех трейдеров. Если для конкретного трейдера индивидуальная комиссия не задана, применяется это значение.
                        </div>
                        <InputError class="mt-1" :message="errors.referral_commission_percentage?.[0]" />

                        <div
                            v-if="canConfigureFlexibleTeamLeaderCommission && form.team_leader_flexible_trader_commission_enabled"
                            class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3"
                        >
                            <div>
                                <InputLabel
                                    for="team_leader_flexible_trader_commission_min"
                                    value="Минимальная комиссия (%)"
                                    :error="!!errors.team_leader_flexible_trader_commission_min?.[0]"
                                />
                                <NumberInput
                                    id="team_leader_flexible_trader_commission_min"
                                    class="mt-1 block w-full"
                                    v-model="form.team_leader_flexible_trader_commission_min"
                                    :error="!!errors.team_leader_flexible_trader_commission_min?.[0]"
                                    @input="errors.team_leader_flexible_trader_commission_min = null"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :disabled="processing"
                                />
                                <InputError class="mt-1" :message="errors.team_leader_flexible_trader_commission_min?.[0]" />
                            </div>

                            <div>
                                <InputLabel
                                    for="team_leader_flexible_trader_commission_max"
                                    value="Максимальная комиссия (%)"
                                    :error="!!errors.team_leader_flexible_trader_commission_max?.[0]"
                                />
                                <NumberInput
                                    id="team_leader_flexible_trader_commission_max"
                                    class="mt-1 block w-full"
                                    v-model="form.team_leader_flexible_trader_commission_max"
                                    :error="!!errors.team_leader_flexible_trader_commission_max?.[0]"
                                    @input="errors.team_leader_flexible_trader_commission_max = null"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :disabled="processing"
                                />
                                <InputError class="mt-1" :message="errors.team_leader_flexible_trader_commission_max?.[0]" />
                            </div>

                            <div class="md:col-span-2 text-xs opacity-70">
                                В этом диапазоне Team Leader сможет выставлять индивидуальную комиссию по каждому трейдеру.
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <InputLabel
                                value="Кто оплачивает комиссию тимлида"
                                :error="!!errors.team_leader_split_from_service_percent?.[0]"
                            />
                            <div class="flex flex-wrap gap-4">
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="trader"
                                        v-model="teamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Трейдер</span>
                                </label>
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="admin"
                                        v-model="teamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Админ</span>
                                </label>
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="split"
                                        v-model="teamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Сплит</span>
                                </label>
                            </div>

                            <div v-if="teamLeaderSplitMode === 'split'" class="space-y-2 max-w-md">
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="range range-primary"
                                    v-model.number="form.team_leader_split_from_service_percent"
                                    :disabled="processing"
                                />
                                <div class="flex justify-between text-xs opacity-70">
                                    <span>Админ: {{ form.team_leader_split_from_service_percent }}%</span>
                                    <span>Трейдер: {{ 100 - form.team_leader_split_from_service_percent }}%</span>
                                </div>
                            </div>
                            <div v-else class="text-xs opacity-70">
                                Админ: {{ form.team_leader_split_from_service_percent }}%, Трейдер: {{ 100 - form.team_leader_split_from_service_percent }}%
                            </div>
                            <InputError class="mt-1" :message="errors.team_leader_split_from_service_percent?.[0]" />
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div>
                        <h4 class="text-base font-semibold">Настройки выплат</h4>

                        <InputLabel
                            for="payout_referral_commission_percentage"
                            value="Комиссия тимлидера за выплаты (%)"
                            :error="!!errors.payout_referral_commission_percentage?.[0]"
                            class="mt-3"
                        />
                        <NumberInput
                            id="payout_referral_commission_percentage"
                            class="mt-1 block w-full"
                            v-model="form.payout_referral_commission_percentage"
                            :error="!!errors.payout_referral_commission_percentage?.[0]"
                            @input="errors.payout_referral_commission_percentage = null"
                            step="0.01"
                            :disabled="processing"
                        />
                        <InputError class="mt-1" :message="errors.payout_referral_commission_percentage?.[0]" />

                        <div class="mt-4 space-y-2">
                            <InputLabel
                                value="Кто оплачивает комиссию тимлида за выплаты"
                                :error="!!errors.payout_team_leader_split_from_service_percent?.[0]"
                            />
                            <div class="flex flex-wrap gap-4">
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="trader"
                                        v-model="payoutTeamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Трейдер</span>
                                </label>
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="admin"
                                        v-model="payoutTeamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Админ</span>
                                </label>
                                <label class="label cursor-pointer gap-2">
                                    <input
                                        type="radio"
                                        class="radio radio-primary"
                                        value="split"
                                        v-model="payoutTeamLeaderSplitMode"
                                        :disabled="processing"
                                    />
                                    <span class="label-text">Сплит</span>
                                </label>
                            </div>

                            <div v-if="payoutTeamLeaderSplitMode === 'split'" class="space-y-2 max-w-md">
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="range range-primary"
                                    v-model.number="form.payout_team_leader_split_from_service_percent"
                                    :disabled="processing"
                                />
                                <div class="flex justify-between text-xs opacity-70">
                                    <span>Админ: {{ form.payout_team_leader_split_from_service_percent }}%</span>
                                    <span>Трейдер: {{ 100 - form.payout_team_leader_split_from_service_percent }}%</span>
                                </div>
                            </div>
                            <div v-else class="text-xs opacity-70">
                                Админ: {{ form.payout_team_leader_split_from_service_percent }}%, Трейдер: {{ 100 - form.payout_team_leader_split_from_service_percent }}%
                            </div>
                            <InputError class="mt-1" :message="errors.payout_team_leader_split_from_service_percent?.[0]" />
                        </div>
                    </div>
                </div>

                <div v-if="canManageSupportFeatures(form.role_id)" class="space-y-3">
                    <h4 class="text-base font-semibold">Доступы саппорта</h4>

                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.support_can_view_deposits"
                                :disabled="processing"
                            >
                            <span class="label-text">Просмотр депозитов</span>
                        </label>
                    </div>
                    <InputError class="mt-1" :message="errors.support_can_view_deposits?.[0]" />

                    <div class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.support_can_edit_order_amount"
                                :disabled="processing"
                            >
                            <span class="label-text">Изменение суммы сделки</span>
                        </label>
                    </div>
                    <InputError class="mt-1" :message="errors.support_can_edit_order_amount?.[0]" />

                    <div v-if="canManageSupportFeatures(form.role_id)" class="form-control w-fit">
                        <label class="label cursor-pointer gap-3">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary"
                                v-model="form.support_can_use_manual_control_acq"
                                :disabled="processing"
                            >
                            <span class="label-text">Manual Control Acquiring</span>
                        </label>
                    </div>
                    <InputError class="mt-1" :message="errors.support_can_use_manual_control_acq?.[0]" />
                </div>

                <div v-if="(isTrader(form.role_id) || isAdmin(form.role_id)) && user && !user.team_leader_id">
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

                <div v-else-if="user && user.team_leader_id && (isTrader(form.role_id) || isAdmin(form.role_id))">
                    <InputLabel value="Team Leader" />
                    <div class="mt-1 p-2 rounded-btn bg-base-200">
                        <span class="font-medium">{{ user.team_leader?.email }}</span>
                    </div>
                    <div class="mt-1 text-sm opacity-70">
                        Team Leader уже назначен и не может быть изменен.
                    </div>
                </div>
            </form>

            <div v-if="user?.has_2fa === true" class="mt-10 pt-6 border-t border-base-200">
                <h3 class="text-lg font-medium mb-4">Дополнительные действия</h3>
                <div class="space-y-4">
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body p-4 flex-row items-center justify-between">
                            <div>
                                <h4 class="text-base font-medium">Двухфакторная аутентификация</h4>
                                <p class="text-sm opacity-70">Сброс 2FA позволит пользователю настроить его заново</p>
                            </div>
                            <button
                                @click="reset2fa"
                                type="button"
                                class="btn btn-error"
                                :class="{ 'btn-disabled': processing }"
                                :disabled="processing"
                            >
                                Сбросить 2FA
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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


