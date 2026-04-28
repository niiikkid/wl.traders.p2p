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
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">Время холда средств</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <InputLabel
                    for="hold_time"
                    value="Время холда"
                    :error="!!form.errors.hold_time"
                />

                <NumberInput
                    id="hold_time"
                    v-model="form.hold_time"
                    class="input-sm mt-1 block w-full"
                    step="1"
                    :error="!!form.errors.hold_time"
                    @input="form.clearErrors('hold_time')"
                />

                <InputError class="mt-1 text-xs" :message="form.errors.hold_time" />
                <InputHelper
                    v-if="! form.errors.hold_time"
                    class="!mt-1 !text-xs"
                    model-value="Удержание дохода трейдера после завершения выплаты."
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
