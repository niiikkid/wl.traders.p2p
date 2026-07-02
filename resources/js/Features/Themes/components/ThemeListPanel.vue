<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';

const store = useThemeGeneratorStore();

const HOLD_MS = 1200;

const holdProgress = ref(0);
const holdTimer = ref(null);
const holdStart = ref(0);

const customThemes = computed(() => store.custom);
const builtinThemes = computed(() => store.builtin);

const swatches = (theme) => [
    theme.tokens['--color-primary'],
    theme.tokens['--color-secondary'],
    theme.tokens['--color-accent'],
    theme.tokens['--color-neutral'],
];

const isActive = (theme) => store.activeThemeId === theme.id
    || (store.draft?.slug === theme.slug && store.draft?.type === theme.type);

const cancelHold = () => {
    if (holdTimer.value) {
        cancelAnimationFrame(holdTimer.value);
        holdTimer.value = null;
    }

    holdProgress.value = 0;
};

const tickHold = () => {
    const elapsed = Date.now() - holdStart.value;
    holdProgress.value = Math.min(100, (elapsed / HOLD_MS) * 100);

    if (elapsed >= HOLD_MS) {
        cancelHold();
        store.createRandomTheme();

        return;
    }

    holdTimer.value = requestAnimationFrame(tickHold);
};

const startHold = () => {
    cancelHold();
    holdStart.value = Date.now();
    holdTimer.value = requestAnimationFrame(tickHold);
};

onBeforeUnmount(cancelHold);
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between px-3 py-2">
            <span class="text-xs font-semibold uppercase tracking-wide opacity-60">Темы</span>
            <div class="dropdown dropdown-end">
                <button type="button" tabindex="0" class="btn btn-ghost btn-xs btn-square" aria-label="Опции">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="5" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="12" cy="19" r="1.5" />
                    </svg>
                </button>
                <ul tabindex="0" class="menu dropdown-content z-40 mt-1 w-56 rounded-box bg-base-100 p-2 shadow-lg">
                    <li><button type="button" @click="store.removeAllCustomThemes()">Удалить мои темы</button></li>
                    <li><button type="button" @click="store.resetToBaseTheme()">Сбросить к теме «dim»</button></li>
                </ul>
            </div>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
            <button
                type="button"
                class="relative w-full overflow-hidden rounded-box border border-dashed border-base-content/30 py-3 text-sm font-medium transition hover:border-primary hover:text-primary select-none"
                @pointerdown="startHold"
                @pointerup="cancelHold"
                @pointerleave="cancelHold"
                @pointercancel="cancelHold"
                @blur="cancelHold"
            >
                <span
                    class="absolute inset-y-0 left-0 bg-primary/20 transition-none"
                    :style="{ width: `${holdProgress}%` }"
                ></span>
                <span class="relative">+ Удерживайте для случайной темы</span>
            </button>

            <section v-if="customThemes.length">
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Мои темы</h4>
                <ul class="space-y-1">
                    <li v-for="theme in customThemes" :key="theme.id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-field px-2 py-1.5 text-left transition hover:bg-base-200"
                            :class="{ 'bg-base-200 ring-1 ring-primary/40': isActive(theme) }"
                            @click="store.selectTheme(theme.id)"
                        >
                            <span class="flex shrink-0">
                                <span v-for="(color, i) in swatches(theme)" :key="i" class="size-3.5 rounded-full border border-base-content/10 -ml-1 first:ml-0" :style="{ backgroundColor: color }"></span>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ theme.name }}</span>
                            <span v-if="theme.status === 'published'" class="badge badge-success badge-xs">публик.</span>
                            <span v-else class="badge badge-ghost badge-xs">черн.</span>
                        </button>
                    </li>
                </ul>
            </section>

            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Темы daisyUI</h4>
                <ul class="space-y-1">
                    <li v-for="theme in builtinThemes" :key="theme.id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-field px-2 py-1.5 text-left transition hover:bg-base-200"
                            :class="{ 'bg-base-200 ring-1 ring-primary/40': isActive(theme) }"
                            @click="store.selectTheme(theme.id)"
                        >
                            <span class="flex shrink-0">
                                <span v-for="(color, i) in swatches(theme)" :key="i" class="size-3.5 rounded-full border border-base-content/10 -ml-1 first:ml-0" :style="{ backgroundColor: color }"></span>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm capitalize">{{ theme.name }}</span>
                            <span v-if="theme.colorScheme === 'dark'" class="badge badge-neutral badge-xs">dark</span>
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
