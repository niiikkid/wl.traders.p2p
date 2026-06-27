<script setup>
import { computed } from 'vue';
import ViewModeSwitcher from '@/Layouts/Partials/ViewModeSwitcher.vue';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import { useUserStore } from '@/store/user.js';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import DashboardIcon from '@/Layouts/Partials/Icons/DashboardIcon.vue';
import NewsIcon from '@/Layouts/Partials/Icons/NewsIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';
import UsersIcon from '@/Layouts/Partials/Icons/UsersIcon.vue';

const userStore = useUserStore();
const { menu } = useMenuCounters();

const items = computed(() => [
    {
        key: 'dashboard',
        label: 'Панель управления',
        icon: DashboardIcon,
        href: route('leader.main.index'),
        active: route().current('leader.main.index'),
    },
    {
        key: 'news',
        label: 'Новости',
        icon: NewsIcon,
        href: route('leader.news.index'),
        active: route().current('leader.news.*'),
        badge: menu.value.newsUnreadCount,
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
    <SidebarMenu :items="items">
        <template #prepend>
            <ViewModeSwitcher v-if="userStore.isAdmin" class="mb-2" />
        </template>
    </SidebarMenu>
</template>
