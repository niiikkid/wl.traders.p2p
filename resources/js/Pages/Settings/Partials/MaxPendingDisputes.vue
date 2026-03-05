<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";

const maxPendingDisputes = usePage().props.maxPendingDisputes;

const form = useForm({
    max_pending_disputes: maxPendingDisputes,
});

const submit = () => {
    form.patch(route('admin.settings.update.max-pending-disputes'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};

//
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка максимума активных споров</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="max_pending_disputes"
                        value="Максимум активных споров"
                        :error="!!form.errors.max_pending_disputes"
                    />

                    <NumberInput
                        id="max_pending_disputes"
                        v-model="form.max_pending_disputes"
                        class="mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.max_pending_disputes"
                        @input="form.clearErrors('max_pending_disputes')"
                    />

                    <InputError class="mt-2" :message="form.errors.max_pending_disputes" />
                    <InputHelper v-if="! form.errors.max_pending_disputes" model-value="Если у пользователя количество споров достигло лимита, то сделки выдаваться не будут. 0 = бесконечно"></InputHelper>
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
