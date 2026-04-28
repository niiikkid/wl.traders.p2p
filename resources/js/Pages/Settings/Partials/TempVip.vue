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
        <header class="space-y-0.5">
            <h3 class="text-sm font-semibold leading-snug text-base-content">Временный VIP</h3>
            <p class="text-xs leading-snug text-base-content/65">Норма сделок и длительность активации.</p>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <label class="label min-h-0 cursor-pointer justify-start gap-2 py-1">
                    <span class="label-text text-sm">Включить временный VIP (квиз)</span>
                    <input
                        type="checkbox"
                        class="toggle toggle-sm toggle-primary shrink-0"
                        v-model="form.enabled"
                        :disabled="form.processing"
                    />
                </label>
                <p class="text-xs leading-snug text-base-content/55">
                    Если выключить — баннер и кнопка активации исчезнут, прогресс перестанет считаться и временный VIP не будет активироваться.
                </p>
            </div>

            <div class="grid w-full min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="min-w-0">
                    <InputLabel
                        for="required_deals"
                        value="Успешных сделок"
                        :error="!!form.errors.required_deals"
                    />
                    <NumberInput
                        id="required_deals"
                        v-model="form.required_deals"
                        class="input-sm mt-1 block w-full"
                        :error="!!form.errors.required_deals"
                        min="1"
                        :disabled="form.processing || !form.enabled"
                        @input="form.clearErrors('required_deals')"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.required_deals" />
                </div>
                <div class="min-w-0">
                    <InputLabel
                        for="duration_minutes"
                        value="Длительность VIP (мин.)"
                        :error="!!form.errors.duration_minutes"
                    />
                    <NumberInput
                        id="duration_minutes"
                        v-model="form.duration_minutes"
                        class="input-sm mt-1 block w-full"
                        :error="!!form.errors.duration_minutes"
                        min="1"
                        :disabled="form.processing || !form.enabled"
                        @input="form.clearErrors('duration_minutes')"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.duration_minutes" />
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

