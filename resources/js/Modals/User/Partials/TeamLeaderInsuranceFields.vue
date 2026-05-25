<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import Select from "@/Components/Select.vue";
import { computed } from "vue";

const props = defineProps({
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
    canChangeInsuranceMode: {
        type: Boolean,
        default: true,
    },
    connectedTraderCount: {
        type: Number,
        default: 0,
    },
});

const insuranceModeOptions = [
    { id: 'trader_reserve', name: 'Вариант 1: страховой депозит у каждого трейдера' },
    { id: 'team_leader_reserve', name: 'Вариант 2: общий страховой депозит Team Leader' },
];

const isSharedReserveMode = computed(() => props.form.team_leader_insurance_mode === 'team_leader_reserve');
</script>

<template>
    <div class="space-y-4 rounded-box border border-base-300 p-4">
        <h4 class="text-base font-semibold">Режим страхового депозита</h4>

        <div v-if="connectedTraderCount > 0" class="alert alert-info text-sm">
            <span>
                Подключено трейдеров: {{ connectedTraderCount }}.
                Режим нельзя изменить, пока есть подключённые трейдеры.
            </span>
        </div>

        <div>
            <InputLabel
                for="team_leader_insurance_mode"
                value="Режим"
                :error="!!errors.team_leader_insurance_mode?.[0]"
                class="mb-1"
            />
            <Select
                id="team_leader_insurance_mode"
                v-model="form.team_leader_insurance_mode"
                :error="!!errors.team_leader_insurance_mode?.[0]"
                :items="insuranceModeOptions"
                value="id"
                name="name"
                :disabled="processing || !canChangeInsuranceMode"
                @change="errors.team_leader_insurance_mode = null"
            />
            <InputError class="mt-1" :message="errors.team_leader_insurance_mode?.[0]" />
        </div>

        <div v-if="isSharedReserveMode" class="alert alert-warning text-sm">
            <span>
                Во втором варианте подключённые трейдеры используют общий страховой резерв Team Leader.
                Team Leader пополняет только резервный баланс. Доход Team Leader всегда зачисляется на Team Leader баланс
                и не используется для страховых списаний.
            </span>
        </div>

        <template v-if="isSharedReserveMode">
            <div>
                <InputLabel
                    for="team_leader_trader_limit"
                    value="Лимит трейдеров"
                    :error="!!errors.team_leader_trader_limit?.[0]"
                />
                <NumberInput
                    id="team_leader_trader_limit"
                    v-model="form.team_leader_trader_limit"
                    class="mt-1 block w-full max-w-xs"
                    min="1"
                    step="1"
                    :error="!!errors.team_leader_trader_limit?.[0]"
                    :disabled="processing"
                    @input="errors.team_leader_trader_limit = null"
                />
                <InputError class="mt-1" :message="errors.team_leader_trader_limit?.[0]" />
            </div>

            <div>
                <InputLabel
                    for="team_leader_reserve_balance_limit"
                    value="Требуемая сумма резерва (USDT)"
                    :error="!!errors.team_leader_reserve_balance_limit?.[0]"
                />
                <NumberInput
                    id="team_leader_reserve_balance_limit"
                    v-model="form.team_leader_reserve_balance_limit"
                    class="mt-1 block w-full max-w-xs"
                    min="0"
                    step="1"
                    :error="!!errors.team_leader_reserve_balance_limit?.[0]"
                    :disabled="processing"
                    @input="errors.team_leader_reserve_balance_limit = null"
                />
                <InputError class="mt-1" :message="errors.team_leader_reserve_balance_limit?.[0]" />
                <p class="mt-1 text-xs text-base-content/70">
                    Сумма, которую Team Leader должен внести на резервный баланс для работы подключённых трейдеров.
                </p>
            </div>

            <div>
                <InputLabel
                    for="team_leader_reserve_stop_threshold"
                    value="Порог остановки выдачи (USDT)"
                    :error="!!errors.team_leader_reserve_stop_threshold?.[0]"
                />
                <NumberInput
                    id="team_leader_reserve_stop_threshold"
                    v-model="form.team_leader_reserve_stop_threshold"
                    class="mt-1 block w-full max-w-xs"
                    min="0"
                    step="1"
                    :error="!!errors.team_leader_reserve_stop_threshold?.[0]"
                    :disabled="processing"
                    @input="errors.team_leader_reserve_stop_threshold = null"
                />
                <InputError class="mt-1" :message="errors.team_leader_reserve_stop_threshold?.[0]" />
                <p class="mt-1 text-xs text-base-content/70">
                    Если резервный баланс Team Leader станет равен этой сумме или ниже, система перестанет выдавать сделки подключённым трейдерам.
                </p>
            </div>
        </template>
    </div>
</template>
