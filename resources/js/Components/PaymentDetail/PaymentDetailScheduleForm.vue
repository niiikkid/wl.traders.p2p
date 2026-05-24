<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import ScheduleTimeInput from '@/Components/PaymentDetail/ScheduleTimeInput.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    WEEKDAY_OPTIONS,
    addDayOverrideInterval,
    removeDayOverrideInterval,
    setDayOverrideEnabled,
    toggleDefaultDay,
} from '@/composables/usePaymentDetailScheduleEditor.js';
import { computed } from 'vue';

const editorState = defineModel({
    type: Object,
    required: true,
});

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
    processing: {
        type: Boolean,
        default: false,
    },
    serverTimezone: {
        type: String,
        default: null,
    },
    serverNow: {
        type: String,
        default: null,
    },
    showSharedEditWarning: {
        type: Boolean,
        default: false,
    },
    attachedCount: {
        type: Number,
        default: 0,
    },
});

const compactInputClass = 'w-full input-sm h-8 min-h-8 text-sm';

const formatServerNow = computed(() => {
    if (!props.serverNow) {
        return null;
    }

    try {
        return new Date(props.serverNow).toLocaleString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
        });
    } catch {
        return null;
    }
});

const isDefaultDaySelected = (day) => (editorState.value.defaultDays || []).includes(day);

const isDayOverrideEnabled = (day) => !!editorState.value.dayOverrides?.[day]?.enabled;

const dayOverrideIntervals = (day) => editorState.value.dayOverrides?.[day]?.intervals || [];

const hasAnyDayOverride = computed(() => {
    return Object.values(editorState.value.dayOverrides || {}).some((override) => override?.enabled);
});

const onToggleDefaultDay = (day) => {
    editorState.value = toggleDefaultDay(editorState.value, day);
};

const onToggleOverride = (day, enabled) => {
    editorState.value = setDayOverrideEnabled(editorState.value, day, enabled);
};

const onAddOverrideInterval = (day) => {
    editorState.value = addDayOverrideInterval(editorState.value, day);
};

const onRemoveOverrideInterval = (day, index) => {
    editorState.value = removeDayOverrideInterval(editorState.value, day, index);
};

const updateOverrideInterval = (day, index, field, value) => {
    const dayOverrides = { ...(editorState.value.dayOverrides || {}) };
    const override = dayOverrides[day];

    if (!override) {
        return;
    }

    const intervals = override.intervals.map((interval, intervalIndex) => {
        if (intervalIndex !== index) {
            return { ...interval };
        }

        return { ...interval, [field]: value };
    });

    dayOverrides[day] = { ...override, intervals };
    editorState.value = { ...editorState.value, dayOverrides };
};
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="showSharedEditWarning"
            class="alert alert-warning text-xs py-1.5 min-h-0"
        >
            Изменения применятся ко всем реквизитам, где используется это расписание
            <span v-if="attachedCount"> ({{ attachedCount }})</span>.
        </div>

        <p class="text-[11px] leading-snug text-base-content/70">
            Время интервалов — по серверу.
            <span v-if="formatServerNow">
                Сейчас: {{ formatServerNow }}
                <span v-if="serverTimezone">({{ serverTimezone }})</span>.
            </span>
        </p>

        <div>
            <InputLabel
                for="schedule_editor_name"
                value="Название"
                :error="!!errors.name?.[0] || !!errors.name"
                class="mb-0.5 [&_.label-text]:text-xs"
            />
            <TextInput
                id="schedule_editor_name"
                v-model="editorState.name"
                type="text"
                :class="[compactInputClass, { 'input-error': !!errors.name?.[0] || !!errors.name }]"
                autocomplete="off"
                :disabled="processing"
            />
            <InputError :message="errors.name?.[0] || errors.name" class="mt-1" />
        </div>

        <div class="space-y-1.5">
            <InputLabel
                value="Рабочие дни"
                :error="!!errors.defaultDays"
                class="mb-0 [&_.label-text]:text-xs"
            />
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="weekday in WEEKDAY_OPTIONS"
                    :key="weekday.value"
                    type="button"
                    class="btn btn-xs min-h-7 h-7 px-2"
                    :class="isDefaultDaySelected(weekday.value) ? 'btn-primary' : 'btn-outline'"
                    :disabled="processing || isDayOverrideEnabled(weekday.value)"
                    @click="onToggleDefaultDay(weekday.value)"
                >
                    {{ weekday.label }}
                </button>
            </div>
            <p v-if="hasAnyDayOverride" class="text-[11px] leading-snug text-base-content/60">
                Дни с отдельным расписанием настраиваются ниже.
            </p>
            <InputError :message="errors.defaultDays" class="mt-0.5" />
        </div>

        <div class="grid gap-2 grid-cols-2">
            <div>
                <InputLabel
                    for="schedule_default_start"
                    value="Начало"
                    :error="!!errors.defaultStart"
                    class="mb-0.5 [&_.label-text]:text-xs"
                />
                <ScheduleTimeInput
                    id="schedule_default_start"
                    v-model="editorState.defaultStart"
                    :error="!!errors.defaultStart"
                    :disabled="processing"
                    aria-label="Начало общего интервала"
                />
                <InputError :message="errors.defaultStart" class="mt-0.5" />
            </div>
            <div>
                <InputLabel
                    for="schedule_default_end"
                    value="Окончание"
                    :error="!!errors.defaultEnd"
                    class="mb-0.5 [&_.label-text]:text-xs"
                />
                <ScheduleTimeInput
                    id="schedule_default_end"
                    v-model="editorState.defaultEnd"
                    :error="!!errors.defaultEnd"
                    :disabled="processing"
                    aria-label="Окончание общего интервала"
                />
                <InputError :message="errors.defaultEnd" class="mt-0.5" />
            </div>
        </div>

        <div class="space-y-1.5">
            <div class="text-xs font-medium">
                Отдельно по дням
            </div>
            <p class="text-[11px] leading-snug text-base-content/70">
                Переопределение заменяет общий интервал для дня.
            </p>

            <div class="divide-y divide-base-300 rounded-box border border-base-300">
                <div
                    v-for="weekday in WEEKDAY_OPTIONS"
                    :key="`override-${weekday.value}`"
                    class="px-2 py-1.5 space-y-1.5"
                >
                    <label class="flex cursor-pointer items-center gap-2 min-h-7">
                        <input
                            type="checkbox"
                            class="checkbox checkbox-xs shrink-0"
                            :checked="isDayOverrideEnabled(weekday.value)"
                            :disabled="processing"
                            @change="onToggleOverride(weekday.value, $event.target.checked)"
                        />
                        <span class="text-xs">{{ weekday.label }}</span>
                    </label>

                    <template v-if="isDayOverrideEnabled(weekday.value)">
                        <div class="space-y-1.5 pl-5">
                            <div
                                v-for="(interval, index) in dayOverrideIntervals(weekday.value)"
                                :key="`${weekday.value}-${index}`"
                                class="flex flex-nowrap items-center gap-2"
                            >
                                <div class="join">
                                    <ScheduleTimeInput
                                        :id="`override_start_${weekday.value}_${index}`"
                                        :model-value="interval.starts_at"
                                        join-item
                                        :error="!!errors[`dayOverrides.${weekday.value}.${index}.starts_at`]"
                                        :disabled="processing"
                                        :aria-label="`Начало, ${weekday.label}`"
                                        @update:model-value="updateOverrideInterval(weekday.value, index, 'starts_at', $event)"
                                    />
                                    <ScheduleTimeInput
                                        :id="`override_end_${weekday.value}_${index}`"
                                        :model-value="interval.ends_at"
                                        join-item
                                        :error="!!errors[`dayOverrides.${weekday.value}.${index}.ends_at`]"
                                        :disabled="processing"
                                        :aria-label="`Окончание, ${weekday.label}`"
                                        @update:model-value="updateOverrideInterval(weekday.value, index, 'ends_at', $event)"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-circle btn-xs text-base-content/50 hover:bg-error/10 hover:text-error hover:border-error/20"
                                    title="Удалить интервал"
                                    :disabled="processing || dayOverrideIntervals(weekday.value).length <= 1"
                                    @click="onRemoveOverrideInterval(weekday.value, index)"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        class="size-4"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <InputError
                                :message="errors[`dayOverrides.${weekday.value}`] || errors[`dayOverrides.${weekday.value}.0.starts_at`]"
                                class="mt-0"
                            />

                            <button
                                type="button"
                                class="btn btn-outline btn-primary btn-xs gap-1 font-normal"
                                :disabled="processing"
                                @click="onAddOverrideInterval(weekday.value)"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Добавить интервал
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <InputError :message="errors.intervals?.[0] || errors.intervals" />
    </div>
</template>
