<script setup>
import Modal from '@/Components/Modals/Modal.vue';
import ModalHeader from '@/Components/Modals/Components/ModalHeader.vue';
import ModalBody from '@/Components/Modals/Components/ModalBody.vue';
import PaymentDetail from '@/Components/PaymentDetail.vue';
import GatewayLogo from '@/Components/GatewayLogo.vue';
import PaymentDetailStatisticsContextPanel from '@/Components/PaymentDetail/PaymentDetailStatisticsContextPanel.vue';
import { useModalStore } from '@/store/modal.js';
import { useViewStore } from '@/store/view.js';
import { storeToRefs } from 'pinia';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const modalStore = useModalStore();
const viewStore = useViewStore();
const { paymentDetailVolumeStatisticsModal } = storeToRefs(modalStore);

const loading = ref(false);
const selectedPeriod = ref('current_month');
const payload = ref(null);
const loadError = ref('');

const paymentDetailRouteKey = computed(() => (
    paymentDetailVolumeStatisticsModal.value.params?.uuid
    ?? paymentDetailVolumeStatisticsModal.value.params?.paymentDetail?.uuid
    ?? paymentDetailVolumeStatisticsModal.value.params?.id
    ?? paymentDetailVolumeStatisticsModal.value.params?.paymentDetail?.id
    ?? null
));

const volumeStatisticsRoute = computed(() => {
    if (!paymentDetailRouteKey.value) {
        return null;
    }

    return viewStore.isAdminViewMode
        ? route('admin.payment-details.volume-statistics', paymentDetailRouteKey.value)
        : route('payment-details.volume-statistics', paymentDetailRouteKey.value);
});

const periodOptions = computed(() => payload.value?.period_options ?? []);
const apiPaymentDetail = computed(() => payload.value?.payment_detail ?? null);
const displayPaymentDetail = computed(() => payload.value?.context_detail ?? null);
const distribution = computed(() => payload.value?.distribution ?? { buckets: [], total_deals: 0 });
const currencySymbol = computed(() => (
    apiPaymentDetail.value?.currency_symbol
    ?? displayPaymentDetail.value?.currency?.toUpperCase?.()
    ?? ''
));

const modalTitle = computed(() => {
    const name = displayPaymentDetail.value?.name ?? '';

    return name ? `Статистика — ${name}` : 'Статистика по реквизиту';
});

const shouldShowProcessingIndicator = computed(() => {
    const detail = displayPaymentDetail.value;

    if (!detail) {
        return false;
    }

    return viewStore.isAdminViewMode || !!detail.owner_can_work_without_device;
});

const usesManualProcessing = computed(() => !displayPaymentDetail.value?.user_device_id);

const distributionIndicatorColor = (count) => (
    Number(count) > 0 ? 'hsl(142, 71%, 40%)' : 'hsl(0, 0%, 72%)'
);

const formatInteger = (value) => Number(value ?? 0).toLocaleString('ru-RU');

const close = () => {
    modalStore.closeModal('paymentDetailVolumeStatistics');
};

const loadStatistics = async () => {
    if (!volumeStatisticsRoute.value) {
        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(volumeStatisticsRoute.value, {
            params: { period: selectedPeriod.value },
        });

        payload.value = response.data;
        selectedPeriod.value = response.data?.period ?? selectedPeriod.value;
    } catch (error) {
        loadError.value = error.response?.data?.message ?? 'Не удалось загрузить статистику.';
        payload.value = null;
    } finally {
        loading.value = false;
    }
};

const selectPeriod = (period) => {
    if (selectedPeriod.value === period || loading.value) {
        return;
    }

    selectedPeriod.value = period;
    loadStatistics();
};

const resetState = () => {
    loading.value = false;
    loadError.value = '';
    payload.value = null;
    selectedPeriod.value = 'current_month';
};

watch(
    () => paymentDetailVolumeStatisticsModal.value.showed,
    async (showed) => {
        if (showed) {
            resetState();
            await loadStatistics();
        } else {
            resetState();
        }
    },
);
</script>

<template>
    <Modal
        :show="paymentDetailVolumeStatisticsModal.showed"
        max-width="6xl"
        @close="close"
    >
        <ModalHeader
            :title="modalTitle"
            @close="close"
        />
        <ModalBody>
            <div
                v-if="!paymentDetailRouteKey"
                class="py-8 text-center text-sm text-base-content/60"
            >
                Реквизит не выбран.
            </div>

            <div
                v-else-if="loading && !displayPaymentDetail"
                class="flex justify-center py-12"
            >
                <span class="loading loading-spinner loading-md" />
            </div>

            <template v-else-if="displayPaymentDetail">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,22rem)_1fr]">
                    <div class="space-y-4">
                        <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <GatewayLogo
                                    v-if="displayPaymentDetail.payment_gateway"
                                    :img_path="displayPaymentDetail.payment_gateway.logo_path"
                                    :name="displayPaymentDetail.payment_gateway.name"
                                    class="h-14 w-14 shrink-0"
                                />
                                <div class="min-w-0 flex-1">
                                    <PaymentDetail
                                        size="md"
                                        :detail="displayPaymentDetail.detail"
                                        :type="displayPaymentDetail.detail_type"
                                        :name="displayPaymentDetail.name"
                                        :show-processing-indicator="shouldShowProcessingIndicator"
                                        :uses-manual-processing="usesManualProcessing"
                                    />
                                </div>
                            </div>
                        </div>

                        <PaymentDetailStatisticsContextPanel
                            :payment-detail="displayPaymentDetail"
                        />
                    </div>

                    <div
                        class="space-y-4"
                        :class="{ 'pointer-events-none opacity-60': loading && payload }"
                    >
                        <div
                            v-if="periodOptions.length"
                            class="card border border-base-300 bg-base-200/40"
                        >
                            <div class="card-body gap-3 p-4">
                                <p class="text-xs text-base-content/60">
                                    Период, за который считаются объем и сделки по реквизиту
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="option in periodOptions"
                                        :key="option.value"
                                        type="button"
                                        class="btn btn-xs"
                                        :class="selectedPeriod === option.value ? 'btn-primary' : 'btn-outline btn-primary'"
                                        :disabled="loading"
                                        @click="selectPeriod(option.value)"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="loading && !payload"
                            class="flex justify-center py-12"
                        >
                            <span class="loading loading-spinner loading-md" />
                        </div>

                        <p
                            v-else-if="loadError"
                            class="py-8 text-center text-sm text-error"
                        >
                            {{ loadError }}
                        </p>

                        <div
                            v-else-if="payload"
                            class="card border border-base-300 bg-base-200/40"
                        >
                            <div class="card-body gap-4 p-4">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-base-content/60">
                                    За выбранный период
                                </p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <p class="text-xs text-base-content/50">
                                            Объем успешных сделок
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                                            {{ payload.volume }}
                                            <span class="text-lg text-primary">{{ currencySymbol }}</span>
                                        </p>
                                    </div>
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <p class="text-xs text-base-content/50">
                                            Количество сделок
                                        </p>
                                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                                            {{ formatInteger(payload.deals_count) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="payload"
                            class="card border border-base-300 bg-base-200/40"
                        >
                            <div class="card-body gap-3 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-medium text-base-content/80">
                                        Распределение сделок по сумме
                                    </h4>
                                    <span class="badge badge-outline badge-sm">
                                        Сделок: {{ formatInteger(distribution.total_deals) }}
                                    </span>
                                </div>

                                <div
                                    v-if="(distribution.buckets ?? []).length > 0"
                                    class="overflow-x-auto rounded-box border border-base-300 bg-base-100"
                                >
                                    <table class="table table-xs table-zebra text-[11px]">
                                        <thead>
                                            <tr class="text-[10px] uppercase text-base-content/60">
                                                <th class="py-2">
                                                    Диапазон
                                                </th>
                                                <th class="py-2 text-right">
                                                    Сделок
                                                </th>
                                                <th class="py-2 text-right">
                                                    %
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="bucket in distribution.buckets"
                                                :key="bucket.key"
                                                :class="bucket.count > 0 ? '' : 'text-base-content/50'"
                                            >
                                                <td class="py-1.5">
                                                    <span class="flex min-w-0 items-center gap-2">
                                                        <span
                                                            class="size-2 shrink-0 rounded-full ring-2 ring-base-100"
                                                            :style="{ backgroundColor: distributionIndicatorColor(bucket.count) }"
                                                        />
                                                        <span class="truncate leading-tight">
                                                            {{ bucket.label }}
                                                        </span>
                                                    </span>
                                                </td>
                                                <td class="py-1.5 text-right tabular-nums">
                                                    {{ formatInteger(bucket.count) }}
                                                </td>
                                                <td class="py-1.5 text-right tabular-nums">
                                                    {{ bucket.percent }}%
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p
                                    v-else
                                    class="py-8 text-center text-xs text-base-content/60"
                                >
                                    Нет данных для распределения.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </ModalBody>
    </Modal>
</template>
