<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import {useUserStore} from "@/store/user.js";

const menu = ref(usePage().props.menu);
const userStore = useUserStore();

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
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5"/>
                </svg>
                Главная
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
    </ul>
</template>

<style scoped>

</style>
