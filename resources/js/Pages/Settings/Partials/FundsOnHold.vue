<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";

const fundsOnHoldTime = usePage().props.fundsOnHoldTime;

const form = useForm({
    hold_time: fundsOnHoldTime,
});

const submit = () => {
    form.patch(route('admin.settings.update.funds-on-hold'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};

//
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка времени холда средств</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="hold_time"
                        value="Время холда"
                        :error="!!form.errors.hold_time"
                    />

                    <NumberInput
                        id="hold_time"
                        v-model="form.hold_time"
                        class="mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.hold_time"
                        @input="form.clearErrors('hold_time')"
                    />

                    <InputError class="mt-2" :message="form.errors.hold_time" />
                    <InputHelper v-if="! form.errors.hold_time" model-value="Время которое доход трейдера будет удерживаться после завершения выплаты."></InputHelper>
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
