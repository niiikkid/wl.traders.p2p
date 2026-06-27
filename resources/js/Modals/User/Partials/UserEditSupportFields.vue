<script setup>
import InputError from "@/Components/InputError.vue";
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
});
</script>

<template>
    <UserFormSection compact title="Доступы саппорта">
        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-1">
                <UserFormToggle
                    v-model="form.support_can_view_deposits"
                    label="Просмотр депозитов"
                    :disabled="processing"
                />
                <InputError :message="errors.support_can_view_deposits?.[0]" />
            </div>

            <div class="space-y-1">
                <UserFormToggle
                    v-model="form.support_can_edit_order_amount"
                    label="Изменение суммы сделки"
                    :disabled="processing"
                />
                <InputError :message="errors.support_can_edit_order_amount?.[0]" />
            </div>

            <div class="space-y-1 sm:col-span-2 lg:col-span-1">
                <UserFormToggle
                    v-model="form.support_can_use_manual_control_acq"
                    label="Manual Control Acquiring"
                    :disabled="processing"
                />
                <InputError :message="errors.support_can_use_manual_control_acq?.[0]" />
            </div>
        </div>
    </UserFormSection>
</template>
