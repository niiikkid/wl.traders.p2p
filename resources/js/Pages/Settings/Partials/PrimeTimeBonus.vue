<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {router, useForm, usePage} from '@inertiajs/vue3';
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

//
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка бонуса за работу в прайм-тайм</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem] grid sm:grid-cols-2 grid-cols-1 gap-4">
                <div>
                    <label for="start-time" class="block mb-2 text-sm font-medium">Время начала:</label>
                    <TimepickerInput v-model="form.starts" placeholder="--:--" />
                </div>
                <div>
                    <label for="end-time" class="block mb-2 text-sm font-medium">Время окончания:</label>
                    <TimepickerInput v-model="form.ends" placeholder="--:--" />
                </div>
            </div>
            <InputError class="mt-2" :message="form.errors.starts" />
            <InputError class="mt-2" :message="form.errors.ends" />

            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="rate"
                        value="Рейт %"
                        :error="!!form.errors.rate"
                    />

                    <NumberInput
                        id="rate"
                        v-model="form.rate"
                        class="mt-1 block w-full"
                        step="0.01"
                        placeholder="0.0"
                        :error="!!form.errors.rate"
                        @input="form.clearErrors('rate')"
                    />

                    <InputError class="mt-2" :message="form.errors.rate" />
                    <InputHelper v-if="! form.errors.rate" model-value="Складывается с % комиссии трейдера, которая в настройках платежного метода"></InputHelper>
                </div>
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
