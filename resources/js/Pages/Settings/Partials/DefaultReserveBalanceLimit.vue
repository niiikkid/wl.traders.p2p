<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NumberInput from '@/Components/NumberInput.vue';
import InputHelper from "@/Components/InputHelper.vue";
import {useForm, usePage} from '@inertiajs/vue3';

const defaultReserveBalanceLimit = usePage().props.defaultReserveBalanceLimit;

const form = useForm({
    default_reserve_balance_limit: defaultReserveBalanceLimit,
});

const submit = () => {
    form.patch(route('admin.settings.update.default-reserve-balance-limit'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header class="space-y-1">
            <h2 class="text-lg font-medium">Страховой депозит (по умолчанию)</h2>
            <p class="text-sm text-base-content/70">
                Значение будет сохраняться новым трейдерам при создании. Для существующих трейдеров можно поменять индивидуально в редактировании пользователя.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <InputLabel
                    for="default_reserve_balance_limit"
                    value="Сумма (USDT)"
                    :error="!!form.errors.default_reserve_balance_limit"
                />
                <NumberInput
                    id="default_reserve_balance_limit"
                    v-model="form.default_reserve_balance_limit"
                    class="mt-1 block w-full"
                    step="1"
                    min="0"
                    :error="!!form.errors.default_reserve_balance_limit"
                    :disabled="form.processing"
                    @input="form.clearErrors('default_reserve_balance_limit')"
                />
                <InputError class="mt-2" :message="form.errors.default_reserve_balance_limit" />
                <InputHelper v-if="!form.errors.default_reserve_balance_limit" model-value="Пополнения трейдера сначала идут в резерв до этой суммы."></InputHelper>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm opacity-70">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>


