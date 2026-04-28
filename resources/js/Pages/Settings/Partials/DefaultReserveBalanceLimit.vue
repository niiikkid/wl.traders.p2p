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
        <header class="space-y-0.5">
            <h3 class="text-sm font-semibold leading-snug text-base-content">Страховой депозит (по умолчанию)</h3>
            <p class="text-xs leading-snug text-base-content/65">
                Для новых трейдеров при создании; у действующих — в карточке пользователя.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <InputLabel
                    for="default_reserve_balance_limit"
                    value="Сумма (USDT)"
                    :error="!!form.errors.default_reserve_balance_limit"
                />
                <NumberInput
                    id="default_reserve_balance_limit"
                    v-model="form.default_reserve_balance_limit"
                    class="input-sm mt-1 block w-full"
                    step="1"
                    min="0"
                    :error="!!form.errors.default_reserve_balance_limit"
                    :disabled="form.processing"
                    @input="form.clearErrors('default_reserve_balance_limit')"
                />
                <InputError class="mt-1 text-xs" :message="form.errors.default_reserve_balance_limit" />
                <InputHelper
                    v-if="!form.errors.default_reserve_balance_limit"
                    class="!mt-1 !text-xs"
                    model-value="Пополнения трейдера сначала идут в резерв до этой суммы."
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <PrimaryButton class="btn-sm" :disabled="form.processing">Сохранить</PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-xs opacity-70">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>


