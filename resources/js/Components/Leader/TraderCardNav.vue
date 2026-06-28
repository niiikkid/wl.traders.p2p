<script setup>
import { router } from '@inertiajs/vue3';
import PaymentDetailsIcon from '@/Layouts/Partials/Icons/PaymentDetailsIcon.vue';
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import DisputesIcon from '@/Layouts/Partials/Icons/DisputesIcon.vue';
import WalletIcon from '@/Layouts/Partials/Icons/WalletIcon.vue';

const props = defineProps({
    traderId: {
        type: [Number, String],
        required: true,
    },
    current: {
        type: String,
        required: true,
        validator: (value) => ['payment-details', 'orders', 'disputes', 'finances'].includes(value),
    },
});

const items = [
    {
        key: 'payment-details',
        label: 'Реквизиты',
        route: 'leader.traders.payment-details.index',
        icon: PaymentDetailsIcon,
    },
    {
        key: 'orders',
        label: 'Сделки',
        route: 'leader.traders.orders.index',
        icon: OrdersIcon,
    },
    {
        key: 'disputes',
        label: 'Споры',
        route: 'leader.traders.disputes.index',
        icon: DisputesIcon,
    },
    {
        key: 'finances',
        label: 'Финансы',
        route: 'leader.traders.finances.index',
        icon: WalletIcon,
    },
];

const visit = (item) => {
    if (props.current === item.key) {
        return;
    }

    router.visit(route(item.route, { trader: props.traderId }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <nav aria-label="Разделы карточки трейдера" class="w-fit max-w-full">
        <div
            role="tablist"
            class="tabs tabs-box bg-base-200/60 border border-base-300/70 rounded-xl p-1 gap-0.5 w-fit max-w-full overflow-x-auto"
        >
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                role="tab"
                class="tab gap-2 whitespace-nowrap px-3 sm:px-4"
                :class="{ 'tab-active font-semibold': current === item.key }"
                :aria-selected="current === item.key"
                :aria-current="current === item.key ? 'page' : undefined"
                @click="visit(item)"
            >
                <span class="inline-flex shrink-0 [&>svg]:size-4 [&>svg]:opacity-80">
                    <component :is="item.icon" aria-hidden="true" />
                </span>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
