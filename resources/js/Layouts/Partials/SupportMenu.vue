<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import SupportUsersIcon from '@/Layouts/Partials/Icons/SupportUsersIcon.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import DepositsIcon from '@/Layouts/Partials/Icons/DepositsIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';

const { menu } = useMenuCounters();
const canViewDeposits = computed(() => !!usePage().props.auth?.user?.support_can_view_deposits);

const items = computed(() => [
    {
        key: 'users',
        label: 'Пользователи',
        icon: SupportUsersIcon,
        href: route('support.users.index'),
        active: route().current('support.users.*'),
        badge: menu.value.onlineUsers,
    },
    {
        key: 'orders',
        label: 'Сделки',
        icon: OrdersIcon,
        href: route('support.orders.index'),
        active: route().current('support.orders.*'),
        badge: menu.value.pendingOrdersCount,
        badgeClass: 'badge-info',
    },
    {
        key: 'deposits',
        label: 'Депозиты средств',
        icon: DepositsIcon,
        href: route('support.deposits.index'),
        active: route().current('support.deposits.*'),
        show: canViewDeposits.value,
    },
    {
        key: 'payouts',
        label: 'Выплаты',
        icon: PayoutsIcon,
        href: route('support.payouts.index'),
        active: route().current('support.payouts.*'),
        badge: menu.value.payoutsActiveCount,
        badgeClass: 'badge-info',
    },
]);
</script>

<template>
    <SidebarMenu :items="items" />
</template>
