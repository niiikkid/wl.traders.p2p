<script setup>
import {ref, onMounted} from "vue";

// Полный список тем DaisyUI + дополнительные
const themes = [
    'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate', 'synthwave', 'retro', 'cyberpunk',
    'valentine', 'halloween', 'garden', 'forest', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'black',
    'luxury', 'dracula', 'cmyk', 'autumn', 'business', 'acid', 'lemonade', 'night', 'coffee', 'winter',
    'dim', 'nord', 'sunset', 'caramellatte', 'abyss', 'silk'
];

const storedTheme = typeof window !== 'undefined' ? window.localStorage.getItem('theme') : null;
const initialTheme = storedTheme || document.querySelector('html')?.getAttribute('data-theme');
const currentTheme = ref(initialTheme);

const applyTheme = (theme) => {
    if (!themes.includes(theme)) return;
    const html = document.querySelector('html');
    if (html) {
        html.setAttribute('data-theme', theme);
        currentTheme.value = theme;
        try {
            window.localStorage.setItem('theme', theme);
        } catch (e) {
            // ignore storage errors
        }
    }
};

onMounted(() => {
    // При монтировании синхронизируем тему из localStorage с атрибутом html
    const saved = typeof window !== 'undefined' ? window.localStorage.getItem('theme') : null;
    const themeToApply = saved && themes.includes(saved) ? saved : currentTheme.value;
    if (themeToApply) {
        const html = document.querySelector('html');
        if (html) {
            html.setAttribute('data-theme', themeToApply);
        }
        currentTheme.value = themeToApply;
    }
});
</script>

<template>
    <div class="w-full">
        <div class="grid grid-cols-3 gap-2 h-60 overflow-y-scroll overflow-x-hidden py-2">
            <div
                class="text-center cursor-pointer"
                v-for="t in themes"
                @click="applyTheme(t)"
            >
                <button
                    :key="t"
                    type="button"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-base-300 cursor-pointer hover:outline hover:outline-primary"
                    :class="currentTheme === t ? 'outline outline-primary' : 'outline outline-base-300'"
                    :title="t"
                    :aria-label="'Тема ' + t"
                    :data-theme="t"
                >
                    <div class="grid grid-cols-2 gap-0.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-primary cursor-pointer"></span>
                        <span class="w-2 h-2 rounded-full bg-secondary cursor-pointer"></span>
                        <span class="w-2 h-2 rounded-full bg-accent cursor-pointer"></span>
                        <span class="w-2 h-2 rounded-full bg-neutral cursor-pointer"></span>
                    </div>
                </button>
                <div class="text-base-content/70 text-xs">{{t}}</div>
            </div>
        </div>
    </div>

</template>

<style scoped>

</style>


