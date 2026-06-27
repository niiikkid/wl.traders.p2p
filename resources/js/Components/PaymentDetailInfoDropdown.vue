<script setup>
import {computed, nextTick, onMounted, onUnmounted, ref} from "vue";
import {usePage} from "@inertiajs/vue3";
import {useModalStore} from "@/store/modal.js";

const props = defineProps({
    paymentDetailId: {
        type: [Number, String],
        required: true,
    },
});

const modalStore = useModalStore();
const currentUser = usePage().props.auth?.user;

const isOpen = ref(false);
const isLoading = ref(false);
const isLoaded = ref(false);
const error = ref(null);
const paymentDetail = ref(null);
const dropdown = ref(null);
const button = ref(null);
const overlay = ref(null);
const dropdownPosition = ref({top: 0, left: 0});
const dropdownMaxHeight = ref(null);

const canSetOrderAmountLimits = computed(() => {
    return currentUser?.can_set_order_amount_limits === true || currentUser?.can_set_order_amount_limits === 1;
});

const shouldShowAmountRange = computed(() => {
    return canSetOrderAmountLimits.value
        || paymentDetail.value?.owner_can_set_order_amount_limits
        || paymentDetail.value?.min_order_amount !== null
        || paymentDetail.value?.max_order_amount !== null;
});

const dropdownStyles = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    width: "min(22rem, calc(100vw - 1rem))",
    maxHeight: dropdownMaxHeight.value ?? "none",
    overflowY: dropdownMaxHeight.value ? "auto" : "visible",
}));

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
};

const hasLimit = (limit) => {
    return normalizeNumber(limit) > 0;
};

const formatInteger = (value) => {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 0,
    }).format(Math.trunc(number));
};

const formatMoneyAmount = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const normalized = String(value).replace(/\s/g, '').replace(',', '.');
    const number = Number(normalized);

    if (!Number.isFinite(number)) {
        return String(value);
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
};

const limitLabel = (current, limit) => {
    if (!hasLimit(limit)) {
        return 'Без лимита';
    }

    return `${current ?? 0} из ${limit}`;
};

const amountLimitLabel = (current, limit) => {
    if (!hasLimit(limit)) {
        return 'Без лимита';
    }

    return `${current ?? 0} из ${limit}`;
};

const processingModeLabel = computed(() => {
    return paymentDetail.value && !paymentDetail.value.user_device_id ? 'Ручной' : 'Автоматика';
});

const processingModeBadgeClass = computed(() => {
    return paymentDetail.value && !paymentDetail.value.user_device_id
        ? 'badge-warning badge-outline'
        : 'badge-success badge-outline';
});

const updateDropdownPosition = () => {
    if (!button.value || !dropdown.value) {
        return;
    }

    const gap = 6;
    const viewportPadding = 8;
    const rect = button.value.getBoundingClientRect();
    const dropdownWidth = dropdown.value.offsetWidth;
    const dropdownHeight = dropdown.value.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const minLeft = window.scrollX + viewportPadding;
    const maxLeft = window.scrollX + viewportWidth - dropdownWidth - viewportPadding;
    const targetLeft = rect.right + window.scrollX - dropdownWidth;
    const spaceAbove = rect.top - viewportPadding - gap;
    const spaceBelow = viewportHeight - rect.bottom - viewportPadding - gap;
    const opensUp = spaceAbove > spaceBelow;
    const availableHeight = Math.max(160, opensUp ? spaceAbove : spaceBelow);
    const top = opensUp
        ? rect.top + window.scrollY - Math.min(dropdownHeight, availableHeight) - gap
        : rect.bottom + window.scrollY + gap;

    dropdownPosition.value = {
        top: Math.max(window.scrollY + viewportPadding, top),
        left: Math.max(minLeft, Math.min(targetLeft, maxLeft)),
    };
    dropdownMaxHeight.value = `${availableHeight}px`;
};

const loadPaymentDetail = async () => {
    if (isLoaded.value || isLoading.value) {
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('payment-details.show', props.paymentDetailId), {
            headers: {'Accept': 'application/json'},
        });
        paymentDetail.value = response.data?.data || response.data;
        isLoaded.value = true;
    } catch (e) {
        error.value = 'Не удалось загрузить реквизит';
    } finally {
        isLoading.value = false;
        await nextTick();
        updateDropdownPosition();
    }
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick();
        updateDropdownPosition();
        await loadPaymentDetail();
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

const resetLoadedDetail = () => {
    paymentDetail.value = null;
    isLoaded.value = false;
};

const openEditModal = () => {
    if (!paymentDetail.value) {
        return;
    }

    modalStore.openPaymentDetailEditModal({
        paymentDetail: paymentDetail.value,
        reloadProps: ['orders'],
        onSaved: resetLoadedDetail,
    });
    closeDropdown();
};

const handleClickOutside = (event) => {
    if (
        dropdown.value &&
        !dropdown.value.contains(event.target) &&
        button.value &&
        !button.value.contains(event.target) &&
        overlay.value &&
        !overlay.value.contains(event.target)
    ) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    window.addEventListener("resize", updateDropdownPosition);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener("resize", updateDropdownPosition);
});
</script>

<template>
    <div class="relative inline-block">
        <button
            ref="button"
            type="button"
            class="btn btn-ghost btn-circle btn-xs shrink-0 text-info"
            :class="{'btn-disabled': isLoading}"
            :disabled="isLoading"
            aria-label="Информация по реквизиту"
            @click.stop="toggleDropdown"
        >
            <span v-if="isLoading" class="loading loading-spinner loading-xs" aria-hidden="true"></span>
            <svg v-else class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7h.01M12 11v6m9-5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </button>

        <teleport to="body">
            <div
                v-if="isOpen"
                ref="overlay"
                class="fixed inset-0 z-40"
                @click="closeDropdown"
            ></div>

            <div
                v-if="isOpen"
                ref="dropdown"
                class="absolute z-50 rounded-box border border-base-300 bg-base-100 shadow-lg pointer-events-auto"
                :style="dropdownStyles"
            >
                <div class="grid gap-3 p-3 text-xs leading-tight">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-base-content">Реквизит</div>
                            <div class="truncate text-base-content/60">
                                {{ paymentDetail?.name || 'Загрузка...' }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary btn-outline btn-xs shrink-0"
                            :disabled="!paymentDetail || isLoading"
                            @click.prevent="openEditModal"
                        >
                            Редактировать
                        </button>
                    </div>

                    <div v-if="isLoading" class="flex items-center justify-center gap-2 py-6 text-base-content/70">
                        <span class="loading loading-spinner loading-sm" aria-hidden="true"></span>
                        <span>Загрузка...</span>
                    </div>

                    <div v-else-if="error" class="alert alert-error alert-outline py-2 text-xs">
                        <span>{{ error }}</span>
                    </div>

                    <template v-else-if="paymentDetail">
                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-base-content/60">
                                Обработка
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Режим:</span>
                                    <span class="badge badge-xs" :class="processingModeBadgeClass">
                                        {{ processingModeLabel }}
                                    </span>
                                </div>
                                <div v-if="paymentDetail.user_device_id" class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Устройство:</span>
                                    <span class="text-right">{{ paymentDetail.device_name }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Интервал:</span>
                                    <span class="text-right">{{ paymentDetail.order_interval_minutes !== null ? paymentDetail.order_interval_minutes + ' мин' : '-' }}</span>
                                </div>
                                <div v-if="shouldShowAmountRange" class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Мин:</span>
                                    <span class="text-right">{{ paymentDetail.min_order_amount !== null ? paymentDetail.min_order_amount : '∞' }}</span>
                                </div>
                                <div v-if="shouldShowAmountRange" class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Макс:</span>
                                    <span class="text-right">{{ paymentDetail.max_order_amount !== null ? paymentDetail.max_order_amount : '∞' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">В день</span>
                                <span class="badge badge-xs badge-outline">суточные</span>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Активных сделок:</span>
                                    <span class="text-right font-medium">
                                        {{ limitLabel(paymentDetail.pending_orders_count, paymentDetail.max_pending_orders_quantity) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Количество сделок:</span>
                                    <span class="text-right font-medium">
                                        {{ limitLabel(paymentDetail.current_daily_successful_orders_count, paymentDetail.daily_successful_orders_limit) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Объём сделок:</span>
                                    <span class="text-right font-medium">
                                        {{ amountLimitLabel(paymentDetail.current_daily_limit, paymentDetail.daily_limit) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">В месяц</span>
                                <span class="badge badge-xs badge-outline">сброс {{ paymentDetail.monthly_limit_reset_day ?? '—' }}</span>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Объём сделок:</span>
                                    <span class="text-right font-medium">
                                        {{ amountLimitLabel(paymentDetail.current_monthly_limit, paymentDetail.monthly_limit) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Количество сделок:</span>
                                    <span class="text-right font-medium">
                                        {{ limitLabel(paymentDetail.current_monthly_successful_orders_count, paymentDetail.monthly_successful_orders_limit) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-box border border-base-200 bg-base-100 p-2">
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-base-content/60">
                                Оборот
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Сделок:</span>
                                    <span class="text-right font-medium">{{ formatInteger(paymentDetail.successful_orders_total_count) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Оборот:</span>
                                    <span class="text-right font-medium">
                                        {{ formatMoneyAmount(paymentDetail.successful_orders_total_turnover_fiat) }}
                                        <span class="text-primary">{{ paymentDetail.currency?.toUpperCase?.() }}</span>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-base-content/70">Оборот:</span>
                                    <span class="text-right font-medium">
                                        {{ formatMoneyAmount(paymentDetail.successful_orders_total_turnover_usdt) }}
                                        <span class="text-primary">USDT</span>
                                    </span>
                                </div>
                                <div class="pt-1 text-center text-[10px] text-base-content/50">
                                    Обновляется раз в 15 минут
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </teleport>
    </div>
</template>
