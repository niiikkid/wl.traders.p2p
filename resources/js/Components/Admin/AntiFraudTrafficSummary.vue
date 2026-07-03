<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        required: true,
        validator: (value) => ['primary', 'secondary'].includes(value),
    },
    setting: {
        type: Object,
        required: true,
    },
});

const TYPE_CONFIG = {
    primary: {
        label: 'Первичный',
        badgeClass: 'badge-primary badge-outline',
        boxClass: 'border-primary/30 bg-primary/5',
        labelClass: 'text-primary',
    },
    secondary: {
        label: 'Вторичный',
        badgeClass: 'badge-secondary badge-outline',
        boxClass: 'border-secondary/30 bg-secondary/5',
        labelClass: 'text-secondary',
    },
};

const config = computed(() => TYPE_CONFIG[props.type]);

const isSecondaryDisabled = computed(() => {
    return props.type === 'secondary' && props.setting.secondary_enabled === false;
});

const prefix = computed(() => (props.type === 'primary' ? 'primary' : 'secondary'));

const maxPending = computed(() => props.setting[`${prefix.value}_max_pending`]);
const failedLimit = computed(() => props.setting[`${prefix.value}_failed_limit`]);
const blockDays = computed(() => props.setting[`${prefix.value}_block_days`]);
const rateLimits = computed(() => props.setting[`${prefix.value}_rate_limits`]);

const formatRateLimits = (limits) => {
    if (!limits || !limits.length) {
        return '—';
    }

    return limits.map((limit) => `${limit.count}/${limit.minutes}м`).join(', ');
};
</script>

<template>
    <div
        class="rounded-box border px-2 py-1.5 text-xs"
        :class="isSecondaryDisabled ? 'border-base-300/50 bg-base-200/30' : config.boxClass"
    >
        <div class="mb-1 flex items-center gap-1.5">
            <span class="badge badge-xs" :class="isSecondaryDisabled ? 'badge-ghost' : config.badgeClass">
                {{ config.label }}
            </span>
        </div>

        <div v-if="isSecondaryDisabled" class="text-base-content/50">
            Фильтры отключены
        </div>

        <dl v-else class="grid grid-cols-2 gap-x-2 gap-y-0.5">
            <div class="min-w-0">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/45">Pending</dt>
                <dd class="font-medium tabular-nums">{{ maxPending ?? '—' }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/45">Fail</dt>
                <dd class="font-medium tabular-nums">{{ failedLimit ?? '—' }}</dd>
            </div>
            <div class="min-w-0 col-span-2">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/45">Лимиты</dt>
                <dd class="truncate font-medium" :title="formatRateLimits(rateLimits)">
                    {{ formatRateLimits(rateLimits) }}
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/45">Блок</dt>
                <dd class="font-medium tabular-nums">{{ blockDays ?? '—' }} дн.</dd>
            </div>
        </dl>
    </div>
</template>
