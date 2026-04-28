<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import {useUserStore} from "@/store/user.js";
import OnlineSwitcher from "@/Layouts/Partials/OnlineSwitcher.vue";

const menu = ref(usePage().props.menu);
const userStore = useUserStore();
const payoutsEnabled = computed(() => !!usePage().props.auth?.user?.payouts_enabled);

router.on('success', (event) => {
    menu.value = usePage().props.menu;
})
</script>

<template>
    <ul class="menu menu-md w-full space-y-0.5">
        <ViewModeSwitcher v-if="userStore.isAdmin" class="mb-2"/>
        <li class="not-prose mb-3 mt-0.5 min-w-0 py-0.5">
            <OnlineSwitcher/>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.main.index') }]">
            <span
                @click="router.visit(route('trader.main.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.main.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="2 0 21 21" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.918 10.0005H7.082C6.66587 9.99708 6.26541 10.1591 5.96873 10.4509C5.67204 10.7427 5.50343 11.1404 5.5 11.5565V17.4455C5.5077 18.3117 6.21584 19.0078 7.082 19.0005H9.918C10.3341 19.004 10.7346 18.842 11.0313 18.5502C11.328 18.2584 11.4966 17.8607 11.5 17.4445V11.5565C11.4966 11.1404 11.328 10.7427 11.0313 10.4509C10.7346 10.1591 10.3341 9.99708 9.918 10.0005Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.918 4.0006H7.082C6.23326 3.97706 5.52559 4.64492 5.5 5.4936V6.5076C5.52559 7.35629 6.23326 8.02415 7.082 8.0006H9.918C10.7667 8.02415 11.4744 7.35629 11.5 6.5076V5.4936C11.4744 4.64492 10.7667 3.97706 9.918 4.0006Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M15.082 13.0007H17.917C18.3333 13.0044 18.734 12.8425 19.0309 12.5507C19.3278 12.2588 19.4966 11.861 19.5 11.4447V5.55666C19.4966 5.14054 19.328 4.74282 19.0313 4.45101C18.7346 4.1592 18.3341 3.9972 17.918 4.00066H15.082C14.6659 3.9972 14.2654 4.1592 13.9687 4.45101C13.672 4.74282 13.5034 5.14054 13.5 5.55666V11.4447C13.5034 11.8608 13.672 12.2585 13.9687 12.5503C14.2654 12.8421 14.6659 13.0041 15.082 13.0007Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M15.082 19.0006H17.917C18.7661 19.0247 19.4744 18.3567 19.5 17.5076V16.4936C19.4744 15.6449 18.7667 14.9771 17.918 15.0006H15.082C14.2333 14.9771 13.5256 15.6449 13.5 16.4936V17.5066C13.525 18.3557 14.2329 19.0241 15.082 19.0006Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Панель управления
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('news.*') }]">
            <span
                @click="router.visit(route('news.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('news.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 opacity-30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                </svg>
                Новости
                <span v-if="menu.newsUnreadCount" class="badge badge-primary badge-sm justify-self-end">
                    {{ menu.newsUnreadCount }}
                </span>
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('sms-logs.*') }]">
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.devices.*') }]">
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
       <!-- <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.economy.*') }]">
            <span
                @click="router.visit(route('trader.economy.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.economy.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" fill="currentColor" aria-hidden="true">
                    <path d="M32 13.22V29H4V7h18.57a8.35 8.35 0 0 1-.07-1 8.35 8.35 0 0 1 .07-1H4a2 2 0 0 0-2 2v22a2 2 0 0 0 2 2h28a2 2 0 0 0 2-2V12.34a8.45 8.45 0 0 1-2 .88Z"/>
                    <path d="m15.62 15.22-6.02 8.75-4.05-3.58 1.06-1.2 2.7 2.39 6.32-9.2 6.75 10.02 6.76-8.93 1.27.97-8.1 10.71-6.69-9.93Z"/>
                    <circle cx="30" cy="6" r="5"/>
                </svg>
                Экономика
            </span>
        </li>-->
    </ul>
</template>
