<script setup>
import { ref, watch } from 'vue';
import { useDropdown } from '@/composables/useDropdown.js';

const props = defineProps({
    preset: {
        type: String,
        required: true,
    },
    dateFrom: {
        type: String,
        default: '',
    },
    dateTo: {
        type: String,
        default: '',
    },
    presetOptions: {
        type: Array,
        default: () => [
            { value: 'today', label: 'Сегодня' },
            { value: 'week', label: 'Неделя' },
            { value: 'month', label: 'Месяц' },
            { value: 'all', label: 'Все' },
        ],
    },
});

const emit = defineEmits(['select-preset', 'update:dateFrom', 'update:dateTo']);

const desktopCustom = useDropdown();
const mobilePresets = useDropdown();
const mobileCustom = useDropdown();
const mobileCustomPanel = ref(null);
const mobileCustomLeft = ref(null);

const positionMobileCustom = () => {
    if (window.innerWidth >= 768) {
        mobileCustom.close();
        return;
    }

    const dropdown = mobileCustom.el.value;
    const panel = mobileCustomPanel.value;

    if (!mobileCustom.isOpen.value || !dropdown || !panel) {
        return;
    }

    const padding = 8;
    const viewportWidth = document.documentElement.clientWidth;
    const triggerRect = dropdown.getBoundingClientRect();
    const panelWidth = panel.offsetWidth;
    const centeredLeft = triggerRect.left + (triggerRect.width - panelWidth) / 2;
    const left = Math.max(padding, Math.min(centeredLeft, viewportWidth - panelWidth - padding));

    mobileCustomLeft.value = `${left - triggerRect.left - dropdown.clientLeft}px`;
};

watch(mobileCustom.isOpen, (isOpen, wasOpen, onCleanup) => {
    if (!isOpen) {
        mobileCustomLeft.value = null;
        return;
    }

    positionMobileCustom();
    window.addEventListener('scroll', positionMobileCustom, true);
    window.addEventListener('resize', positionMobileCustom);

    onCleanup(() => {
        window.removeEventListener('scroll', positionMobileCustom, true);
        window.removeEventListener('resize', positionMobileCustom);
    });
}, { flush: 'post' });

const selectPreset = (value) => {
    emit('select-preset', value);
};

const openCustom = (dropdown) => {
    emit('select-preset', 'custom');
    dropdown.toggle();
};

const selectMobilePreset = (value) => {
    selectPreset(value);
    mobilePresets.close();
};
</script>

<template>
    <div class="flex items-start gap-2">
        <div class="hidden md:join md:join-horizontal md:flex md:flex-wrap">
            <button
                v-for="option in presetOptions.filter((item) => item.value !== 'all')"
                :key="`desktop-preset-${option.value}`"
                type="button"
                class="btn btn-sm join-item"
                :class="preset === option.value ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                @click="selectPreset(option.value)"
            >
                {{ option.label }}
            </button>

            <div :ref="desktopCustom.el" class="dropdown dropdown-end" :class="{ 'dropdown-open': desktopCustom.isOpen.value }">
                <button
                    type="button"
                    class="btn btn-sm join-item"
                    :class="preset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                    @click.stop="openCustom(desktopCustom)"
                >
                    Свой период
                </button>
                <div
                    v-if="desktopCustom.isOpen.value"
                    class="absolute z-30 mt-2 w-72 rounded-box border border-base-300 bg-base-100 p-3 shadow right-0 left-auto"
                >
                    <div class="flex items-center gap-2">
                        <input
                            :value="dateFrom"
                            type="date"
                            class="input input-bordered input-sm w-full"
                            @input="emit('update:dateFrom', $event.target.value)"
                        >
                        <span class="text-sm text-base-content/60">—</span>
                        <input
                            :value="dateTo"
                            type="date"
                            class="input input-bordered input-sm w-full"
                            @input="emit('update:dateTo', $event.target.value)"
                        >
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="btn btn-ghost btn-sm" @click="desktopCustom.close()">
                            Закрыть
                        </button>
                    </div>
                </div>
            </div>

            <button
                type="button"
                class="btn btn-sm join-item"
                :class="preset === 'all' ? 'btn-active btn-primary' : 'bg-base-100 border-transparent'"
                @click="selectPreset('all')"
            >
                Все
            </button>
        </div>

        <div class="flex md:hidden items-start gap-2">
            <div :ref="mobilePresets.el" class="dropdown" :class="{ 'dropdown-open': mobilePresets.isOpen.value }">
                <button
                    type="button"
                    class="btn btn-sm bg-base-100 border-transparent"
                    @click.stop="mobilePresets.toggle()"
                >
                    Период
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                    </svg>
                </button>
                <ul class="dropdown-content z-30 mt-2 menu w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                    <li v-for="option in presetOptions" :key="`mobile-preset-${option.value}`">
                        <button
                            type="button"
                            :class="preset === option.value ? 'menu-active' : ''"
                            @click="selectMobilePreset(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </li>
                </ul>
            </div>

            <div :ref="mobileCustom.el" class="dropdown" :class="{ 'dropdown-open': mobileCustom.isOpen.value }">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="preset === 'custom' ? 'btn-primary' : 'bg-base-100 border-transparent'"
                    @click.stop="openCustom(mobileCustom)"
                >
                    Свой период
                </button>
                <div
                    v-if="mobileCustom.isOpen.value"
                    ref="mobileCustomPanel"
                    class="absolute z-30 mt-2 w-72 max-w-[calc(100vw-1rem)] rounded-box border border-base-300 bg-base-100 p-3 shadow left-0"
                    :style="{ left: mobileCustomLeft }"
                >
                    <div class="flex items-center gap-2">
                        <input
                            :value="dateFrom"
                            type="date"
                            class="input input-bordered input-sm min-w-0 flex-1"
                            @input="emit('update:dateFrom', $event.target.value)"
                        >
                        <span class="shrink-0 text-sm text-base-content/60">—</span>
                        <input
                            :value="dateTo"
                            type="date"
                            class="input input-bordered input-sm min-w-0 flex-1"
                            @input="emit('update:dateTo', $event.target.value)"
                        >
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="btn btn-ghost btn-sm" @click="mobileCustom.close()">
                            Закрыть
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
