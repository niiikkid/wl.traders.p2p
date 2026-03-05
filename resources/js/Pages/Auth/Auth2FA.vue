<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Code2FA from "@/Pages/Auth/Components/Code2FA.vue";
import InputError from "@/Components/InputError.vue";

const form = useForm({
    one_time_password: ''
});

const submit = () => {
    form.post(route('auth.2fa.validate'), {
        onBefore: () => form.reset('one_time_password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Введите 2FA Код" />

        <form @submit.prevent="submit" class="space-y-6">
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-1">Введите 2FA код</h2>
                <p class="text-sm opacity-70">Откройте приложение-аутентификатор и введите 6 цифр</p>
            </div>

            <Code2FA v-model="form.one_time_password"/>
            <div class="flex justify-center">
                <InputError class="mt-2 text-error" :message="form.errors.one_time_password" />
            </div>
            <p v-if="$page.props.flash.error" class="alert alert-error text-sm mt-2 justify-center">
                {{ $page.props.flash.error }}
            </p>

            <div class="flex items-center justify-center">
                <PrimaryButton class="btn btn-primary btn-wide" :class="{ 'btn-disabled opacity-50': form.processing }" :disabled="form.processing">
                    Войти
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
