<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import {useUserStore} from "@/store/user.js";

const menu = ref(usePage().props.menu);
const userStore = useUserStore();
const extendedTeamLeaderAccessEnabled = computed(() => {
    return !!usePage().props.auth?.user?.team_leader_extended_access_enabled;
});

router.on('success', (event) => {
    menu.value = usePage().props.menu;
})
</script>

<template>
    <ul class="menu menu-md w-full space-y-0.5">
        <ViewModeSwitcher v-if="userStore.isAdmin" class="mb-2"/>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('leader.main.index') }]">
            <span
                @click="router.visit(route('leader.main.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('leader.main.index'), { preserveScroll: true })"
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('leader.news.*') }]">
            <span
                @click="router.visit(route('leader.news.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('leader.news.index'), { preserveScroll: true })"
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('leader.finances.*') }]">
            <span
                @click="router.visit(route('leader.finances.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('leader.finances.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                </svg>
                Финансы
            </span>
        </li>


        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('leader.referrals.*') }]">
            <span
                @click="router.visit(route('leader.referrals.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('leader.referrals.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3a2.5 2.5 0 1 1 2-4.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3a2.5 2.5 0 1 0-2-4.5m.5 14h-8a1 1 0 0 1-1-1 3 3 0 0 1 3-3h4a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-2-7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                Рефералы
            </span>
        </li>
        <li v-if="extendedTeamLeaderAccessEnabled" :class="[{ 'bg-base-content/10 rounded-lg': route().current('leader.traders.*') }]">
            <span
                @click="router.visit(route('leader.traders.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('leader.traders.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v1H4V7Zm0 3h16v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6Zm10 3a1 1 0 1 0 0 2h3a1 1 0 1 0 0-2h-3Z"/>
                </svg>
                Трейдеры
            </span>
        </li>
    </ul>
</template>

<style scoped>

</style>
