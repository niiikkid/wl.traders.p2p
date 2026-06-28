<script setup>
import OrdersIcon from '@/Layouts/Partials/Icons/OrdersIcon.vue';
import PayoutsIcon from '@/Layouts/Partials/Icons/PayoutsIcon.vue';

defineProps({
    current: {
        type: String,
        required: true,
        validator: (value) => ['deals', 'payouts'].includes(value),
    },
});

const emit = defineEmits(['switch']);

const items = [
    {
        key: 'deals',
        label: 'Сделки',
        icon: OrdersIcon,
    },
    {
        key: 'payouts',
        label: 'Выплаты',
        icon: PayoutsIcon,
    },
];

const select = (key, isActive) => {
    if (isActive) {
        return;
    }

    emit('switch', key);
};
</script>

<template>
    <nav aria-label="Разделы статистики" class="w-fit max-w-full">
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
                @click="select(item.key, current === item.key)"
            >
                <span class="inline-flex shrink-0 [&>svg]:size-4 [&>svg]:opacity-80">
                    <component :is="item.icon" aria-hidden="true" />
                </span>
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
