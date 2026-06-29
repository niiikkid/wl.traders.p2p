<script setup>
import { computed } from 'vue';
import SidebarMenu from '@/Layouts/Partials/Sidebar/SidebarMenu.vue';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import DashboardIcon from '@/Layouts/Partials/Icons/DashboardIcon.vue';
import UsersIcon from '@/Layouts/Partials/Icons/UsersIcon.vue';
import CurrenciesIcon from '@/Layouts/Partials/Icons/CurrenciesIcon.vue';
import BankIcon from '@/Layouts/Partials/Icons/BankIcon.vue';
import PaymentDetailsIcon from '@/Layouts/Partials/Icons/PaymentDetailsIcon.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';
import DisputesIcon from '@/Layouts/Partials/Icons/DisputesIcon.vue';
import MerchantsIcon from '@/Layouts/Partials/Icons/MerchantsIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';
import AutomationIcon from '@/Layouts/Partials/Icons/AutomationIcon.vue';
import LogsIcon from '@/Layouts/Partials/Icons/LogsIcon.vue';
import AntiFraudIcon from '@/Layouts/Partials/Icons/AntiFraudIcon.vue';
import TelegramChatsIcon from '@/Layouts/Partials/Icons/TelegramChatsIcon.vue';
import SettingsIcon from '@/Layouts/Partials/Icons/SettingsIcon.vue';

const { menu } = useMenuCounters();

const items = computed(() => [
    {
        key: 'dashboard',
        label: 'Панель управления',
        icon: DashboardIcon,
        href: route('admin.main.index'),
        active: route().current('admin.main.index'),
    },
    {
        key: 'users',
        label: 'Пользователи',
        icon: UsersIcon,
        href: route('admin.users.index'),
        active: route().current('admin.users.*'),
        badge: menu.value.onlineUsers,
    },
    {
        key: 'currencies',
        label: 'Валюты',
        icon: CurrenciesIcon,
        href: route('admin.currencies.index'),
        active: route().current('admin.currencies.*'),
    },
    {
        key: 'payment-gateways',
        label: 'Банки',
        icon: BankIcon,
        href: route('admin.payment-gateways.index'),
        active: route().current('admin.payment-gateways.*'),
    },
    {
        key: 'payment-details',
        label: 'Реквизиты',
        icon: PaymentDetailsIcon,
        href: route('admin.payment-details.index'),
        active: route().current('admin.payment-details.*') || route().current('admin.enabled-cards.*'),
        badge: menu.value.activeDetails,
        badgeClass: 'badge-success',
    },
    {
        key: 'orders',
        label: 'Сделки',
        icon: OrdersIcon,
        href: route('admin.orders.index'),
        active: route().current('admin.orders.*'),
        badge: menu.value.pendingOrdersCount,
        badgeClass: 'badge-info',
    },
    {
        key: 'payouts',
        label: 'Выплаты',
        icon: PayoutsIcon,
        href: route('admin.payouts.index'),
        active: route().current('admin.payouts.*'),
        badge: menu.value.payoutsActiveCount,
        badgeClass: 'badge-info',
    },
    {
        key: 'disputes',
        label: 'Споры',
        icon: DisputesIcon,
        href: route('admin.disputes.index'),
        active: route().current('admin.disputes.*'),
        badge: menu.value.pendingDisputesCount,
        badgeClass: 'badge-error',
    },
    {
        key: 'merchants',
        label: 'Мерчанты',
        icon: MerchantsIcon,
        href: route('admin.merchants.index'),
        active: route().current('admin.merchants.*'),
    },
    {
        key: 'finances',
        label: 'Финансы',
        icon: WalletIcon,
        href: route('admin.finances.index'),
        active: route().current('admin.finances.*'),
        badge: menu.value.pendingWithdrawals,
        badgeClass: 'badge-warning',
        spanClass: 'text-nowrap',
    },
    {
        key: 'automation',
        label: 'Автоматика',
        icon: AutomationIcon,
        href: route('admin.sms-logs.index'),
        active: route().current('admin.sms-logs.*') || route().current('admin.app.*') || route().current('admin.devices.*'),
    },
    {
        key: 'merchant-api-logs',
        label: 'Логи',
        icon: LogsIcon,
        href: route('admin.merchant-api-logs.index'),
        active: route().current('admin.merchant-api-logs.*'),
    },
    // Калькулятор прибыли (admin.profit-calculator.*) — временно отключён
    {
        key: 'anti-fraud',
        label: 'Антифрод',
        icon: AntiFraudIcon,
        href: route('admin.anti-fraud.settings.index'),
        active: route().current('admin.anti-fraud.*'),
    },
    {
        key: 'telegram-chats',
        label: 'Telegram-чаты',
        icon: TelegramChatsIcon,
        href: route('admin.telegram-chats.index'),
        active: route().current('admin.telegram-chats.*'),
    },
    {
        key: 'settings',
        label: 'Настройки',
        icon: SettingsIcon,
        href: route('admin.settings.index'),
        active: route().current('admin.settings.*'),
    },
    // Компоненты (DEV) (admin.dev.components) — временно отключён
]);
</script>

<template>
    <SidebarMenu :items="items" />
</template>
