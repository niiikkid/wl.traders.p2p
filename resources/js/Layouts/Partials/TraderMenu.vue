<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import {useUserStore} from "@/store/user.js";
import OnlineSwitcher from "@/Layouts/Partials/OnlineSwitcher.vue";

const menu = ref(usePage().props.menu);
const userStore = useUserStore();
const canWorkWithoutDevice = computed(() => !!usePage().props.auth?.user?.can_work_without_device);
const payoutsEnabled = computed(() => !!usePage().props.auth?.user?.payouts_enabled);

router.on('success', (event) => {
    menu.value = usePage().props.menu;
})
</script>

<template>
    <ul class="menu menu-md w-full space-y-0.5">
        <ViewModeSwitcher v-if="userStore.isAdmin" class="mb-2"/>
        <div>
            <div class="p-3">
                <OnlineSwitcher/>
            </div>
        </div>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.main.index') }]">
            <span
                @click="router.visit(route('trader.main.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.main.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/>
                </svg>
                Главная
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('notifications.*') }]">
            <span
                @click="router.visit(route('notifications.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('notifications.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                Уведомления
                <span v-if="menu.notificationsUnreadCount" class="badge badge-primary badge-sm justify-self-end">
                    {{ menu.notificationsUnreadCount }}
                </span>
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('payment-details.*') }]">
            <span
                @click="router.visit(route('payment-details.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('payment-details.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M6 14h2m3 0h5M3 7v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1Z"/>
                </svg>
                Реквизиты
                <span v-if="menu.activeDetails" class="badge badge-success badge-sm justify-self-end">
                    {{ menu.activeDetails }}
                </span>
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('orders.*') }]">
            <span
                @click="router.visit(route('orders.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('orders.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17.345a4.76 4.76 0 0 0 2.558 1.618c2.274.589 4.512-.446 4.999-2.31.487-1.866-1.273-3.9-3.546-4.49-2.273-.59-4.034-2.623-3.547-4.488.486-1.865 2.724-2.899 4.998-2.31.982.236 1.87.793 2.538 1.592m-3.879 12.171V21m0-18v2.2"/>
                </svg>
                Сделки
                <span v-if="menu.pendingOrdersCount" class="badge badge-info badge-sm justify-self-end">
                    {{ menu.pendingOrdersCount }}
                </span>
            </span>
        </li>
        <li
            v-if="payoutsEnabled"
            :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.payouts.*') }]"
        >
            <span
                @click="router.visit(route('trader.payouts.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.payouts.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                </svg>
                Выплаты
                <span v-if="menu.payoutsActiveCount" class="badge badge-info badge-sm justify-self-end">
                    {{ menu.payoutsActiveCount }}
                </span>
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('disputes.*') }]">
            <span
                @click="router.visit(route('disputes.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('disputes.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                Споры
                <span v-if="menu.pendingDisputesCount" class="badge badge-error badge-sm justify-self-end">
                    {{ menu.pendingDisputesCount }}
                </span>
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('wallet.*') }]">
            <span
                @click="router.visit(route('wallet.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('wallet.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                </svg>
                Финансы
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.statistics.*') }]">
            <span
                @click="router.visit(route('trader.statistics.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.statistics.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5"/>
                </svg>
                Статистика
            </span>
        </li>
        <li v-if="!canWorkWithoutDevice" :class="[{ 'bg-base-content/10 rounded-lg': route().current('sms-logs.*') }]">
            <span
                @click="router.visit(route('sms-logs.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('sms-logs.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.556 8.5h8m-8 3.5H12m7.111-7H4.89a.896.896 0 0 0-.629.256.868.868 0 0 0-.26.619v9.25c0 .232.094.455.26.619A.896.896 0 0 0 4.89 16H9l3 4 3-4h4.111a.896.896 0 0 0 .629-.256.868.868 0 0 0 .26-.619v-9.25a.868.868 0 0 0-.26-.619.896.896 0 0 0-.63-.256Z"/>
                </svg>
                Сообщения
            </span>
        </li>
        <li v-if="!canWorkWithoutDevice" :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.devices.*') }]">
            <span
                @click="router.visit(route('trader.devices.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.devices.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 15h12M6 6h12m-6 12h.01M7 21h10a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1Z"/>
                </svg>
                Устройства
            </span>
        </li>
    </ul>
</template>

<style scoped>

</style>
