<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Подтвердите пароль" />

        <div class="text-center space-y-1 mb-2">
            <h2 class="text-2xl font-bold">Подтвердите пароль</h2>
            <p class="text-sm opacity-70">Для продолжения требуется повторный ввод пароля</p>
        </div>

        <div class="alert alert-info text-sm mb-4">
            Это защищённая область приложения. Пожалуйста, подтвердите Ваш пароль, прежде чем продолжить.
        </div>

        <form @submit.prevent="submit" class="space-y-6">
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
                    autofocus
                    placeholder="Пароль"
                />
                <InputError class="mt-2 text-error" :message="form.errors.password" />
            </div>

            <div class="mt-2">
                <PrimaryButton class="btn btn-primary btn-block" :class="{ 'btn-disabled opacity-50': form.processing }" :disabled="form.processing">
                    Подтвердить
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
