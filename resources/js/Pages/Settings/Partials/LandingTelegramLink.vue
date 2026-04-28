<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import InputHelper from '@/Components/InputHelper.vue';
import TextInput from '@/Components/TextInput.vue';

const landing_telegram_link = usePage().props.landingTelegramLink;

const form = useForm({
    landing_telegram_link: landing_telegram_link ?? '',
});

const submit = () => {
    form.patch(route('admin.settings.update.landing-telegram-link'), {
        preserveScroll: true,
        onError: () => form.reset(),
    });
};
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">Telegram на маркетинговой странице</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <InputLabel
                    for="landing_telegram_link"
                    value="HTTPS-ссылка"
                    :error="!!form.errors.landing_telegram_link"
                />

                <TextInput
                    id="landing_telegram_link"
                    v-model="form.landing_telegram_link"
                    class="input-sm mt-1 block w-full"
                    placeholder="https://t.me/your_bot_or_channel"
                    :error="!!form.errors.landing_telegram_link"
                    @input="form.clearErrors('landing_telegram_link')"
                />

                <InputError class="mt-1 text-xs" :message="form.errors.landing_telegram_link" />
                <InputHelper
                    v-if="!form.errors.landing_telegram_link"
                    class="!mt-1 !text-xs"
                    model-value="Кнопки на публичной главной; пусто — без перехода. Открывается в новой вкладке."
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
