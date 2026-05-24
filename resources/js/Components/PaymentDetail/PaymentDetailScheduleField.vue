<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Select from '@/Components/Select.vue';
import { usePaymentDetailSchedules } from '@/composables/usePaymentDetailSchedules.js';
import { useModalStore } from '@/store/modal.js';
import { computed, onMounted, watch } from 'vue';

const model = defineModel({ type: [Number, String, null], default: null });

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showCreate: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['clear-error']);

const modalStore = useModalStore();
const {
    fetchSchedules,
    scheduleOptions,
    findScheduleById,
    serverTimezone,
    serverNow,
    loading,
} = usePaymentDetailSchedules();

const scheduleItems = computed(() => scheduleOptions());

const selectedSchedule = computed(() => findScheduleById(model.value));

const selectedSchedulePreview = computed(() => {
    const schedule = selectedSchedule.value;

    if (!schedule) {
        return null;
    }

    const intervals = schedule.today_intervals || [];
    const intervalText = intervals.length
        ? intervals.map((interval) => `${interval.starts_at}-${interval.ends_at}`).join(', ')
        : null;

    return {
        name: schedule.name,
        status_label: schedule.status_label,
        intervalText,
    };
});

const formatServerNow = computed(() => {
    if (!serverNow.value) {
        return null;
    }

    try {
        return new Date(serverNow.value).toLocaleString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
        });
    } catch {
        return null;
    }
});

const clearSchedule = () => {
    model.value = null;
    emit('clear-error', 'payment_detail_schedule_id');
};

const scheduleManagerParams = () => ({
    onCreated: (schedule) => {
        if (schedule?.id) {
            model.value = schedule.id;
        }
        emit('clear-error', 'payment_detail_schedule_id');
    },
});

const openCreateSchedule = () => {
    modalStore.openPaymentDetailScheduleManagerModal({
        ...scheduleManagerParams(),
        startInCreate: true,
        closeOnCreate: true,
    });
};

const openScheduleManager = () => {
    modalStore.openPaymentDetailScheduleManagerModal({
        ...scheduleManagerParams(),
        scheduleId: model.value || null,
    });
};

onMounted(() => {
    fetchSchedules();
});

const refreshSchedulesAfterModal = (modalKey) => {
    watch(
        () => modalStore.modals[modalKey]?.showed,
        (showed, wasShowed) => {
            if (wasShowed && !showed) {
                fetchSchedules(true);
            }
        },
    );
};

refreshSchedulesAfterModal('paymentDetailScheduleManager');
</script>

<template>
    <div class="rounded-box border border-base-300 p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm font-medium">
                Рабочее расписание
            </div>
            <div v-if="formatServerNow" class="text-xs text-base-content/60">
                Сервер: {{ formatServerNow }}
                <span v-if="serverTimezone">({{ serverTimezone }})</span>
            </div>
        </div>

        <p class="text-xs text-base-content/70">
            Время интервалов указывается по времени сервера. Расписание ограничивает трафик, но не меняет активность реквизита.
        </p>

        <div>
            <InputLabel
                for="payment_detail_schedule_id"
                value="Расписание"
                :error="!!errors.payment_detail_schedule_id?.[0]"
                class="mb-1"
            />
            <Select
                id="payment_detail_schedule_id"
                v-model="model"
                :items="scheduleItems"
                value="id"
                name="name"
                default_title="Без расписания"
                :error="!!errors.payment_detail_schedule_id?.[0]"
                :disabled="disabled || loading"
                @change="emit('clear-error', 'payment_detail_schedule_id')"
            />
            <InputError :message="errors.payment_detail_schedule_id?.[0]" class="mt-2" />
            <div v-if="!scheduleItems.length && !loading" class="text-xs text-base-content/70 mt-2">
                Расписания ещё не созданы — можно создать новое.
            </div>
        </div>

        <div v-if="selectedSchedulePreview" class="text-xs text-base-content/80 space-y-1">
            <div>
                <span class="font-medium">{{ selectedSchedulePreview.name }}</span>
                <span class="opacity-70"> — {{ selectedSchedulePreview.status_label }}</span>
            </div>
            <div v-if="selectedSchedulePreview.intervalText" class="opacity-70">
                Сегодня: {{ selectedSchedulePreview.intervalText }}
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-if="model"
                type="button"
                class="btn btn-sm btn-ghost"
                :disabled="disabled || loading"
                @click="clearSchedule"
            >
                Убрать расписание
            </button>
            <button
                v-if="showCreate"
                type="button"
                class="btn btn-sm btn-outline"
                :disabled="disabled || loading"
                @click="openCreateSchedule"
            >
                Создать расписание
            </button>
            <button
                type="button"
                class="btn btn-sm btn-outline"
                :disabled="disabled || loading"
                @click="openScheduleManager"
            >
                Управлять расписаниями
            </button>
        </div>
    </div>
</template>
