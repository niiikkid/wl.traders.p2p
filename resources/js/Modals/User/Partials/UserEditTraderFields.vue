<script setup>
import DateTime from "@/Components/DateTime.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Multiselect from "@/Components/Form/Multiselect.vue";
import NumberInput from "@/Components/NumberInput.vue";
import UserFormSection from "@/Modals/User/Partials/UserFormSection.vue";
import UserFormToggle from "@/Modals/User/Partials/UserFormToggle.vue";

defineProps({
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    teamLeaders: {
        type: Array,
        default: () => [],
    },
    showTraderSettings: {
        type: Boolean,
        default: false,
    },
    showPayoutSettings: {
        type: Boolean,
        default: false,
    },
    showTraderPayoutDetails: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <UserFormSection
        v-if="showTraderSettings || showPayoutSettings"
        compact
        :title="showTraderSettings ? 'Трейдер' : 'Выплаты'"
    >
        <div
            v-if="showTraderSettings"
            class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 xl:grid-cols-3"
        >
            <UserFormToggle
                v-model="form.stop_traffic"
                label="Остановить трафик"
                toggle-class="toggle-error"
                :disabled="processing"
            >
                <p
                    v-if="user?.traffic_enabled_at && !form.stop_traffic"
                    class="truncate text-xs text-base-content/60"
                >
                    Вкл.: <DateTime :data="user.traffic_enabled_at" />
                </p>
            </UserFormToggle>

            <UserFormToggle
                v-model="form.can_work_without_device"
                label="Без устройства"
                hint="Реквизиты можно создавать без привязки к устройству. Страница устройств будет недоступна."
                :disabled="processing"
            />

            <UserFormToggle
                v-model="form.can_set_order_amount_limits"
                label="Лимиты сделки"
                hint="Трейдер сможет задавать минимальную и максимальную сумму сделки на своих реквизитах."
                :disabled="processing"
            />
        </div>

        <div v-if="showPayoutSettings" class="space-y-2">
            <UserFormToggle
                v-model="form.payouts_enabled"
                label="Выплаты включены"
                :disabled="processing"
            />

            <div
                v-if="form.payouts_enabled && showTraderPayoutDetails"
                class="grid grid-cols-1 gap-2 rounded-lg border border-base-300/80 bg-base-200/20 p-2.5 sm:grid-cols-2"
            >
                <div class="sm:col-span-2">
                    <UserFormToggle
                        v-model="form.payout_hold_enabled"
                        label="Холд включён"
                        :disabled="processing"
                    />
                </div>

                <div v-if="form.payout_hold_enabled">
                    <InputLabel
                        for="payout_hold_minutes"
                        value="Hold (мин.)"
                        :error="!!errors.payout_hold_minutes?.[0]"
                    />
                    <NumberInput
                        id="payout_hold_minutes"
                        v-model="form.payout_hold_minutes"
                        class="mt-1 block w-full"
                        step="1"
                        min="1"
                        :error="!!errors.payout_hold_minutes?.[0]"
                        :disabled="processing || !form.payouts_enabled || !form.payout_hold_enabled"
                        @input="errors.payout_hold_minutes = null"
                    />
                    <InputError class="mt-1" :message="errors.payout_hold_minutes?.[0]" />
                </div>

                <div>
                    <InputLabel
                        for="payout_active_payouts_limit"
                        value="Лимит активных"
                        hint="Сколько выплат трейдер ведёт одновременно"
                        :error="!!errors.payout_active_payouts_limit?.[0]"
                    />
                    <NumberInput
                        id="payout_active_payouts_limit"
                        v-model="form.payout_active_payouts_limit"
                        class="mt-1 block w-full"
                        min="1"
                        step="1"
                        :error="!!errors.payout_active_payouts_limit?.[0]"
                        :disabled="processing || !form.payouts_enabled"
                        @input="errors.payout_active_payouts_limit = null"
                    />
                    <InputError class="mt-1" :message="errors.payout_active_payouts_limit?.[0]" />
                </div>
            </div>
        </div>

        <div
            v-if="showTraderSettings"
            class="grid grid-cols-1 gap-2 sm:grid-cols-2"
        >
            <div
                v-if="user && !user.uses_team_leader_shared_reserve"
            >
                <InputLabel
                    for="reserve_balance_limit"
                    value="Страховой депозит (USDT)"
                    hint="Сумма, до которой пополнения сначала идут в резервный баланс"
                    :error="!!errors.reserve_balance_limit?.[0]"
                />
                <NumberInput
                    id="reserve_balance_limit"
                    v-model="form.reserve_balance_limit"
                    class="mt-1 block w-full"
                    step="1"
                    min="0"
                    :error="!!errors.reserve_balance_limit?.[0]"
                    :disabled="processing"
                    @input="errors.reserve_balance_limit = null"
                />
                <InputError class="mt-1" :message="errors.reserve_balance_limit?.[0]" />
            </div>

            <div v-if="form.role_id === 2">
                <InputLabel
                    for="max_min_order_amount"
                    value="Макс. мин. сумма сделки"
                    hint="Потолок для поля «Минимум» в лимитах реквизита. 0 — без ограничения."
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
        </div>

        <div v-if="user?.uses_team_leader_shared_reserve" class="alert alert-info py-2 text-xs">
            <span>Общий страховой резерв Team Leader. Пополнения — на основной баланс.</span>
        </div>

        <div v-if="showTraderSettings && user && !user.team_leader_id">
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

        <div v-else-if="showTraderSettings && user?.team_leader_id" class="text-sm">
            <InputLabel value="Team Leader" />
            <div class="mt-1 rounded-btn bg-base-200 px-3 py-1.5">
                <span class="font-medium">{{ user.team_leader?.email }}</span>
            </div>
            <p class="mt-0.5 text-xs text-base-content/60">
                Назначен, изменить нельзя.
            </p>
        </div>
    </UserFormSection>
</template>
