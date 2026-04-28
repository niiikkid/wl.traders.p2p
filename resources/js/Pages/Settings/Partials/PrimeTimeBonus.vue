<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import NumberInput from "@/Components/NumberInput.vue";
import InputHelper from "@/Components/InputHelper.vue";
import TimepickerInput from "@/Components/Form/TimepickerInput.vue";

const primeTimeBonus = usePage().props.primeTimeBonus;

const form = useForm({
    starts: primeTimeBonus.starts,
    ends: primeTimeBonus.ends,
    rate: primeTimeBonus.rate,
});

const submit = () => {
    form.patch(route('admin.settings.update.prime-time-bonus'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">Бонус за работу в прайм-тайм</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="grid w-full min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="start-time" class="mb-1 block text-xs font-medium text-base-content/80">Время начала</label>
                    <TimepickerInput v-model="form.starts" placeholder="--:--" compact />
                </div>
                <div class="min-w-0">
                    <label for="end-time" class="mb-1 block text-xs font-medium text-base-content/80">Время окончания</label>
                    <TimepickerInput v-model="form.ends" placeholder="--:--" compact />
                </div>
            </div>
            <InputError class="mt-0 text-xs" :message="form.errors.starts" />
            <InputError class="mt-0 text-xs" :message="form.errors.ends" />

            <div class="w-full min-w-0">
                <InputLabel
                    for="rate"
                    value="Рейт %"
                    :error="!!form.errors.rate"
                />

                <NumberInput
                    id="rate"
                    v-model="form.rate"
                    class="input-sm mt-1 block w-full"
                    step="0.01"
                    placeholder="0.0"
                    :error="!!form.errors.rate"
                    @input="form.clearErrors('rate')"
                />

                <InputError class="mt-1 text-xs" :message="form.errors.rate" />
                <InputHelper
                    v-if="! form.errors.rate"
                    class="!mt-1 !text-xs"
                    model-value="Складывается с % комиссии трейдера, которая в настройках платежного метода"
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
