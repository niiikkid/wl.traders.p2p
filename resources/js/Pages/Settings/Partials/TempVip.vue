<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NumberInput from '@/Components/NumberInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const tempVipRequiredDeals = usePage().props.tempVipRequiredDeals;
const tempVipDurationMinutes = usePage().props.tempVipDurationMinutes;
const tempVipEnabled = usePage().props.tempVipEnabled;

const form = useForm({
    enabled: !!tempVipEnabled,
    required_deals: tempVipRequiredDeals,
    duration_minutes: tempVipDurationMinutes,
});

const submit = () => {
    form.patch(route('admin.settings.update.temp-vip'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header class="space-y-1">
            <h2 class="text-lg font-medium">Временный VIP</h2>
            <p class="text-sm text-base-content/70">Настройка нормы сделок и длительности активации.</p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-3xl">
                <label class="label cursor-pointer justify-start gap-3">
                    <span class="label-text">Включить функционал временного VIP (квиз)</span>
                    <input
                        type="checkbox"
                        class="toggle toggle-primary"
                        v-model="form.enabled"
                        :disabled="form.processing"
                    />
                </label>
                <p class="text-xs text-base-content/60">
                    Если выключить — баннер и кнопка активации исчезнут, прогресс перестанет считаться и временный VIP не будет активироваться.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-3xl">
                <div>
                    <InputLabel
                        for="required_deals"
                        value="Необходимое число успешных сделок"
                        :error="!!form.errors.required_deals"
                    />
                    <NumberInput
                        id="required_deals"
                        v-model="form.required_deals"
                        class="mt-1 block w-full"
                        :error="!!form.errors.required_deals"
                        min="1"
                        :disabled="form.processing || !form.enabled"
                        @input="form.clearErrors('required_deals')"
                    />
                    <InputError class="mt-2" :message="form.errors.required_deals" />
                </div>
                <div>
                    <InputLabel
                        for="duration_minutes"
                        value="Длительность временного VIP (минуты)"
                        :error="!!form.errors.duration_minutes"
                    />
                    <NumberInput
                        id="duration_minutes"
                        v-model="form.duration_minutes"
                        class="mt-1 block w-full"
                        :error="!!form.errors.duration_minutes"
                        min="1"
                        :disabled="form.processing || !form.enabled"
                        @input="form.clearErrors('duration_minutes')"
                    />
                    <InputError class="mt-2" :message="form.errors.duration_minutes" />
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

