<script setup>
import {router} from "@inertiajs/vue3";
import {computed, reactive, ref} from "vue";

const props = defineProps({
    demo: {
        type: Object,
        default: () => ({})
    }
});

const presets = computed(() => props.demo?.options?.presets ?? []);

const form = reactive({
    preset: props.demo?.query?.preset ?? 'pending',
    stage: props.demo?.query?.stage ?? 'payment',
    detail_type: props.demo?.query?.detail_type ?? 'card',
    selected_gateway: props.demo?.query?.selected_gateway ?? 'sber',
    manually: Number(props.demo?.query?.manually ?? 0) === 1,
    expires_in: Number(props.demo?.query?.expires_in ?? 20),
});

const groupedPresets = computed(() => {
    return {
        status: presets.value.filter((preset) => preset.group === 'status'),
        details: presets.value.filter((preset) => preset.group === 'details'),
    };
});

const currentPreset = computed(() => {
    return presets.value.find((preset) => preset.value === form.preset) ?? null;
});

const showDetailsPresets = computed(() => {
    return form.stage === 'payment' && !form.manually;
});

const presetsOpen = ref(false);

const applyPreset = (preset) => {
    form.preset = preset.value;
    form.stage = preset.query.stage;
    form.detail_type = preset.query.detail_type;
    form.selected_gateway = preset.query.selected_gateway;
    form.manually = Number(preset.query.manually) === 1;
    form.expires_in = Number(preset.query.expires_in);

    router.get(route('payment.demo.show'), preset.query, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <div class="card bg-base-100 shadow-sm border border-base-300">
        <div class="card-body sm:px-5 px-3 py-3">
            <div class="flex items-center justify-between mb-2 gap-2">
                <div class="font-semibold text-base-content">Демо-пресеты</div>
                <div class="flex items-center gap-2">
                    <div class="badge badge-primary badge-outline">DEMO</div>
                    <div class="badge badge-ghost text-xs">{{ currentPreset?.name ?? 'Кастом' }}</div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="text-xs text-base-content/60">
                    Быстрый выбор сценария без ручного переключения параметров
                </div>
                <button
                    type="button"
                    class="btn btn-xs btn-outline"
                    @click="presetsOpen = !presetsOpen"
                >
                    {{ presetsOpen ? 'Скрыть пресеты' : 'Показать пресеты' }}
                </button>
            </div>

            <div v-show="presetsOpen" class="space-y-3 mt-3">
                <div>
                    <div class="text-xs font-medium text-base-content/70 mb-2">Статусы</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in groupedPresets.status"
                            :key="preset.value"
                            type="button"
                            class="btn btn-xs sm:btn-sm"
                            :class="form.preset === preset.value ? 'btn-primary' : 'btn-outline'"
                            :title="preset.description"
                            @click="applyPreset(preset)"
                        >
                            {{ preset.name }}
                        </button>
                    </div>
                </div>

                <div v-if="showDetailsPresets">
                    <div class="text-xs font-medium text-base-content/70 mb-2">Витрина реквизитов</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in groupedPresets.details"
                            :key="preset.value"
                            type="button"
                            class="btn btn-xs sm:btn-sm"
                            :class="form.preset === preset.value ? 'btn-primary' : 'btn-outline'"
                            :title="preset.description"
                            @click="applyPreset(preset)"
                        >
                            {{ preset.name }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
