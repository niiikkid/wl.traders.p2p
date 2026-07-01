<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Code2FA from '@/Pages/Auth/Components/Code2FA.vue';
import InputError from '@/Components/InputError.vue';
import UserAvatar from '@/Components/User/UserAvatar.vue';

defineProps({
    account: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    one_time_password: '',
});

const submit = () => {
    form.post(route('account-sessions.2fa.verify'), {
        onError: () => form.reset('one_time_password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Подтвердите аккаунт" />

        <form @submit.prevent="submit" class="space-y-6">
            <div class="text-center space-y-3">
                <div class="flex justify-center">
                    <UserAvatar :user="account" size="md" ring />
                </div>

                <div>
                    <h2 class="text-2xl font-bold mb-1">Введите 2FA код</h2>
                    <p class="text-sm opacity-70">
                        Аккаунт: <span class="font-semibold">{{ account.email }}</span>
                    </p>
                </div>
            </div>

            <Code2FA v-model="form.one_time_password" />

            <div class="flex justify-center">
                <InputError class="mt-2 text-error" :message="form.errors.one_time_password" />
            </div>

            <p v-if="$page.props.flash.error" class="alert alert-error text-sm mt-2 justify-center">
                {{ $page.props.flash.error }}
            </p>

            <div class="grid gap-3">
                <PrimaryButton
                    type="submit"
                    class="btn btn-primary btn-wide justify-self-center"
                    :class="{ 'btn-disabled opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Подтвердить
                </PrimaryButton>

                <Link :href="route('account-sessions.create')" class="btn btn-ghost btn-block">
                    Назад к добавлению аккаунта
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
