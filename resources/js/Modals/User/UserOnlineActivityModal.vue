<script setup>
import { computed, ref, watch } from 'vue';
import { useModalStore } from '@/store/modal.js';

const modalStore = useModalStore();

const STEP_SECONDS = 15;
const SLOT_SECONDS = 15 * 60; // 15 минут
const SLOTS_PER_DAY = 86400 / SLOT_SECONDS; // 96
const STEPS_PER_SLOT = SLOT_SECONDS / STEP_SECONDS; // 60
const MAX_DAYS_BACK = 6; // всего 7 дней: сегодня + 6 предыдущих
const TODAY_REFRESH_MS = 20000;

const WEEKDAYS = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

const isOpen = computed(() => modalStore.isOpen('userOnlineActivity'));
const user = computed(() => modalStore.paramsOf('userOnlineActivity')?.user ?? null);

const loading = ref(false);
const errorMessage = ref('');
const presentSet = ref(new Set());
// 0 = сегодня, -1..-6 = предыдущие дни
const dayOffset = ref(0);
const selectedSlot = ref(null);
let refreshTimer = null;

const pad = (value) => String(value).padStart(2, '0');

/** Локальная полночь для выбранного дня (в таймзоне пользователя). */
const dayStartDate = computed(() => {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() + dayOffset.value);

    return date;
});

const dayStartEpoch = computed(() => Math.floor(dayStartDate.value.getTime() / 1000));
const dayStartBucket = computed(() => Math.floor(dayStartEpoch.value / STEP_SECONDS));

const dayLabel = computed(() => {
    const date = dayStartDate.value;

    return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
});

const weekdayLabel = computed(() => WEEKDAYS[dayStartDate.value.getDay()]);

const isToday = computed(() => dayOffset.value === 0);
const canGoPrev = computed(() => dayOffset.value > -MAX_DAYS_BACK);
const canGoNext = computed(() => dayOffset.value < 0);

/** Позиция текущего дня в окне из 7 дней (1 — самый старый, 7 — сегодня). */
const dayPosition = computed(() => MAX_DAYS_BACK + 1 + dayOffset.value);

const formatTime = (epochSeconds) => {
    const date = new Date(epochSeconds * 1000);

    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const formatTimeWithSeconds = (epochSeconds) => {
    const date = new Date(epochSeconds * 1000);

    return `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

/** Сетка из 96 ячеек по 15 минут. */
const slots = computed(() => {
    const base = dayStartBucket.value;
    const set = presentSet.value;
    const result = [];

    for (let i = 0; i < SLOTS_PER_DAY; i++) {
        let count = 0;
        const slotBase = base + i * STEPS_PER_SLOT;

        for (let j = 0; j < STEPS_PER_SLOT; j++) {
            if (set.has(slotBase + j)) {
                count++;
            }
        }

        const startEpoch = dayStartEpoch.value + i * SLOT_SECONDS;

        result.push({
            index: i,
            count,
            startEpoch,
            label: `${formatTime(startEpoch)}–${formatTime(startEpoch + SLOT_SECONDS)}`,
        });
    }

    return result;
});

/** Детализация выбранного 15-минутного слота с шагом 15 секунд. */
const slotSteps = computed(() => {
    if (selectedSlot.value === null) {
        return [];
    }

    const i = selectedSlot.value;
    const slotBase = dayStartBucket.value + i * STEPS_PER_SLOT;
    const set = presentSet.value;
    const result = [];

    for (let j = 0; j < STEPS_PER_SLOT; j++) {
        const startEpoch = dayStartEpoch.value + i * SLOT_SECONDS + j * STEP_SECONDS;

        result.push({
            index: j,
            online: set.has(slotBase + j),
            startEpoch,
        });
    }

    return result;
});

const ROW_SLOTS = 8; // 2 часа в строке (8 × 15 минут)

/** Слоты, разбитые на строки с подписью периода. */
const slotRows = computed(() => {
    const all = slots.value;
    const rows = [];

    for (let i = 0; i < all.length; i += ROW_SLOTS) {
        const chunk = all.slice(i, i + ROW_SLOTS);
        const startEpoch = chunk[0].startEpoch;
        const endEpoch = chunk[chunk.length - 1].startEpoch + SLOT_SECONDS;

        rows.push({
            key: i,
            label: `${formatTime(startEpoch)}–${formatTime(endEpoch)}`,
            slots: chunk,
        });
    }

    return rows;
});

const selectedSlotLabel = computed(() => {
    if (selectedSlot.value === null) {
        return '';
    }

    return slots.value[selectedSlot.value]?.label ?? '';
});

const onlineSecondsTotal = computed(() => presentSet.value.size * STEP_SECONDS);

const onlineDurationLabel = computed(() => {
    const total = onlineSecondsTotal.value;

    if (total <= 0) {
        return 'не был онлайн';
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (hours > 0) {
        return `${hours} ч ${minutes} мин`;
    }

    return `${minutes} мин`;
});

const slotClass = (count) => {
    if (count <= 0) {
        return 'bg-base-content/10';
    }

    if (count >= STEPS_PER_SLOT) {
        return 'bg-success';
    }

    return 'bg-success/50';
};

const stepClass = (online) => (online ? 'bg-success' : 'bg-base-content/15');

const close = () => {
    modalStore.close('userOnlineActivity');
};

const stopRefresh = () => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
};

const fetchActivity = async () => {
    if (! user.value?.id) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await window.axios.get(
            route('admin.users.online-pings', { user: user.value.id }),
            {
                params: {
                    from: dayStartEpoch.value,
                    to: dayStartEpoch.value + 86400,
                },
            },
        );

        const buckets = Array.isArray(data.data?.buckets) ? data.data.buckets : [];
        presentSet.value = new Set(buckets.map((bucket) => Number(bucket)));
    } catch {
        errorMessage.value = 'Не удалось загрузить статистику онлайна.';
        presentSet.value = new Set();
    } finally {
        loading.value = false;
    }
};

const startRefresh = () => {
    stopRefresh();

    if (isToday.value) {
        refreshTimer = setInterval(fetchActivity, TODAY_REFRESH_MS);
    }
};

const goPrevDay = () => {
    if (! canGoPrev.value) {
        return;
    }

    selectedSlot.value = null;
    dayOffset.value -= 1;
};

const goNextDay = () => {
    if (! canGoNext.value) {
        return;
    }

    selectedSlot.value = null;
    dayOffset.value += 1;
};

const selectSlot = (index) => {
    selectedSlot.value = index;
};

const clearSelectedSlot = () => {
    selectedSlot.value = null;
};

watch(
    () => [isOpen.value, user.value?.id],
    ([open, userId]) => {
        if (! open || ! userId) {
            stopRefresh();
            presentSet.value = new Set();
            selectedSlot.value = null;

            return;
        }

        dayOffset.value = 0;
        selectedSlot.value = null;
        fetchActivity();
        startRefresh();
    },
);

watch(dayOffset, () => {
    if (! isOpen.value) {
        return;
    }

    fetchActivity();
    startRefresh();
});
</script>

<template>
    <dialog :open="isOpen" class="modal">
        <div class="modal-box w-11/12 max-w-xl p-5">
            <button
                type="button"
                class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                @click="close"
            >
                ✕
            </button>

            <h3 class="font-bold text-base mb-1">
                Онлайн в веб-панели
            </h3>
            <p v-if="user?.email" class="text-xs text-base-content/70 mb-4 truncate">
                {{ user.email }}
            </p>

            <!-- Навигация по дням -->
            <div class="flex items-center justify-between gap-2 mb-3">
                <button
                    type="button"
                    class="btn btn-sm btn-ghost"
                    :disabled="! canGoPrev"
                    @click="goPrevDay"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                    </svg>
                    <span class="hidden sm:inline">Раньше</span>
                </button>

                <div class="text-center">
                    <div class="text-sm font-medium leading-tight">
                        {{ dayLabel }}
                        <span v-if="isToday" class="badge badge-sm badge-success ml-1">сегодня</span>
                    </div>
                    <div class="text-xs text-base-content/60 leading-tight">{{ weekdayLabel }}</div>
                </div>

                <button
                    type="button"
                    class="btn btn-sm btn-ghost"
                    :disabled="! canGoNext"
                    @click="goNextDay"
                >
                    <span class="hidden sm:inline">Позже</span>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Индикатор положения в окне из 7 дней -->
            <div class="flex items-center justify-center gap-1 mb-4">
                <span
                    v-for="position in (MAX_DAYS_BACK + 1)"
                    :key="position"
                    :class="[
                        'h-1.5 rounded-full transition-all',
                        position === dayPosition ? 'w-5 bg-success' : 'w-1.5 bg-base-content/20',
                    ]"
                />
            </div>

            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md" />
            </div>

            <div v-else-if="errorMessage" class="alert alert-error text-sm">
                {{ errorMessage }}
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="text-xs text-base-content/70">
                        Сутки, шаг 15 минут. Нажмите на ячейку для детализации по 15 секунд.
                    </div>
                    <div class="text-xs text-base-content/70 text-nowrap">
                        Онлайн за день: <span class="font-medium text-base-content">{{ onlineDurationLabel }}</span>
                    </div>
                </div>

                <!-- Сетка суток: строки по 2 часа, ячейки по 15 минут -->
                <div class="space-y-[3px]">
                    <div
                        v-for="row in slotRows"
                        :key="row.key"
                        class="flex items-center gap-2"
                    >
                        <span class="w-[78px] shrink-0 text-[11px] tabular-nums text-base-content/60 text-right">
                            {{ row.label }}
                        </span>
                        <div class="flex gap-[2px]">
                            <button
                                v-for="slot in row.slots"
                                :key="slot.index"
                                type="button"
                                :class="[
                                    'w-[18px] h-[18px] sm:w-[21px] sm:h-[21px] rounded-[3px] cursor-pointer transition-all duration-150',
                                    'hover:scale-110 hover:brightness-110 hover:ring-2 hover:ring-success/70 hover:z-10',
                                    'active:scale-95',
                                    slotClass(slot.count),
                                    selectedSlot === slot.index ? 'ring-2 ring-success scale-110' : '',
                                ]"
                                :title="`${slot.label} — ${slot.count} из ${STEPS_PER_SLOT} (15с)`"
                                @click="selectSlot(slot.index)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Детализация выбранного слота -->
                <div v-if="selectedSlot !== null" class="mt-4 rounded-box border border-base-content/10 p-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="text-sm font-medium">
                            Детально: {{ selectedSlotLabel }}
                            <span class="text-xs text-base-content/60 font-normal">(шаг 15с)</span>
                        </div>
                        <button type="button" class="btn btn-xs btn-ghost" @click="clearSelectedSlot">
                            Скрыть
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-[2px]">
                        <div
                            v-for="step in slotSteps"
                            :key="step.index"
                            :class="['w-[18px] h-[18px] sm:w-[21px] sm:h-[21px] rounded-[3px]', stepClass(step.online)]"
                            :title="`${formatTimeWithSeconds(step.startEpoch)} — ${step.online ? 'онлайн' : 'не онлайн'}`"
                        />
                    </div>
                </div>

                <!-- Легенда -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-4 text-xs text-base-content/70">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-[2px] bg-success" />
                        <span>был онлайн</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-[2px] bg-success/50" />
                        <span>частично</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-[2px] bg-base-content/10" />
                        <span>не был</span>
                    </div>
                </div>
            </template>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button type="button" @click="close">close</button>
        </form>
    </dialog>
</template>
