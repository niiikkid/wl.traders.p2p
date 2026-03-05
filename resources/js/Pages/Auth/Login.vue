<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ThemeToggle from "@/Components/ThemeToggle.vue";

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
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Вход" />

        <div class="text-center space-y-1 mb-2">
            <h2 class="text-2xl font-bold">Вход в аккаунт</h2>
            <p class="text-sm opacity-70">Введите логин и пароль, чтобы продолжить</p>
        </div>

        <div v-if="status" class="alert alert-success text-sm mb-4">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="form-control">
                <InputLabel for="login" value="Логин" class="label">
                    <span class="label-text">Логин</span>
                </InputLabel>

                <TextInput
                    id="login"
                    type="text"
                    class="input input-bordered input-lg w-full"
                    v-model="form.login"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Логин"
                />

                <InputError class="mt-2 text-error" :message="form.errors.login" />
            </div>

            <div class="form-control">
                <InputLabel for="password" value="Пароль" class="label">
                    <span class="label-text">Пароль</span>
                </InputLabel>

                <TextInput
                    id="password"
                    type="password"
                    class="input input-bordered input-lg w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="Пароль"
                />

                <InputError class="mt-2 text-error" :message="form.errors.password" />
            </div>

            <div class="block">
                <label class="label cursor-pointer justify-start gap-3">
                    <Checkbox name="remember" v-model:checked="form.remember" class="checkbox" />
                    <span class="label-text">Запомнить меня</span>
                </label>
            </div>

            <div class="mt-2">
                <PrimaryButton class="btn btn-primary btn-block" :class="{ 'btn-disabled opacity-50': form.processing }" :disabled="form.processing">
                    Войти
                </PrimaryButton>
            </div>
        </form>
        <div class="flex justify-center mt-5">
            <ThemeToggle />
        </div>
    </GuestLayout>
</template>
