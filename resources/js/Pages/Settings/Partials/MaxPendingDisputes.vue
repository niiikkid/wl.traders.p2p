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
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">Максимум активных споров</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <InputLabel
                    for="max_pending_disputes"
                    value="Лимит"
                    :error="!!form.errors.max_pending_disputes"
                />

                <NumberInput
                    id="max_pending_disputes"
                    v-model="form.max_pending_disputes"
                    class="input-sm mt-1 block w-full"
                    step="1"
                    :error="!!form.errors.max_pending_disputes"
                    @input="form.clearErrors('max_pending_disputes')"
                />

                <InputError class="mt-1 text-xs" :message="form.errors.max_pending_disputes" />
                <InputHelper
                    v-if="! form.errors.max_pending_disputes"
                    class="!mt-1 !text-xs"
                    model-value="При достижении лимита сделки не выдаются. 0 — без ограничения."
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
