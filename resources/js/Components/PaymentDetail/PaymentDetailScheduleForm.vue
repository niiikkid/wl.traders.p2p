<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
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
    <div class="space-y-5">
        <div
            v-if="showSharedEditWarning"
            class="alert alert-warning text-sm py-2"
        >
            Изменения применятся ко всем реквизитам, где используется это расписание
            <span v-if="attachedCount"> ({{ attachedCount }})</span>.
        </div>

        <p class="text-xs text-base-content/70">
            Время интервалов указывается по времени сервера.
            <span v-if="formatServerNow">
                Сейчас на сервере: {{ formatServerNow }}
                <span v-if="serverTimezone">({{ serverTimezone }})</span>.
            </span>
        </p>

        <div>
            <InputLabel
                for="schedule_editor_name"
                value="Название"
                :error="!!errors.name?.[0] || !!errors.name"
                class="mb-1"
            />
            <TextInput
                id="schedule_editor_name"
                v-model="editorState.name"
                type="text"
                class="w-full"
                :class="{ 'input-error': !!errors.name?.[0] || !!errors.name }"
                autocomplete="off"
                :disabled="processing"
            />
            <InputError :message="errors.name?.[0] || errors.name" class="mt-2" />
        </div>

        <div class="space-y-3">
            <InputLabel
                value="Рабочие дни (общее расписание)"
                :error="!!errors.defaultDays"
                class="mb-1"
            />
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="weekday in WEEKDAY_OPTIONS"
                    :key="weekday.value"
                    type="button"
                    class="btn btn-sm"
                    :class="isDefaultDaySelected(weekday.value) ? 'btn-primary' : 'btn-outline'"
                    :disabled="processing || isDayOverrideEnabled(weekday.value)"
                    @click="onToggleDefaultDay(weekday.value)"
                >
                    {{ weekday.label }}
                </button>
            </div>
            <p v-if="hasAnyDayOverride" class="text-xs text-base-content/60">
                Дни с отдельным расписанием настраиваются ниже и не участвуют в общем режиме.
            </p>
            <InputError :message="errors.defaultDays" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel
                    for="schedule_default_start"
                    value="Начало (общее)"
                    :error="!!errors.defaultStart"
                    class="mb-1"
                />
                <TextInput
                    id="schedule_default_start"
                    v-model="editorState.defaultStart"
                    type="text"
                    placeholder="09:00"
                    class="w-full"
                    :class="{ 'input-error': !!errors.defaultStart }"
                    :disabled="processing"
                />
                <InputError :message="errors.defaultStart" class="mt-2" />
            </div>
            <div>
                <InputLabel
                    for="schedule_default_end"
                    value="Окончание (общее)"
                    :error="!!errors.defaultEnd"
                    class="mb-1"
                />
                <TextInput
                    id="schedule_default_end"
                    v-model="editorState.defaultEnd"
                    type="text"
                    placeholder="19:00"
                    class="w-full"
                    :class="{ 'input-error': !!errors.defaultEnd }"
                    :disabled="processing"
                />
                <InputError :message="errors.defaultEnd" class="mt-2" />
            </div>
        </div>

        <div class="space-y-3">
            <div class="text-sm font-medium">
                Отдельное расписание по дням
            </div>
            <p class="text-xs text-base-content/70">
                Переопределение заменяет общий интервал для выбранного дня.
            </p>

            <div
                v-for="weekday in WEEKDAY_OPTIONS"
                :key="`override-${weekday.value}`"
                class="rounded-box border border-base-300 p-3 space-y-3"
            >
                <label class="label cursor-pointer justify-start gap-3 p-0">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm"
                        :checked="isDayOverrideEnabled(weekday.value)"
                        :disabled="processing"
                        @change="onToggleOverride(weekday.value, $event.target.checked)"
                    />
                    <span class="label-text">{{ weekday.label }} — своё расписание</span>
                </label>

                <template v-if="isDayOverrideEnabled(weekday.value)">
                    <div
                        v-for="(interval, index) in dayOverrideIntervals(weekday.value)"
                        :key="`${weekday.value}-${index}`"
                        class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                        :class="{ 'sm:items-end': true }"
                    >
                        <div>
                            <InputLabel
                                :for="`override_start_${weekday.value}_${index}`"
                                value="Начало"
                                class="mb-1"
                            />
                            <TextInput
                                :id="`override_start_${weekday.value}_${index}`"
                                :model-value="interval.starts_at"
                                type="text"
                                placeholder="09:00"
                                class="w-full"
                                :disabled="processing"
                                @update:model-value="updateOverrideInterval(weekday.value, index, 'starts_at', $event)"
                            />
                        </div>
                        <div>
                            <InputLabel
                                :for="`override_end_${weekday.value}_${index}`"
                                value="Окончание"
                                class="mb-1"
                            />
                            <TextInput
                                :id="`override_end_${weekday.value}_${index}`"
                                :model-value="interval.ends_at"
                                type="text"
                                placeholder="19:00"
                                class="w-full"
                                :disabled="processing"
                                @update:model-value="updateOverrideInterval(weekday.value, index, 'ends_at', $event)"
                            />
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-ghost btn-error"
                            :disabled="processing || dayOverrideIntervals(weekday.value).length <= 1"
                            @click="onRemoveOverrideInterval(weekday.value, index)"
                        >
                            Удалить
                        </button>
                    </div>
                    <InputError
                        :message="errors[`dayOverrides.${weekday.value}`] || errors[`dayOverrides.${weekday.value}.0.starts_at`]"
                        class="mt-1"
                    />
                    <button
                        type="button"
                        class="btn btn-xs btn-outline"
                        :disabled="processing"
                        @click="onAddOverrideInterval(weekday.value)"
                    >
                        Добавить интервал
                    </button>
                </template>
            </div>
        </div>

        <InputError :message="errors.intervals?.[0] || errors.intervals" />
    </div>
</template>
