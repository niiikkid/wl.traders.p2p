<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import NumberInput from "@/Components/NumberInput.vue";
import TeamLeaderInsuranceFields from "@/Modals/User/Partials/TeamLeaderInsuranceFields.vue";
import TeamLeaderSplitFields from "@/Modals/User/Partials/TeamLeaderSplitFields.vue";
import UserFormSection from "@/Modals/User/Partials/UserFormSection.vue";
import UserFormToggle from "@/Modals/User/Partials/UserFormToggle.vue";
import { computed, watch } from "vue";

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
    user: {
        type: Object,
        default: null,
    },
    isTeamLeaderRole: {
        type: Boolean,
        default: false,
    },
    isAdminRole: {
        type: Boolean,
        default: false,
    },
    canChangeTeamLeaderInsuranceMode: {
        type: Boolean,
        default: true,
    },
});

const canConfigureFlexibleTeamLeaderCommission = computed(() => {
    return (props.isTeamLeaderRole || props.isAdminRole) && !!props.form.team_leader_extended_access_enabled;
});

watch(
    () => [props.form.role_id, props.form.team_leader_extended_access_enabled],
    ([roleId, extendedEnabled]) => {
        const teamLeaderLikeRole = roleId === 5 || roleId === 1;
        if (!teamLeaderLikeRole || !extendedEnabled) {
            props.form.team_leader_flexible_trader_commission_enabled = false;
        }
    }
);
</script>

<template>
    <UserFormSection compact title="Team Leader">
        <TeamLeaderInsuranceFields
            v-if="isTeamLeaderRole"
            :form="form"
            :errors="errors"
            :processing="processing"
            :can-change-insurance-mode="canChangeTeamLeaderInsuranceMode"
            :connected-trader-count="user?.connected_trader_count ?? 0"
        />

        <UserFormToggle
            v-model="form.team_leader_extended_access_enabled"
            label="Расширенный доступ"
            hint="Если выключено, тимлидер не увидит раздел «Трейдеры» и расширенные страницы."
            :disabled="processing"
        />

        <div class="join join-vertical w-full">
            <div class="collapse collapse-arrow join-item border border-base-300 bg-base-200/20">
                <input type="checkbox">
                <div class="collapse-title min-h-0 py-2 text-sm font-medium">
                    Настройки сделок
                </div>
                <div class="collapse-content space-y-2 text-sm">
                    <UserFormToggle
                        v-if="canConfigureFlexibleTeamLeaderCommission"
                        v-model="form.team_leader_flexible_trader_commission_enabled"
                        label="Гибкая комиссия"
                        hint="Team Leader настраивает комиссию отдельно для каждого трейдера в указанном диапазоне."
                        :disabled="processing"
                    />
                    <InputError :message="errors.team_leader_flexible_trader_commission_enabled?.[0]" />

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <InputLabel
                                for="referral_commission_percentage"
                                value="Комиссия по умолчанию (%)"
                                hint="Базовая комиссия, если для трейдера не задана индивидуальная."
                                :error="!!errors.referral_commission_percentage?.[0]"
                            />
                            <NumberInput
                                id="referral_commission_percentage"
                                v-model="form.referral_commission_percentage"
                                class="mt-1 block w-full"
                                step="0.01"
                                :error="!!errors.referral_commission_percentage?.[0]"
                                :disabled="processing"
                                @input="errors.referral_commission_percentage = null"
                            />
                            <InputError class="mt-1" :message="errors.referral_commission_percentage?.[0]" />
                        </div>

                        <template v-if="canConfigureFlexibleTeamLeaderCommission && form.team_leader_flexible_trader_commission_enabled">
                            <div>
                                <InputLabel
                                    for="team_leader_flexible_trader_commission_min"
                                    value="Мин. комиссия (%)"
                                    :error="!!errors.team_leader_flexible_trader_commission_min?.[0]"
                                />
                                <NumberInput
                                    id="team_leader_flexible_trader_commission_min"
                                    v-model="form.team_leader_flexible_trader_commission_min"
                                    class="mt-1 block w-full"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :error="!!errors.team_leader_flexible_trader_commission_min?.[0]"
                                    :disabled="processing"
                                    @input="errors.team_leader_flexible_trader_commission_min = null"
                                />
                                <InputError class="mt-1" :message="errors.team_leader_flexible_trader_commission_min?.[0]" />
                            </div>

                            <div>
                                <InputLabel
                                    for="team_leader_flexible_trader_commission_max"
                                    value="Макс. комиссия (%)"
                                    :error="!!errors.team_leader_flexible_trader_commission_max?.[0]"
                                />
                                <NumberInput
                                    id="team_leader_flexible_trader_commission_max"
                                    v-model="form.team_leader_flexible_trader_commission_max"
                                    class="mt-1 block w-full"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :error="!!errors.team_leader_flexible_trader_commission_max?.[0]"
                                    :disabled="processing"
                                    @input="errors.team_leader_flexible_trader_commission_max = null"
                                />
                                <InputError class="mt-1" :message="errors.team_leader_flexible_trader_commission_max?.[0]" />
                            </div>
                        </template>
                    </div>

                    <TeamLeaderSplitFields
                        label="Кто оплачивает комиссию"
                        percent-field="team_leader_split_from_service_percent"
                        :form="form"
                        :errors="errors"
                        :processing="processing"
                    />
                </div>
            </div>

            <div class="collapse collapse-arrow join-item border border-base-300 bg-base-200/20">
                <input type="checkbox">
                <div class="collapse-title min-h-0 py-2 text-sm font-medium">
                    Настройки выплат
                </div>
                <div class="collapse-content space-y-2 text-sm">
                    <div class="max-w-xs">
                        <InputLabel
                            for="payout_referral_commission_percentage"
                            value="Комиссия за выплаты (%)"
                            :error="!!errors.payout_referral_commission_percentage?.[0]"
                        />
                        <NumberInput
                            id="payout_referral_commission_percentage"
                            v-model="form.payout_referral_commission_percentage"
                            class="mt-1 block w-full"
                            step="0.01"
                            :error="!!errors.payout_referral_commission_percentage?.[0]"
                            :disabled="processing"
                            @input="errors.payout_referral_commission_percentage = null"
                        />
                        <InputError class="mt-1" :message="errors.payout_referral_commission_percentage?.[0]" />
                    </div>

                    <TeamLeaderSplitFields
                        label="Кто оплачивает комиссию за выплаты"
                        percent-field="payout_team_leader_split_from_service_percent"
                        :form="form"
                        :errors="errors"
                        :processing="processing"
                    />
                </div>
            </div>
        </div>
    </UserFormSection>
</template>
