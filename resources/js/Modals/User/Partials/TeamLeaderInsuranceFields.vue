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
    { id: 'trader_reserve', name: 'Вариант 1: депозит у каждого трейдера' },
    { id: 'team_leader_reserve', name: 'Вариант 2: общий депозит Team Leader' },
];

const isSharedReserveMode = computed(() => props.form.team_leader_insurance_mode === 'team_leader_reserve');
</script>

<template>
    <div class="space-y-2 rounded-lg border border-base-300/80 bg-base-200/20 p-2.5">
        <p class="text-sm font-medium">Страховой депозит</p>

        <div v-if="connectedTraderCount > 0" class="alert alert-info py-1.5 text-xs">
            <span>
                Подключено трейдеров: {{ connectedTraderCount }}. Режим нельзя изменить.
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

        <div v-if="isSharedReserveMode" class="alert alert-warning py-1.5 text-xs">
            <span>Общий резерв Team Leader. Пополнения — только на резервный баланс.</span>
        </div>

        <div v-if="isSharedReserveMode" class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <div>
                <InputLabel
                    for="team_leader_trader_limit"
                    value="Лимит трейдеров"
                    :error="!!errors.team_leader_trader_limit?.[0]"
                />
                <NumberInput
                    id="team_leader_trader_limit"
                    v-model="form.team_leader_trader_limit"
                    class="mt-1 block w-full"
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
                    value="Сумма резерва (USDT)"
                    hint="Сумма на резервном балансе для работы подключённых трейдеров"
                    :error="!!errors.team_leader_reserve_balance_limit?.[0]"
                />
                <NumberInput
                    id="team_leader_reserve_balance_limit"
                    v-model="form.team_leader_reserve_balance_limit"
                    class="mt-1 block w-full"
                    min="0"
                    step="1"
                    :error="!!errors.team_leader_reserve_balance_limit?.[0]"
                    :disabled="processing"
                    @input="errors.team_leader_reserve_balance_limit = null"
                />
                <InputError class="mt-1" :message="errors.team_leader_reserve_balance_limit?.[0]" />
            </div>

            <div>
                <InputLabel
                    for="team_leader_reserve_stop_threshold"
                    value="Порог остановки (USDT)"
                    hint="При достижении порога выдача сделок подключённым трейдерам останавливается"
                    :error="!!errors.team_leader_reserve_stop_threshold?.[0]"
                />
                <NumberInput
                    id="team_leader_reserve_stop_threshold"
                    v-model="form.team_leader_reserve_stop_threshold"
                    class="mt-1 block w-full"
                    min="0"
                    step="1"
                    :error="!!errors.team_leader_reserve_stop_threshold?.[0]"
                    :disabled="processing"
                    @input="errors.team_leader_reserve_stop_threshold = null"
                />
                <InputError class="mt-1" :message="errors.team_leader_reserve_stop_threshold?.[0]" />
            </div>
        </div>
    </div>
</template>
