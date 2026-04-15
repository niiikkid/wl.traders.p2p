<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Декоративный фон лендинга: mesh + орбы + конус (в духе Magic MCP / mesh-gradient),
 * только SVG + Tailwind, без WebGL и shader-пакетов.
 */
const reduced_motion = ref(false);

const grain_layer_style = {
    backgroundImage:
        'url("data:image/svg+xml,' +
        encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(#n)"/></svg>',
        ) +
        '")',
    backgroundSize: '160px 160px',
};

onMounted(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    reduced_motion.value = mq.matches;
    const on_change = () => {
        reduced_motion.value = mq.matches;
    };
    mq.addEventListener('change', on_change);
    onUnmounted(() => mq.removeEventListener('change', on_change));
});
</script>

<template>
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden bg-base-100" aria-hidden="true">
        <!-- Статичный mesh из токенов DIM -->
        <div
            class="absolute inset-0"
            style="
                background-image:
                    radial-gradient(ellipse 85% 55% at 50% -25%, color-mix(in oklab, var(--color-accent) 26%, transparent), transparent),
                    radial-gradient(ellipse 60% 45% at 105% 5%, color-mix(in oklab, var(--color-primary) 18%, transparent), transparent),
                    radial-gradient(ellipse 55% 40% at -5% 55%, color-mix(in oklab, var(--color-secondary) 14%, transparent), transparent),
                    radial-gradient(ellipse 45% 35% at 85% 90%, color-mix(in oklab, var(--color-info) 12%, transparent), transparent);
            "
        />

        <!-- Медленный конический «поток» -->
        <div
            v-if="!reduced_motion"
            class="absolute left-1/2 top-[42%] h-[min(140vw,120vh)] w-[min(140vw,120vh)] -translate-x-1/2 -translate-y-1/2 opacity-[0.11] motion-safe:animate-[spin_90s_linear_infinite]"
            style="
                background: conic-gradient(
                    from 0deg at 50% 50%,
                    color-mix(in oklab, var(--color-accent) 55%, transparent),
                    color-mix(in oklab, var(--color-primary) 50%, transparent),
                    color-mix(in oklab, var(--color-info) 45%, transparent),
                    color-mix(in oklab, var(--color-secondary) 50%, transparent),
                    color-mix(in oklab, var(--color-accent) 55%, transparent)
                );
                mask-image: radial-gradient(circle at 50% 50%, black 0%, black 42%, transparent 70%);
            "
        />

        <svg class="absolute inset-0 h-full w-full text-primary opacity-[0.42]" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <filter id="landing-ambient-blur" x="-40%" y="-40%" width="180%" height="180%">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="56" result="blur" />
                    <feColorMatrix
                        in="blur"
                        type="matrix"
                        values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 0.75 0"
                    />
                </filter>
            </defs>
            <g v-if="!reduced_motion" class="text-primary" filter="url(#landing-ambient-blur)">
                <ellipse cx="18%" cy="28%" rx="32%" ry="22%" fill="currentColor" fill-opacity="0.55">
                    <animate attributeName="cx" values="14%;22%;16%;18%" dur="32s" repeatCount="indefinite" />
                    <animate attributeName="cy" values="24%;32%;26%;28%" dur="28s" repeatCount="indefinite" />
                    <animate attributeName="rx" values="28%;34%;30%;32%" dur="40s" repeatCount="indefinite" />
                </ellipse>
            </g>
            <g v-else class="text-primary" filter="url(#landing-ambient-blur)">
                <ellipse cx="18%" cy="28%" rx="30%" ry="22%" fill="currentColor" fill-opacity="0.45" />
            </g>
            <g v-if="!reduced_motion" class="text-accent" filter="url(#landing-ambient-blur)">
                <ellipse cx="78%" cy="22%" rx="28%" ry="20%" fill="currentColor" fill-opacity="0.45">
                    <animate attributeName="cx" values="82%;72%;76%;78%" dur="36s" repeatCount="indefinite" />
                    <animate attributeName="cy" values="18%;28%;24%;22%" dur="30s" repeatCount="indefinite" />
                </ellipse>
            </g>
            <g v-else class="text-accent" filter="url(#landing-ambient-blur)">
                <ellipse cx="78%" cy="22%" rx="26%" ry="20%" fill="currentColor" fill-opacity="0.38" />
            </g>
            <g v-if="!reduced_motion" class="text-secondary" filter="url(#landing-ambient-blur)">
                <ellipse cx="50%" cy="88%" rx="38%" ry="18%" fill="currentColor" fill-opacity="0.35">
                    <animate attributeName="cy" values="92%;84%;90%;88%" dur="44s" repeatCount="indefinite" />
                    <animate attributeName="rx" values="34%;42%;36%;38%" dur="38s" repeatCount="indefinite" />
                </ellipse>
            </g>
            <g v-else class="text-secondary" filter="url(#landing-ambient-blur)">
                <ellipse cx="50%" cy="88%" rx="36%" ry="18%" fill="currentColor" fill-opacity="0.28" />
            </g>
        </svg>

        <!-- Лёгкая зернистость (feTurbulence как в shader-примерах Magic MCP) -->
        <div v-if="!reduced_motion" class="absolute inset-0 opacity-[0.04] mix-blend-overlay" :style="grain_layer_style" />

        <div
            class="absolute inset-0 opacity-[0.06] motion-reduce:opacity-[0.03]"
            style="
                background-image:
                    linear-gradient(color-mix(in oklab, var(--color-base-content) 35%, transparent) 1px, transparent 1px),
                    linear-gradient(90deg, color-mix(in oklab, var(--color-base-content) 35%, transparent) 1px, transparent 1px);
                background-size: 48px 48px;
            "
        />

        <div
            class="absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-base-100 via-base-100/80 to-transparent motion-reduce:via-base-100/90"
        />
    </div>
</template>
