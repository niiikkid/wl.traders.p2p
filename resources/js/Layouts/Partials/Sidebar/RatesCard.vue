<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    variant: { type: String, default: 'desktop' },
});

const showAll = ref(false);
const isMobile = computed(() => props.variant === 'mobile');
const priceField = computed(() => (isMobile.value ? 'sell_price' : 'buy_price'));
</script>

<template>
    <div class="card bg-base-100" :class="{ 'shadow': !isMobile }">
        <div :class="isMobile ? 'card-body' : 'w-full p-6 pb-3'">
            <div class="flex items-center mb-2">
                <span
                    class="text-xs"
                    :class="isMobile ? 'text-base-content/70' : 'text-base-content font-semibold'"
                >
                    Курс Tether TRC-20
                </span>
            </div>
            <div class="text-xs">
                <ul class="space-y-1">
                    <li
                        v-for="(rate, index) in rates"
                        v-show="index < 3 || showAll"
                        :key="rate.code"
                        class="flex justify-between items-center pb-1 last:border-none"
                        :class="isMobile ? 'border-b border-base-300' : 'border-b border-dashed border-primary/50'"
                    >
                        <span class="text-xs text-base-content">{{ rate[priceField] }}</span>
                        <span class="text-xs text-primary">{{ rate.code.toUpperCase() }}</span>
                    </li>
                </ul>
                <div class="flex justify-center mt-3">
                    <button type="button" class="btn btn-ghost btn-sm" @click="showAll = !showAll">
                        <span v-show="!showAll">Показать все</span>
                        <span v-show="showAll">Спрятать</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
