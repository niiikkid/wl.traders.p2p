<script setup>
import { computed } from 'vue';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import DashboardIcon from '@/Layouts/Partials/Icons/DashboardIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';
import UsersIcon from '@/Layouts/Partials/Icons/UsersIcon.vue';

const items = computed(() => [
    {
        key: 'dashboard',
        label: 'Панель управления',
        icon: DashboardIcon,
        href: route('leader.main.index'),
        active: route().current('leader.main.index'),
    },
    {
        key: 'finances',
        label: 'Финансы',
        icon: WalletIcon,
        href: route('leader.finances.index'),
        active: route().current('leader.finances.*'),
    },
    {
        key: 'traders',
        label: 'Трейдеры',
        icon: UsersIcon,
        href: route('leader.traders.index'),
        active: route().current('leader.traders.*'),
    },
]);
</script>

<template>
    <SidebarMenu :items="items" />
</template>
