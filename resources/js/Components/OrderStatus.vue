<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
    },
    status_name: {
        type: String,
    },
    /** Подробный статус каскада (ожидает оплаты и т.д.) — в режиме inline показывается в бейдже вместо общего названия */
    sub_status_name: {
        type: String,
        default: null,
    },
    /** Иконка + название в одну строку (бейдж DaisyUI) */
    inline: {
        type: Boolean,
        default: false,
    },
});

const iconToneClass = computed(
    () =>
        ({
            success: 'text-success',
            fail: 'text-error',
            pending: 'text-warning',
        })[props.status ?? ''] ?? 'text-base-content/50',
);

const svgClass = computed(() => (props.inline ? 'size-5' : 'size-7'));

const badgeClass = computed(() => {
    switch (props.status) {
        case 'success':
            return 'badge badge-sm badge-soft badge-success border-0 font-medium leading-none';
        case 'fail':
            return 'badge badge-sm badge-soft badge-error border-0 font-medium leading-none';
        case 'pending':
            return 'badge badge-sm badge-soft badge-warning border-0 font-medium leading-none';
        default:
            return 'badge badge-sm badge-soft badge-neutral border-0 font-medium leading-none';
    }
});

/** В бейдж — подстатус, если есть (конкретика), иначе общий статус («Успешно», «В обработке»…) */
const inlineBadgeText = computed(() => {
    const detail = props.sub_status_name?.trim();
    if (detail) {
        return detail;
    }

    return props.status_name ?? '—';
});
</script>

<template>
    <div class="contents">
    <!-- Компактная строка: иконка + подпись -->
    <div
        v-if="inline"
        class="inline-flex items-center gap-2 min-w-0 max-w-full"
        role="status"
    >
        <template v-if="status === 'success'">
            <div class="shrink-0" :class="iconToneClass" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" :class="svgClass">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
        <template v-else-if="status === 'fail'">
            <div class="shrink-0" :class="iconToneClass" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" :class="svgClass">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
        <template v-else-if="status === 'pending'">
            <div class="shrink-0" :class="iconToneClass" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" :class="svgClass">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
        <span
            :class="[badgeClass, 'truncate max-w-[14rem]']"
            :title="inlineBadgeText"
        >{{ inlineBadgeText }}</span>
    </div>

    <!-- По умолчанию: только иконка, подпись в tooltip -->
    <div v-else class="flex items-center text-nowrap text-base-content">
        <template v-if="status === 'success'">
            <div class="text-success tooltip" :data-tip="status_name">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
        <template v-if="status === 'fail'">
            <div class="text-error tooltip" :data-tip="status_name">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
        <template v-if="status === 'pending'">
            <div class="text-warning tooltip" :data-tip="status_name">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </template>
    </div>
    </div>
</template>
