<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import DashboardIcon from '@/Layouts/Partials/Icons/DashboardIcon.vue';
import MerchantsIcon from '@/Layouts/Partials/Icons/MerchantsIcon.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';
import IntegrationIcon from '@/Layouts/Partials/Icons/IntegrationIcon.vue';
import LogsIcon from '@/Layouts/Partials/Icons/LogsIcon.vue';

const { menu } = useMenuCounters();
const payoutsEnabled = computed(() => !!usePage().props.auth?.user?.payouts_enabled);

const items = computed(() => {
    void menu.value;

    return [
        {
            key: 'dashboard',
            label: 'Панель управления',
            icon: DashboardIcon,
            href: route('merchant.main.index'),
            active: route().current('merchant.main.index'),
        },
        {
            key: 'merchants',
            label: 'Мерчанты',
            icon: MerchantsIcon,
            href: route('merchants.index'),
            active: route().current('merchants.*'),
        },
        {
            key: 'payments',
            label: 'Платежи',
            icon: OrdersIcon,
            href: route('payments.index'),
            active: route().current('payments.*'),
        },
        {
            key: 'payouts',
            label: 'Выплаты',
            icon: PayoutsIcon,
            href: route('merchant.payouts.index'),
            active: route().current('merchant.payouts.*'),
            show: payoutsEnabled.value,
        },
        {
            key: 'finances',
            label: 'Финансы',
            icon: WalletIcon,
            href: route('merchant.finances.index'),
            active: route().current('merchant.finances.*'),
        },
        {
            key: 'integration',
            label: 'API Интеграция',
            icon: IntegrationIcon,
            href: route('integration.index'),
            active: route().current('integration.*'),
        },
        {
            key: 'merchant-api-logs',
            label: 'Логи',
            icon: LogsIcon,
            href: route('merchant.merchant-api-logs.index'),
            active: route().current('merchant.merchant-api-logs.*'),
        },
    ];
});
</script>

<template>
    <SidebarMenu :items="items" />
</template>
