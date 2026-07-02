<script setup>
import { computed, ref } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';
import {
    CHROMATIC_FAMILIES,
    NEUTRAL_FAMILIES,
    colorToHex,
    formatOklch,
    hexToOklchString,
    parseColorToRgb,
    parseOklch,
    tailwindFamilyPalette,
} from '../lib/color.js';
import { TOKEN_ALIASES } from '../lib/theme-schema.js';
import { CONTRAST_PAIRS, contrastBadgeClass, contrastLevel, contrastRatio } from '../lib/theme-contrast.js';

const props = defineProps({
    token: { type: String, required: true },
    label: { type: String, default: '' },
});

const store = useThemeGeneratorStore();

const open = ref(false);
const mode = ref('oklch');
const paletteFamily = ref('blue');
const rawText = ref('');

const PALETTE_FAMILIES = [...NEUTRAL_FAMILIES, ...CHROMATIC_FAMILIES];

const value = computed(() => store.draftTokens[props.token] ?? 'oklch(0% 0 0)');

const swatchStyle = computed(() => ({ backgroundColor: value.value }));

const alias = computed(() => TOKEN_ALIASES[props.token] ?? '');

const okl = computed(() => parseOklch(value.value));
const hex = computed(() => colorToHex(value.value));

const contrastInfo = computed(() => {
    const pair = CONTRAST_PAIRS.find((p) => p.content === props.token || p.surface === props.token);

    if (!pair) {
        return null;
    }

    const ratio = contrastRatio(store.draftTokens[pair.content], store.draftTokens[pair.surface]);
    const level = contrastLevel(ratio);

    return { ratio: Math.round(ratio * 100) / 100, level, badge: contrastBadgeClass(level) };
});

const palette = computed(() => tailwindFamilyPalette(paletteFamily.value));

const setOklch = (part, next) => {
    const current = parseOklch(value.value);
    const updated = { ...current, [part]: Number(next) };
    store.updateToken(props.token, formatOklch(updated));
};

const setHex = (event) => {
    store.updateToken(props.token, hexToOklchString(event.target.value));
};

const commitRawText = () => {
    const text = rawText.value.trim();

    if (text && parseColorToRgb(text)) {
        store.updateToken(props.token, text);
    }

    rawText.value = '';
};

const applyPaletteColor = (color) => {
    store.updateToken(props.token, color);
};

const togglePicker = () => {
    open.value = !open.value;

    if (open.value) {
        rawText.value = value.value;
    }
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-field border border-base-content/15 bg-base-100 px-2 py-1.5 text-left transition hover:border-base-content/40"
            @click="togglePicker"
        >
            <span
                class="inline-block size-6 shrink-0 rounded border border-base-content/20"
                :style="swatchStyle"
            ></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-xs font-medium">{{ label || token }}</span>
                <span class="block truncate text-[10px] opacity-60">{{ value }}</span>
            </span>
            <span v-if="alias" class="badge badge-ghost badge-xs font-mono">{{ alias }}</span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-30 mt-1 w-72 rounded-box border border-base-content/15 bg-base-100 p-3 shadow-xl"
        >
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase opacity-60">{{ label || token }}</span>
                <span
                    v-if="contrastInfo"
                    class="badge badge-sm gap-1"
                    :class="contrastInfo.badge"
                >
                    {{ contrastInfo.level }} · {{ contrastInfo.ratio }}
                </span>
            </div>

            <div class="tabs tabs-box tabs-xs mb-3">
                <button type="button" class="tab" :class="{ 'tab-active': mode === 'oklch' }" @click="mode = 'oklch'">OKLCH</button>
                <button type="button" class="tab" :class="{ 'tab-active': mode === 'hex' }" @click="mode = 'hex'">HEX</button>
                <button type="button" class="tab" :class="{ 'tab-active': mode === 'palette' }" @click="mode = 'palette'">Палитра</button>
            </div>

            <div v-if="mode === 'oklch'" class="space-y-3">
                <label class="block">
                    <span class="flex justify-between text-[11px] opacity-70"><span>Lightness</span><span>{{ Math.round(okl.l * 100) }}%</span></span>
                    <input type="range" min="0" max="1" step="0.005" class="range range-xs range-primary" :value="okl.l" @input="setOklch('l', $event.target.value)" />
                </label>
                <label class="block">
                    <span class="flex justify-between text-[11px] opacity-70"><span>Chroma</span><span>{{ okl.c.toFixed(3) }}</span></span>
                    <input type="range" min="0" max="0.37" step="0.001" class="range range-xs range-secondary" :value="okl.c" @input="setOklch('c', $event.target.value)" />
                </label>
                <label class="block">
                    <span class="flex justify-between text-[11px] opacity-70"><span>Hue</span><span>{{ Math.round(okl.h) }}°</span></span>
                    <input type="range" min="0" max="360" step="1" class="range range-xs range-accent" :value="okl.h" @input="setOklch('h', $event.target.value)" />
                </label>
            </div>

            <div v-else-if="mode === 'hex'" class="space-y-3">
                <div class="flex items-center gap-2">
                    <input type="color" class="h-10 w-14 cursor-pointer rounded border border-base-content/20 bg-base-100" :value="hex" @input="setHex" />
                    <input
                        type="text"
                        class="input input-sm input-bordered flex-1 font-mono text-xs"
                        placeholder="oklch(...) / #hex / rgb(...)"
                        v-model="rawText"
                        @keyup.enter="commitRawText"
                        @blur="commitRawText"
                    />
                </div>
                <p class="text-[11px] opacity-60">Введите любой безопасный CSS-цвет и нажмите Enter.</p>
            </div>

            <div v-else class="space-y-2">
                <select v-model="paletteFamily" class="select select-sm select-bordered w-full text-xs">
                    <option v-for="family in PALETTE_FAMILIES" :key="family" :value="family">{{ family }}</option>
                </select>
                <div class="grid grid-cols-11 gap-1">
                    <button
                        v-for="entry in palette"
                        :key="entry.shade"
                        type="button"
                        class="size-5 rounded border border-base-content/10 transition hover:scale-110"
                        :style="{ backgroundColor: entry.color }"
                        :title="`${paletteFamily}-${entry.shade}`"
                        @click="applyPaletteColor(entry.color)"
                    ></button>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" class="btn btn-ghost btn-xs" @click="open = false">Готово</button>
            </div>
        </div>
    </div>
</template>
