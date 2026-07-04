<script setup>
import { computed } from 'vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';
import AppTooltip from '@/Components/AppTooltip.vue';

const props = defineProps({
    img_path: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: null,
    },
    currency: {
        type: String,
        default: null,
    },
    logoClass: {
        type: String,
        default: 'w-10 h-10',
    },
});

const currencyLabel = computed(() => String(props.currency ?? '').trim().toUpperCase());
const hasCurrency = computed(() => currencyLabel.value.length > 0);
</script>

<template>
    <div class="indicator shrink-0">
        <AppTooltip
            v-if="hasCurrency"
            :tip="currencyLabel"
            wrapper-class="indicator-item indicator-bottom indicator-end translate-x-0.5 translate-y-0.5"
        >
            <span class="inline-flex rounded-full bg-base-100 p-0.5 shadow-sm ring-1 ring-base-content/10">
                <CurrencyDisplay
                    :currency="currency"
                    :show-label="false"
                    size="sm"
                    :icon-size="16"
                />
            </span>
        </AppTooltip>

        <GatewayLogo
            :img_path="img_path"
            :name="name"
            :class="logoClass"
        />
    </div>
</template>
