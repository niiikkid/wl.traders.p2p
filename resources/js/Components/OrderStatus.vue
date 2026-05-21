<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
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
            provisioning: 'text-info',
            provisioning_failed: 'text-error',
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
        case 'provisioning':
            return 'badge badge-sm badge-soft badge-info border-0 font-medium leading-none';
        case 'provisioning_failed':
            return 'badge badge-sm badge-soft badge-error border-0 font-medium leading-none';
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
        <template v-else-if="status === 'provisioning'">
            <div class="shrink-0" :class="iconToneClass" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="svgClass" fill="currentColor">
                    <path fill-rule="evenodd" d="M17,21 L17,23 L15,23 L15,21 L17,21 Z M19,21 L21,21 C21,22.1045695 20.1045695,23 19,23 L19,21 Z M13,21 L13,23 L11,23 L11,21 L13,21 Z M9,21 L9,23 L7,23 L7,21 L9,21 Z M5,21 L5,23 C3.8954305,23 3,22.1045695 3,21 L5,21 Z M19,13 L21,13 L21,15 L19,15 L19,13 Z M19,11 L19,9 L15,9 C13.8954305,9 13,8.1045695 13,7 L13,3 L5,3 L5,11 L3,11 L3,3 C3,1.8954305 3.8954305,1 5,1 L15.4142136,1 L21,6.58578644 L21,11 L19,11 Z M5,13 L5,15 L3,15 L3,13 L5,13 Z M19,17 L21,17 L21,19 L19,19 L19,17 Z M5,17 L5,19 L3,19 L3,17 L5,17 Z M15,3.41421356 L15,7 L18.5857864,7 L15,3.41421356 Z" />
                </svg>
            </div>
        </template>
        <template v-else-if="status === 'provisioning_failed'">
            <div class="shrink-0" :class="iconToneClass" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" :class="svgClass">
                    <path fill="currentColor" d="M6 12C6 12.5523 6.44772 13 7 13L17 13C17.5523 13 18 12.5523 18 12C18 11.4477 17.5523 11 17 11H7C6.44772 11 6 11.4477 6 12Z" />
                    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M12 23C18.0751 23 23 18.0751 23 12C23 5.92487 18.0751 1 12 1C5.92487 1 1 5.92487 1 12C1 18.0751 5.92487 23 12 23ZM12 20.9932C7.03321 20.9932 3.00683 16.9668 3.00683 12C3.00683 7.03321 7.03321 3.00683 12 3.00683C16.9668 3.00683 20.9932 7.03321 20.9932 12C20.9932 16.9668 16.9668 20.9932 12 20.9932Z" />
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
            <AppTooltip :tip="status_name" wrapper-class="text-success inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'fail'">
            <AppTooltip :tip="status_name" wrapper-class="text-error inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'pending'">
            <AppTooltip :tip="status_name" wrapper-class="text-warning inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'provisioning'">
            <AppTooltip :tip="status_name" wrapper-class="text-info inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M17,21 L17,23 L15,23 L15,21 L17,21 Z M19,21 L21,21 C21,22.1045695 20.1045695,23 19,23 L19,21 Z M13,21 L13,23 L11,23 L11,21 L13,21 Z M9,21 L9,23 L7,23 L7,21 L9,21 Z M5,21 L5,23 C3.8954305,23 3,22.1045695 3,21 L5,21 Z M19,13 L21,13 L21,15 L19,15 L19,13 Z M19,11 L19,9 L15,9 C13.8954305,9 13,8.1045695 13,7 L13,3 L5,3 L5,11 L3,11 L3,3 C3,1.8954305 3.8954305,1 5,1 L15.4142136,1 L21,6.58578644 L21,11 L19,11 Z M5,13 L5,15 L3,15 L3,13 L5,13 Z M19,17 L21,17 L21,19 L19,19 L19,17 Z M5,17 L5,19 L3,19 L3,17 L5,17 Z M15,3.41421356 L15,7 L18.5857864,7 L15,3.41421356 Z" />
                </svg>
            </AppTooltip>
        </template>
        <template v-if="status === 'provisioning_failed'">
            <AppTooltip :tip="status_name" wrapper-class="text-error inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="size-7" aria-hidden="true">
                    <path fill="currentColor" d="M6 12C6 12.5523 6.44772 13 7 13L17 13C17.5523 13 18 12.5523 18 12C18 11.4477 17.5523 11 17 11H7C6.44772 11 6 11.4477 6 12Z" />
                    <path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M12 23C18.0751 23 23 18.0751 23 12C23 5.92487 18.0751 1 12 1C5.92487 1 1 5.92487 1 12C1 18.0751 5.92487 23 12 23ZM12 20.9932C7.03321 20.9932 3.00683 16.9668 3.00683 12C3.00683 7.03321 7.03321 3.00683 12 3.00683C16.9668 3.00683 20.9932 7.03321 20.9932 12C20.9932 16.9668 16.9668 20.9932 12 20.9932Z" />
                </svg>
            </AppTooltip>
        </template>
    </div>
    </div>
</template>
