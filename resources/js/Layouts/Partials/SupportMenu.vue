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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('support.users.*') }]">
            <span
                @click="router.visit(route('support.users.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('support.users.index'), { preserveScroll: true })"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" stroke-width="1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3a2.5 2.5 0 1 1 2-4.5M19.5 17h.5c.6 0 1-.4 1-1a3 3 0 0 0-3-3h-1m0-3a2.5 2.5 0 1 0-2-4.5m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3c0 .6-.4 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                </svg>
                Пользователи
                <span v-if="menu.onlineUsers" class="badge badge-primary badge-sm justify-self-end">
                    {{ menu.onlineUsers }}
                </span>
            </span>
        </li>
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('support.orders.*') }]">
            <span
                @click="router.visit(route('support.orders.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('support.orders.index'), { preserveScroll: true })"
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
        <li :class="[{ 'bg-base-content/10 rounded-lg': route().current('support.disputes.*') }]">
            <span
                @click="router.visit(route('support.disputes.index'), { preserveScroll: true })"
                @keydown.enter.space="router.visit(route('support.disputes.index'), { preserveScroll: true })"
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
    </ul>
</template>
