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
            <h2 class="text-lg font-medium">Ссылка Telegram для маркетинговой страницы</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="landing_telegram_link"
                        value="HTTPS-ссылка"
                        :error="!!form.errors.landing_telegram_link"
                    />

                    <TextInput
                        id="landing_telegram_link"
                        v-model="form.landing_telegram_link"
                        class="mt-1 block w-full"
                        placeholder="https://t.me/your_bot_or_channel"
                        :error="!!form.errors.landing_telegram_link"
                        @input="form.clearErrors('landing_telegram_link')"
                    />

                    <InputError class="mt-2" :message="form.errors.landing_telegram_link" />
                    <InputHelper
                        v-if="!form.errors.landing_telegram_link"
                        model-value="Используется для кнопок «Подключиться в Telegram» и «Написать в Telegram» на публичной главной. Оставьте поле пустым, чтобы отключить переход. Ссылка открывается в новой вкладке."
                    />
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
