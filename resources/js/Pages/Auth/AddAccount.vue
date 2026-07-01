<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    accounts: {
        type: Object,
        default: () => ({
            items: [],
            has_multiple: false,
        }),
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('account-sessions.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Добавить аккаунт" />

        <div class="text-center space-y-1 mb-2">
            <h2 class="text-2xl font-bold">Добавить аккаунт</h2>
            <p class="text-sm opacity-70">
                Введите логин и пароль второго аккаунта. Если включена 2FA, мы запросим код.
            </p>
        </div>

        <div v-if="$page.props.flash.error" class="alert alert-error text-sm mb-4">
            {{ $page.props.flash.error }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="form-control">
                <InputLabel for="login" value="Логин" class="label" />

                <TextInput
                    id="login"
                    v-model="form.login"
                    type="text"
                    class="input input-bordered input-lg w-full"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Логин"
                    :error="!!form.errors.login"
                />

                <InputError class="mt-2 text-error" :message="form.errors.login" />
            </div>

            <div class="form-control">
                <InputLabel for="password" value="Пароль" class="label" />

                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="input input-bordered input-lg w-full"
                    required
                    autocomplete="current-password"
                    placeholder="Пароль"
                    :error="!!form.errors.password"
                />

                <InputError class="mt-2 text-error" :message="form.errors.password" />
            </div>

            <div class="block">
                <label class="label cursor-pointer justify-start gap-3">
                    <Checkbox name="remember" v-model:checked="form.remember" class="checkbox" />
                    <span class="label-text">Запомнить активный аккаунт</span>
                </label>
                <p class="text-xs text-base-content/60">
                    Список аккаунтов хранится только в этом браузере и живёт в рамках его session.
                </p>
            </div>

            <div class="grid gap-3">
                <PrimaryButton
                    type="submit"
                    class="btn btn-primary btn-block"
                    :class="{ 'btn-disabled opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Добавить и войти
                </PrimaryButton>

                <Link :href="route('dashboard')" class="btn btn-ghost btn-block">
                    Вернуться
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
