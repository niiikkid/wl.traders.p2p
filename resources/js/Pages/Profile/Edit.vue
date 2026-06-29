<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Head } from '@inertiajs/vue3';
import Update2faForm from "@/Pages/Profile/Partials/Update2faForm.vue";
import LoginHistoryForm from "@/Pages/Profile/Partials/LoginHistoryForm.vue";
import { ref } from 'vue';

const activeTab = ref('password');

const profileTabs = [
    {
        key: 'password',
        title: 'Пароль',
        description: 'Обновление доступа',
        icon: 'M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    },
    {
        key: 'security',
        title: '2FA',
        description: 'Двухфакторная защита',
        icon: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Z',
    },
    {
        key: 'history',
        title: 'Сессии',
        description: 'История входов',
        icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    },
];

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    auth2fa: {
        type: Object,
        default: () => ({}),
    },
    loginHistory: {
        type: Object,
        default: () => ({ data: [], meta: {} }),
    },
    loginHistoryLoggingEnabled: {
        type: Boolean,
        default: true,
    },
    canManageLoginHistoryLogging: {
        type: Boolean,
        default: false,
    },
});

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Профиль" />

    <div class="mx-auto space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-sm font-medium uppercase tracking-wide text-primary">Настройки аккаунта</div>
                <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-base-content">Профиль</h2>
                <p class="mt-2 max-w-2xl text-sm text-base-content/70">
                    Настраивайте пароль, 2FA и при необходимости проверяйте сессии входа.
                </p>
            </div>
            <slot name="button"></slot>
        </div>

        <div class="card overflow-hidden bg-base-100 card-border border-base-300 shadow">
            <div class="border-b border-base-300 bg-base-200/40 p-3 sm:p-4">
                <div
                    role="tablist"
                    aria-label="Разделы профиля"
                    class="flex gap-2 sm:grid sm:grid-cols-3"
                >
                    <button
                        v-for="tab in profileTabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        :aria-label="tab.title"
                        class="group flex flex-1 items-center justify-center gap-0 rounded-2xl border border-transparent bg-base-100/70 p-2 text-left transition-all duration-200 hover:border-primary/30 hover:bg-base-100 hover:shadow-sm sm:justify-start sm:gap-3 sm:p-3"
                        :class="activeTab === tab.key ? 'border-primary/40 bg-base-100 shadow-sm ring-1 ring-primary/20' : 'text-base-content/70'"
                        @click="activeTab = tab.key"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/70 transition-colors group-hover:bg-primary/10 group-hover:text-primary sm:h-11 sm:w-11"
                            :class="activeTab === tab.key ? 'bg-primary/10 text-primary' : ''"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                            </svg>
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block font-semibold text-base-content">{{ tab.title }}</span>
                            <span class="block truncate text-xs text-base-content/60">{{ tab.description }}</span>
                        </span>
                    </button>
                </div>
            </div>

            <div class="card-body p-4 sm:p-6 sm:pt-4 lg:px-6 lg:pb-6 lg:pt-4">
                <section
                    v-show="activeTab === 'password'"
                    role="tabpanel"
                    aria-label="Обновление пароля"
                    class="w-full max-w-sm"
                >
                    <UpdatePasswordForm class="w-full"/>
                </section>

                <section
                    v-show="activeTab === 'security'"
                    role="tabpanel"
                    aria-label="Настройка двухфакторной авторизации"
                    class="w-full max-w-5xl"
                >
                    <Update2faForm class="w-full"/>
                </section>

                <section
                    v-show="activeTab === 'history'"
                    role="tabpanel"
                    aria-label="Сессии входа"
                    class="w-full"
                >
                    <LoginHistoryForm
                        :login-history="loginHistory"
                        :status="status"
                        :login-history-logging-enabled="loginHistoryLoggingEnabled"
                        :can-manage-login-history-logging="canManageLoginHistoryLogging"
                        class="w-full"
                    />
                </section>
            </div>
        </div>
    </div>
</template>
