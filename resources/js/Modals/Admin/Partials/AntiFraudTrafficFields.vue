<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import NumberInput from '@/Components/NumberInput.vue';
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        required: true,
        validator: (value) => ['primary', 'secondary'].includes(value),
    },
    form: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['addRateLimit', 'removeRateLimit']);

const maxPendingKey = computed(() => `${props.type}_max_pending`);
const rateLimitsKey = computed(() => `${props.type}_rate_limits`);
const failedLimitKey = computed(() => `${props.type}_failed_limit`);
const blockDaysKey = computed(() => `${props.type}_block_days`);

const rateLimits = computed(() => props.form[rateLimitsKey.value] || []);

const addRateLimit = () => {
    emit('addRateLimit', props.type);
};

const removeRateLimit = (index) => {
    emit('removeRateLimit', props.type, index);
};
</script>

<template>
    <div class="space-y-3">
        <div>
            <InputLabel
                value="Макс. активных сделок"
                :error="!!form.errors[maxPendingKey]"
                :hint="type === 'primary'
                    ? 'Сколько pending-сделок может быть одновременно.'
                    : 'Отдельный лимит pending-сделок для вторичного трафика.'"
            />
            <NumberInput
                v-model="form[maxPendingKey]"
                class="mt-1 block w-full"
                min="0"
                :error="!!form.errors[maxPendingKey]"
                :disabled="disabled || form.processing"
            />
            <InputError class="mt-1" :message="form.errors[maxPendingKey]" />
        </div>

        <div>
            <InputLabel
                value="Лимиты по интервалам"
                :error="!!form.errors[rateLimitsKey]"
                hint="Не более N созданных сделок за M минут. Можно задать несколько ограничений, например: 3 / 1м, 10 / 5м, 20 / 60м."
            />
            <div class="mt-1 space-y-1.5">
                <div
                    v-for="(limit, index) in rateLimits"
                    :key="`${type}-${index}`"
                    class="grid grid-cols-[1fr_1fr_auto] items-center gap-2"
                >
                    <NumberInput
                        v-model="limit.count"
                        min="1"
                        placeholder="Кол-во"
                        :disabled="disabled || form.processing"
                    />
                    <NumberInput
                        v-model="limit.minutes"
                        min="1"
                        placeholder="Минут"
                        :disabled="disabled || form.processing"
                    />
                    <button
                        type="button"
                        class="btn btn-sm btn-outline btn-error btn-square shrink-0"
                        :disabled="disabled || form.processing || rateLimits.length <= 1"
                        aria-label="Удалить интервал"
                        @click="removeRateLimit(index)"
                    >
                        ×
                    </button>
                </div>
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    :disabled="disabled || form.processing"
                    @click="addRateLimit"
                >
                    Добавить интервал
                </button>
            </div>
            <InputError class="mt-1" :message="form.errors[rateLimitsKey]" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <InputLabel
                    value="Неуспешных подряд"
                    :error="!!form.errors[failedLimitKey]"
                    :hint="type === 'primary'
                        ? 'После этого числа неуспешных подряд клиент блокируется.'
                        : 'Блокируем только если неуспешные сделки идут подряд.'"
                />
                <NumberInput
                    v-model="form[failedLimitKey]"
                    class="mt-1 block w-full"
                    min="0"
                    :error="!!form.errors[failedLimitKey]"
                    :disabled="disabled || form.processing"
                />
                <InputError class="mt-1" :message="form.errors[failedLimitKey]" />
            </div>
            <div>
                <InputLabel
                    value="Блокировка, дней"
                    :error="!!form.errors[blockDaysKey]"
                    :hint="type === 'primary'
                        ? 'Сколько дней клиент будет заблокирован.'
                        : 'Период блокировки для вторичного трафика.'"
                />
                <NumberInput
                    v-model="form[blockDaysKey]"
                    class="mt-1 block w-full"
                    min="0"
                    :error="!!form.errors[blockDaysKey]"
                    :disabled="disabled || form.processing"
                />
                <InputError class="mt-1" :message="form.errors[blockDaysKey]" />
            </div>
        </div>
    </div>
</template>
