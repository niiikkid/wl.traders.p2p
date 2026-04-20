<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import {useUserStore} from "@/store/user.js";
import OnlineSwitcher from "@/Layouts/Partials/OnlineSwitcher.vue";
import {useNotificationCenterStore} from "@/store/notificationCenter.js";

const menu = ref(usePage().props.menu);
const userStore = useUserStore();
const notificationCenterStore = useNotificationCenterStore();
const canWorkWithoutDevice = computed(() => !!usePage().props.auth?.user?.can_work_without_device);
const payoutsEnabled = computed(() => !!usePage().props.auth?.user?.payouts_enabled);
const unreadNotificationsCount = computed(() => notificationCenterStore.unreadCount);

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
                <span v-if="unreadNotificationsCount" class="badge badge-primary badge-sm justify-self-end">
                    {{ unreadNotificationsCount }}
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('trader.feedback.*') }]">
            <span
                @click="router.visit(route('trader.feedback.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('trader.feedback.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" xmlns="http://www.w3.org/2000/svg" viewBox="1 1 38 38" fill="none" aria-hidden="true">
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M22.746,31.173h-5.538c-0.505,0-0.912-0.41-0.912-0.912V29.62c0.006-0.551-0.021-0.938-0.079-1.154c-0.311-1.139-1.134-2.094-1.931-3.02c-0.263-0.303-0.511-0.592-0.739-0.883c-1.147-1.459-1.757-3.207-1.757-5.061c0-4.526,3.684-8.207,8.21-8.207c4.525,0,8.208,3.681,8.208,8.207c0,1.855-0.607,3.605-1.757,5.063c-0.201,0.258-0.433,0.528-0.675,0.813c-0.816,0.96-1.727,2.031-2.041,3.162c-0.055,0.202-0.082,0.565-0.078,1.073v0.646C23.657,30.763,23.25,31.173,22.746,31.173L22.746,31.173z M18.122,29.347h3.712c0.01-0.71,0.079-1.06,0.142-1.288c0.423-1.523,1.471-2.76,2.396-3.846c0.243-0.286,0.456-0.536,0.645-0.774c0.893-1.136,1.366-2.495,1.366-3.936c0-3.52-2.862-6.384-6.382-6.384s-6.384,2.864-6.384,6.384c0,1.438,0.472,2.798,1.366,3.934c0.211,0.27,0.442,0.535,0.687,0.818c0.843,0.979,1.888,2.192,2.31,3.733C18.042,28.229,18.114,28.596,18.122,29.347L18.122,29.347z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M22.746,33.588h-5.538c-0.505,0-0.912-0.409-0.912-0.914c0-0.504,0.407-0.911,0.912-0.911h5.538c0.504,0,0.911,0.407,0.911,0.911C23.657,33.179,23.25,33.588,22.746,33.588L22.746,33.588z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M21.362,36h-2.769c-0.505,0-0.914-0.409-0.914-0.912c0-0.504,0.409-0.914,0.914-0.914h2.769c0.502,0,0.912,0.41,0.912,0.914C22.274,35.591,21.864,36,21.362,36L21.362,36z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M34.592,20.415h-3.648c-0.504,0-0.914-0.407-0.914-0.912c0-0.504,0.41-0.912,0.914-0.912h3.648c0.504,0,0.911,0.408,0.911,0.912C35.503,20.008,35.096,20.415,34.592,20.415L34.592,20.415z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M9.057,20.415H5.408c-0.504,0-0.911-0.407-0.911-0.912c0-0.504,0.407-0.912,0.911-0.912h3.648c0.504,0,0.914,0.408,0.914,0.912C9.971,20.008,9.561,20.415,9.057,20.415L9.057,20.415z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M20,9.474c-0.505,0-0.914-0.407-0.914-0.912V4.912C19.086,4.409,19.495,4,20,4s0.912,0.409,0.912,0.912v3.649C20.912,9.066,20.505,9.474,20,9.474L20,9.474z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M30.314,30.733c-0.232,0-0.465-0.089-0.644-0.268l-2.579-2.58c-0.356-0.355-0.356-0.934,0-1.291c0.357-0.356,0.934-0.354,1.29,0.002l2.579,2.579c0.357,0.357,0.357,0.936,0,1.292C30.782,30.646,30.55,30.733,30.314,30.733L30.314,30.733z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M12.26,12.679c-0.232,0-0.466-0.089-0.646-0.268L9.037,9.833c-0.357-0.357-0.357-0.936,0-1.293c0.357-0.356,0.934-0.356,1.29,0l2.579,2.579c0.357,0.356,0.357,0.936,0,1.292C12.728,12.59,12.495,12.679,12.26,12.679L12.26,12.679z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M9.682,30.733c-0.233,0-0.466-0.087-0.645-0.266c-0.357-0.356-0.357-0.935,0-1.292l2.581-2.581c0.354-0.356,0.931-0.356,1.288,0c0.357,0.357,0.357,0.936,0,1.293l-2.579,2.58C10.148,30.645,9.916,30.733,9.682,30.733L9.682,30.733z"/>
                    <path fill="currentColor" stroke="currentColor" stroke-width="0.90" stroke-linejoin="round" stroke-linecap="round" d="M27.738,12.679c-0.233,0-0.466-0.089-0.646-0.268c-0.354-0.356-0.354-0.934,0-1.29l2.579-2.579c0.354-0.356,0.935-0.358,1.29-0.002c0.357,0.357,0.357,0.934,0,1.291l-2.577,2.578C28.206,12.588,27.973,12.679,27.738,12.679L27.738,12.679z"/>
                </svg>
                Мои идеи
            </span>
        </li>
    </ul>
</template>
