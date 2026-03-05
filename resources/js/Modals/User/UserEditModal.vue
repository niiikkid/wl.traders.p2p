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
    role_id: 0,
    banned: false,
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
    team_leader_id: [],
});

const isAdmin = (roleId) => roleId === 1;
const isTrader = (roleId) => roleId === 2;
const isMerchant = (roleId) => roleId === 3;
const isTeamLeader = (roleId) => roleId === 5;
const hasPayoutsToggle = (roleId) => isTrader(roleId) || isMerchant(roleId) || isAdmin(roleId);

const close = () => {
    modalStore.closeModal('userEdit');
};

const resetState = () => {
    user.value = null;
    roles.value = [];
    errors.value = {};
    form.value = {
        login: '',
        role_id: 0,
        banned: false,
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
        team_leader_id: [],
    };
};

const loadRoles = () => {
    return Promise.all([
        axios.get(route('admin.users.roles')),
        axios.get(route('admin.users.team-leaders')),
    ]).then(([rolesResponse, leadersResponse]) => {
        roles.value = rolesResponse.data?.data || rolesResponse.data || [];
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
            form.value.role_id = data.role.id;
            form.value.banned = !!data.banned_at;
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
            form.value.team_leader_id = data.team_leader_id ? [data.team_leader_id] : [];
        });
};

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

                <div v-if="isTrader(form.role_id) || isAdmin(form.role_id)">
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

                <div v-if="isTeamLeader(form.role_id) || isAdmin(form.role_id)" class="space-y-6">
                    <div>
                        <h4 class="text-base font-semibold">Настройки сделок</h4>

                        <InputLabel
                            for="referral_commission_percentage"
                            value="Комиссия тимлидера (%)"
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
                            Процент комиссии, который будет получать Team Leader со сделок привлеченных трейдеров (по умолчанию 0.20%)
                        </div>
                        <InputError class="mt-1" :message="errors.referral_commission_percentage?.[0]" />

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


