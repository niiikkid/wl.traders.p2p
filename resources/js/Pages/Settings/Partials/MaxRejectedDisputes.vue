<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import InputHelper from "@/Components/InputHelper.vue";
import NumberInput from "@/Components/NumberInput.vue";

const maxRejectedDisputes = usePage().props.maxRejectedDisputes;

const form = useForm({
    count: maxRejectedDisputes.count,
    period: maxRejectedDisputes.period,
});

const submit = () => {
    form.patch(route('admin.settings.update.max-rejected-disputes'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка максимума отклоненных споров</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="count"
                        value="Максимум отклоненных споров"
                        :error="!!form.errors.count"
                    />

                    <NumberInput
                        id="count"
                        v-model="form.count"
                        class="mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.count"
                        @input="form.clearErrors('count')"
                    />

                    <InputError class="mt-2" :message="form.errors.count" />
                    <InputHelper v-if="! form.errors.count" model-value="Максимальное количество споров, которое может быть отклонено у трейдера за период времени, прежде чем ему будет остановлен трафик. 0 = бесконечно"></InputHelper>
                </div>

                <div class="mt-4">
                    <InputLabel
                        for="period"
                        value="Период времени (в минутах)"
                        :error="!!form.errors.period"
                    />

                    <NumberInput
                        id="period"
                        v-model="form.period"
                        class="mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.period"
                        @input="form.clearErrors('period')"
                    />

                    <InputError class="mt-2" :message="form.errors.period" />
                    <InputHelper v-if="! form.errors.period" model-value="Период времени, за который ведется подсчет отклоненных споров. 0 = бесконечно"></InputHelper>
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
