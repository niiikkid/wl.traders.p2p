<script setup>
import { router } from '@inertiajs/vue3';
import TraderCardNav from '@/Components/Leader/TraderCardNav.vue';
import TraderOnlineStatus from '@/Components/Leader/TraderOnlineStatus.vue';

defineProps({
    trader: {
        type: Object,
        required: true,
    },
    current: {
        type: String,
        required: true,
        validator: (value) => ['payment-details', 'orders', 'disputes', 'finances'].includes(value),
    },
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-2">
                <div class="breadcrumbs text-sm">
                    <ul>
                        <li>
                            <button
                                type="button"
                                class="link link-hover"
                                @click="router.visit(route('leader.traders.index'))"
                            >
                                Трейдеры
                            </button>
                        </li>
                        <li class="truncate max-w-[16rem] sm:max-w-none">{{ trader.email }}</li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <h3 class="text-lg font-semibold text-base-content truncate max-w-full">
                        {{ trader.email }}
                    </h3>
                    <TraderOnlineStatus :is-online="!!trader.is_online" />
                </div>
            </div>

            <TraderCardNav :trader-id="trader.id" :current="current" />
        </div>

        <slot />
    </div>
</template>
