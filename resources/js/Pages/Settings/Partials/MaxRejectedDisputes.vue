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
            <h3 class="text-sm font-semibold leading-snug text-base-content">Максимум отклонённых споров</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="grid w-full min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel
                        for="count"
                        value="За период (шт.)"
                        :error="!!form.errors.count"
                    />

                    <NumberInput
                        id="count"
                        v-model="form.count"
                        class="input-sm mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.count"
                        @input="form.clearErrors('count')"
                    />

                    <InputError class="mt-1 text-xs" :message="form.errors.count" />
                    <InputHelper
                        v-if="! form.errors.count"
                        class="!mt-1 !text-xs"
                        model-value="Отклонений до остановки трафика. 0 — без ограничения."
                    />
                </div>

                <div class="min-w-0">
                    <InputLabel
                        for="period"
                        value="Период (мин.)"
                        :error="!!form.errors.period"
                    />

                    <NumberInput
                        id="period"
                        v-model="form.period"
                        class="input-sm mt-1 block w-full"
                        step="1"
                        :error="!!form.errors.period"
                        @input="form.clearErrors('period')"
                    />

                    <InputError class="mt-1 text-xs" :message="form.errors.period" />
                    <InputHelper
                        v-if="! form.errors.period"
                        class="!mt-1 !text-xs"
                        model-value="Окно подсчёта отклонённых споров. 0 — без ограничения."
                    />
                </div>
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
