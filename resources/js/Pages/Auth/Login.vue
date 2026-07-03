<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login', {}, false), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Вход" />

        <div class="mb-5">
            <h2 class="text-2xl font-bold tracking-tight">Вход в аккаунт</h2>
            <p class="mt-1 text-sm text-base-content/60">
                Введите логин и пароль, чтобы продолжить
            </p>
        </div>

        <div v-if="status" class="alert alert-success mb-5 text-sm">
            <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
            </svg>
            <span>{{ status }}</span>
        </div>

        <form @submit.prevent="submit">
            <fieldset class="fieldset gap-4 p-0" :disabled="form.processing">
                <div>
                    <label class="label py-1" for="login">
                        <span class="label-text font-medium">Логин</span>
                    </label>
                    <label
                        class="input w-full gap-3"
                        :class="form.errors.login ? 'input-error' : 'input-bordered'"
                    >
                        <svg class="size-5 shrink-0 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <input
                            id="login"
                            v-model="form.login"
                            type="text"
                            class="grow bg-transparent focus:outline-none"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Ваш логин"
                        />
                    </label>
                    <InputError class="mt-1.5 text-error" :message="form.errors.login" />
                </div>

                <div>
                    <label class="label py-1" for="password">
                        <span class="label-text font-medium">Пароль</span>
                    </label>
                    <label
                        class="input w-full gap-3"
                        :class="form.errors.password ? 'input-error' : 'input-bordered'"
                    >
                        <svg class="size-5 shrink-0 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="grow bg-transparent focus:outline-none"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                    </label>
                    <InputError class="mt-1.5 text-error" :message="form.errors.password" />
                </div>

                <label class="label cursor-pointer justify-start gap-3 px-0 py-0">
                    <Checkbox name="remember" v-model:checked="form.remember" class="checkbox checkbox-sm" />
                    <span class="label-text text-base-content/80">Запомнить меня</span>
                </label>

                <PrimaryButton
                    type="submit"
                    class="btn btn-primary btn-block mt-1"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                    <svg v-else class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Войти
                </PrimaryButton>
            </fieldset>
        </form>

        <p class="mt-5 text-center text-xs leading-relaxed text-base-content/40">
            Нет доступа или забыли пароль?<br />
            Обратитесь к администратору платформы.
        </p>
    </GuestLayout>
</template>
