<script setup>
import axios from 'axios';
import {computed, ref, watch} from 'vue';
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import ModalFooterNext from '@/Components/Modals/Next/ModalFooterNext.vue';
import DateTime from '@/Components/DateTime.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    order: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'linked']);

const smsLogs = ref([]);
const isLoading = ref(false);
const loadError = ref('');
const selectedSmsLogId = ref(null);
const isConfirming = ref(false);
const isLinking = ref(false);
const linkError = ref('');

const selectedSmsLog = computed(() => {
    return smsLogs.value.find((item) => item.id === selectedSmsLogId.value) ?? null;
});

const resetState = () => {
    smsLogs.value = [];
    selectedSmsLogId.value = null;
    isConfirming.value = false;
    isLinking.value = false;
    loadError.value = '';
    linkError.value = '';
};

const close = () => {
    if (isLinking.value) {
        return;
    }

    emit('close');
};

const loadSmsLogs = async () => {
    if (!props.order?.id) {
        return;
    }

    isLoading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(route('orders.unlinked-sms-logs.index', props.order.id));

        if (response.data.success) {
            smsLogs.value = response.data.data.sms_logs ?? [];
            selectedSmsLogId.value = smsLogs.value[0]?.id ?? null;
        }
    } catch {
        loadError.value = 'Не удалось загрузить непривязанные сообщения.';
    } finally {
        isLoading.value = false;
    }
};

const selectSmsLog = (smsLogId) => {
    if (isLinking.value) {
        return;
    }

    selectedSmsLogId.value = smsLogId;
    isConfirming.value = false;
    linkError.value = '';
};

const startLink = () => {
    if (!selectedSmsLogId.value || isLinking.value) {
        return;
    }

    isConfirming.value = true;
    linkError.value = '';
};

const cancelLink = () => {
    if (isLinking.value) {
        return;
    }

    isConfirming.value = false;
    linkError.value = '';
};

const confirmLink = async () => {
    if (!props.order?.id || !selectedSmsLogId.value || isLinking.value) {
        return;
    }

    isLinking.value = true;
    linkError.value = '';

    try {
        const response = await axios.post(route('orders.link-sms-log.store', props.order.id), {
            sms_log_id: selectedSmsLogId.value,
        });

        if (response.data.success) {
            emit('linked', response.data.data.sms_log);
            emit('close');
        }
    } catch (error) {
        linkError.value = error?.response?.data?.message ?? 'Не удалось привязать сообщение.';
        isConfirming.value = false;
    } finally {
        isLinking.value = false;
    }
};

watch(
    () => props.show,
    (showed) => {
        if (showed) {
            resetState();
            loadSmsLogs();
        }
    },
);
</script>

<template>
    <ModalNext :show="show" max-width="md" @close="close">
        <ModalHeaderNext
            :title="order ? `Непривязанные поступления · #${order.uuid_short}` : 'Непривязанные поступления'"
            @close="close"
        />

        <ModalBodyNext>
            <div v-if="isLoading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <div
                v-else-if="loadError"
                role="alert"
                class="alert alert-error alert-soft text-sm"
            >
                <span>{{ loadError }}</span>
            </div>

            <div
                v-else-if="smsLogs.length === 0"
                role="alert"
                class="alert alert-dash text-sm"
            >
                <span>Нет непривязанных поступлений для этого устройства.</span>
            </div>

            <div v-else class="space-y-2">
                <p class="text-xs text-base-content/60">
                    Выберите сообщение и привяжите его к сделке.
                </p>

                <div class="max-h-[min(24rem,50dvh)] space-y-2 overflow-y-auto pr-0.5">
                    <button
                        v-for="smsLog in smsLogs"
                        :key="smsLog.id"
                        type="button"
                        class="w-full space-y-2 rounded-box border p-3 text-left transition"
                        :class="selectedSmsLogId === smsLog.id
                            ? 'border-primary bg-primary/10 shadow-sm'
                            : 'border-base-300/80 bg-base-200/40 hover:border-primary/40 hover:bg-base-200/70'"
                        @click="selectSmsLog(smsLog.id)"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="truncate text-xs font-semibold text-primary sm:text-sm">
                                    {{ smsLog.sender }}
                                </span>
                                <span
                                    v-if="smsLog.amount_matches_order"
                                    class="badge badge-success badge-xs shrink-0"
                                >
                                    Сумма совпадает
                                </span>
                            </div>
                            <DateTime
                                class="shrink-0 text-xs text-base-content/60"
                                :data="smsLog.created_at"
                                :simple="true"
                            />
                        </div>

                        <p class="text-sm leading-snug text-base-content break-words whitespace-pre-wrap">
                            {{ smsLog.message }}
                        </p>

                        <div
                            v-if="smsLog.amount || smsLog.bank || smsLog.card || smsLog.device_name"
                            class="flex flex-wrap gap-x-3 gap-y-1 border-t border-base-300/50 pt-2 text-xs text-base-content/70"
                        >
                            <span v-if="smsLog.amount">Сумма: {{ smsLog.amount }}</span>
                            <span v-if="smsLog.bank">Банк: {{ smsLog.bank }}</span>
                            <span v-if="smsLog.card">Карта: *{{ smsLog.card }}</span>
                            <span v-if="smsLog.device_name">{{ smsLog.device_name }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <p v-if="linkError" class="mt-3 text-xs text-error">
                {{ linkError }}
            </p>
        </ModalBodyNext>

        <ModalFooterNext v-if="!isLoading && !loadError && smsLogs.length > 0">
            <div v-if="isConfirming" class="join w-full">
                <button
                    type="button"
                    class="btn btn-sm join-item flex-1"
                    :disabled="isLinking"
                    @click="cancelLink"
                >
                    Не привязывать
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary join-item flex-1"
                    :disabled="isLinking"
                    @click="confirmLink"
                >
                    <span v-if="isLinking" class="loading loading-spinner loading-xs" />
                    <span v-else>Да, привязать</span>
                </button>
            </div>
            <button
                v-else
                type="button"
                class="btn btn-primary btn-sm w-full"
                :disabled="!selectedSmsLogId || isLinking"
                @click="startLink"
            >
                Привязать
            </button>
        </ModalFooterNext>
    </ModalNext>
</template>
