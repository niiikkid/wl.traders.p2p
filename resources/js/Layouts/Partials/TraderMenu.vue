<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import OnlineSwitcher from '@/Layouts/Partials/OnlineSwitcher.vue';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import DashboardIcon from '@/Layouts/Partials/Icons/DashboardIcon.vue';
import PaymentDetailsIcon from '@/Layouts/Partials/Icons/PaymentDetailsIcon.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';
import AutomationIcon from '@/Layouts/Partials/Icons/AutomationIcon.vue';

const { menu } = useMenuCounters();
const payoutsEnabled = computed(() => !!usePage().props.auth?.user?.payouts_enabled);

const items = computed(() => [
    {
        key: 'dashboard',
        label: 'Панель управления',
        icon: DashboardIcon,
        href: route('trader.main.index'),
        active: route().current('trader.main.index'),
    },
    {
        key: 'payment-details',
        label: 'Реквизиты',
        icon: PaymentDetailsIcon,
        href: route('payment-details.index'),
        active: route().current('payment-details.*'),
        badge: menu.value.activeDetails,
        badgeClass: 'badge-success',
    },
    {
        key: 'orders',
        label: 'Сделки',
        icon: OrdersIcon,
        href: route('orders.index'),
        active: route().current('orders.*'),
        badge: menu.value.pendingOrdersCount,
        badgeClass: 'badge-info',
    },
    {
        key: 'payouts',
        label: 'Выплаты',
        icon: PayoutsIcon,
        href: route('trader.payouts.index'),
        active: route().current('trader.payouts.*'),
        badge: menu.value.payoutsActiveCount,
        badgeClass: 'badge-info',
        show: payoutsEnabled.value,
    },
    {
        key: 'wallet',
        label: 'Финансы',
        icon: WalletIcon,
        href: route('wallet.index'),
        active: route().current('wallet.*'),
    },
    {
        key: 'automation',
        label: 'Автоматика',
        icon: AutomationIcon,
        href: route('trader.devices.index'),
        active: route().current('sms-logs.*') || route().current('trader.devices.*'),
    },
]);
</script>

<template>
    <SidebarMenu :items="items">
        <template #prepend>
            <li class="not-prose mb-3 mt-0.5 min-w-0 py-0.5">
                <OnlineSwitcher />
            </li>
        </template>
    </SidebarMenu>
</template>
