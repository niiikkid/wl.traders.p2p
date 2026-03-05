<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref} from "vue";

const themes = [
    'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate', 'synthwave', 'retro', 'cyberpunk',
    'valentine', 'halloween', 'garden', 'forest', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'black',
    'luxury', 'dracula', 'cmyk', 'autumn', 'business', 'acid', 'lemonade', 'night', 'coffee', 'winter',
    'dim', 'nord', 'sunset', 'caramellatte', 'abyss', 'silk'
];

const marqueeThemes = computed(() => [...themes, ...themes]);

const defaultTheme = 'light';
const storedTheme = typeof window !== 'undefined'
    ? (window.localStorage.getItem('color-theme-payment') || window.localStorage.getItem('theme'))
    : null;

const currentTheme = ref(storedTheme && themes.includes(storedTheme) ? storedTheme : defaultTheme);
const translateX = ref(0);
const baseSpeed = 0.6;
const hoverSpeed = baseSpeed / 2;
const currentSpeed = ref(baseSpeed);
const trackRef = ref(null);
let animationFrameId = null;

const persistTheme = (theme) => {
    try {
        window.localStorage.setItem('color-theme-payment', theme);
        window.localStorage.setItem('theme', theme);
    } catch (error) {
        // ignore storage access issues
    }
};

const updateHtmlTheme = (theme) => {
    if (typeof document === 'undefined') {
        return;
    }

    const html = document.documentElement;
    html.setAttribute('data-theme', theme);

    if (theme === 'dark') {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
};

const applyTheme = (theme) => {
    if (!themes.includes(theme)) {
        return;
    }

    currentTheme.value = theme;
    updateHtmlTheme(theme);

    if (typeof window !== 'undefined') {
        persistTheme(theme);
    }
};

const selectTheme = (theme) => {
    applyTheme(theme);
};

const hydrateTheme = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const savedTheme = window.localStorage.getItem('color-theme-payment') || window.localStorage.getItem('theme');
    if (savedTheme && themes.includes(savedTheme)) {
        applyTheme(savedTheme);
        return;
    }

    const prefersDark = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)').matches
        : false;
    applyTheme(prefersDark ? 'dark' : currentTheme.value);
};

const animate = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const track = trackRef.value;
    const singleTrackWidth = track ? track.scrollWidth / 2 : 0;

    if (track && singleTrackWidth > 0) {
        translateX.value -= currentSpeed.value;

        if (Math.abs(translateX.value) >= singleTrackWidth) {
            translateX.value = 0;
        }
    }

    animationFrameId = window.requestAnimationFrame(animate);
};

onMounted(() => {
    hydrateTheme();

    nextTick(() => {
        if (animationFrameId) {
            window.cancelAnimationFrame(animationFrameId);
        }

        animationFrameId = window.requestAnimationFrame(animate);
    });
});

onBeforeUnmount(() => {
    if (animationFrameId) {
        window.cancelAnimationFrame(animationFrameId);
    }
});

const handleMouseEnter = () => {
    currentSpeed.value = hoverSpeed;
};

const handleMouseLeave = () => {
    currentSpeed.value = baseSpeed;
};
</script>

<template>
    <div class="w-full bg-base-100 border-b border-base-200 z-50">
        <div class="overflow-hidden px-4 py-3">
            <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-base-content/70">
                <span class="font-semibold text-base-content">Демо</span>
                <div class="relative flex-1 overflow-hidden">
                    <div
                        ref="trackRef"
                        class="flex items-center gap-2 will-change-transform"
                        :style="{ transform: `translateX(${translateX}px)` }"
                        @mouseenter="handleMouseEnter"
                        @mouseleave="handleMouseLeave"
                    >
                        <button
                            v-for="(theme, index) in marqueeThemes"
                            :key="`${theme}-${index}`"
                            type="button"
                            class="btn btn-ghost bg-base-200 btn-xs rounded-full border border-base-300 capitalize transition-colors"
                            :class="currentTheme === theme ? 'btn-primary text-primary-content border-primary' : 'hover:border-primary hover:text-primary'"
                            @click.stop="selectTheme(theme)"
                            :data-theme="theme"
                        >
                            <span class="mr-2 flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-secondary"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-accent"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-neutral"></span>
                            </span>
                            <span class="truncate text-base-content">{{ theme }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

