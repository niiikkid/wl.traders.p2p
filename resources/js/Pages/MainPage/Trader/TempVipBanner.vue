<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CountdownTimer from '@/Components/CountdownTimer.vue';

const props = defineProps({
    tempVip: {
        type: Object,
        required: true,
    },
});

const form = useForm({});

const progressPercent = computed(() => {
    const required = props.tempVip?.required ?? 0;
    const count = props.tempVip?.count ?? 0;
    if (!required) return 0;
    return Math.min(Math.round((count / required) * 100), 100);
});

const canActivate = computed(() => !!(props.tempVip?.can_activate || props.tempVip?.temp_vip_can_activate));

const activate = () => {
    if (!canActivate.value || form.processing) return;

    form.post(route('trader.temp-vip.activate'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div
        v-if="tempVip?.enabled"
        class="card bg-base-100 text-base-content shadow w-full sm:w-fit relative z-40"
    >
        <div class="card-body gap-2 py-3 sm:py-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-xs sm:text-sm inline-flex items-center gap-1">
                    <span>Временный VIP</span>
                    <div class="dropdown dropdown-hover relative z-50">
                        <div
                            tabindex="0"
                            role="button"
                            class="btn btn-ghost btn-xs btn-circle text-base-content/70 hover:text-base-content"
                            aria-label="Информация о временном VIP"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                        </div>
                        <div
                            tabindex="0"
                            class="dropdown-content z-[60] card card-compact p-3 shadow bg-base-100 text-base-content border border-base-300 w-64 max-w-[80vw] left-1/2 -translate-x-1/2"
                        >
                            <div class="text-xs leading-relaxed">
                                Прогресс показывает, сколько успешных сделок нужно выполнить.
                                <span class="font-semibold">Когда норма будет выполнена</span> — можно включить временный VIP и получить расширенные лимиты на время.
                            </div>
                        </div>
                    </div>
                </span>

                <template v-if="tempVip.active">
                    <div class="inline-flex items-center gap-2">
                        <span class="badge badge-sm badge-success badge-outline">
                        Активен
                        </span>
                            <span class="flex items-center gap-1">
                            <CountdownTimer :end="tempVip.active_until" :muted="true" />
                        </span>
                    </div>
                </template>

                <template v-else>
                    <span class="font-semibold text-xs sm:text-sm">{{ tempVip.count }} / {{ tempVip.required }}</span>
                    <progress class="progress progress-primary w-35 h-2" :value="progressPercent" max="100"></progress>
                    <span v-if="canActivate" class="badge badge-sm badge-primary badge-outline">
                        Готов к включению
                    </span>
                    <button
                        type="button"
                        class="btn btn-primary btn-xs"
                        :class="{ 'btn-disabled': !canActivate || form.processing }"
                        :disabled="!canActivate || form.processing"
                        @click="activate"
                    >
                        Включить
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>

