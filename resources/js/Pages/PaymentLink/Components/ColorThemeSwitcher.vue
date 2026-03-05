<script setup>
import {onBeforeUnmount, onMounted, ref} from "vue";

const themes = [
    'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate', 'synthwave', 'retro', 'cyberpunk',
    'valentine', 'halloween', 'garden', 'forest', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'black',
    'luxury', 'dracula', 'cmyk', 'autumn', 'business', 'acid', 'lemonade', 'night', 'coffee', 'winter',
    'dim', 'nord', 'sunset', 'caramellatte', 'abyss', 'silk'
];

const dropdownRef = ref(null);
const isDropdownOpen = ref(false);
const defaultTheme = 'light';
const storedTheme = typeof window !== 'undefined'
    ? window.localStorage.getItem('color-theme-payment')
    : null;

const currentTheme = ref(storedTheme && themes.includes(storedTheme) ? storedTheme : defaultTheme);

const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
};

const closeDropdown = () => {
    isDropdownOpen.value = false;
};

const persistTheme = (theme) => {
    try {
        window.localStorage.setItem('color-theme-payment', theme);
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
    closeDropdown();
};

const hydrateTheme = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const savedTheme = window.localStorage.getItem('color-theme-payment');
    if (savedTheme && themes.includes(savedTheme)) {
        applyTheme(savedTheme);
        return;
    }

    const prefersDark = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-color-scheme: dark)').matches
        : false;
    applyTheme(prefersDark ? 'dark' : currentTheme.value);
};

const handleClickOutside = (event) => {
    if (!dropdownRef.value) {
        return;
    }

    if (!dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        closeDropdown();
    }
};

onMounted(() => {
    hydrateTheme();
    if (typeof document !== 'undefined') {
        document.addEventListener('click', handleClickOutside);
        document.addEventListener('keydown', handleKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('click', handleClickOutside);
        document.removeEventListener('keydown', handleKeydown);
    }
});
</script>

<template>
    <div
        class="dropdown dropdown-center dropdown-top w-auto"
        :class="{'dropdown-open': isDropdownOpen}"
        ref="dropdownRef"
    >
        <button
            type="button"
            class="btn btn-ghost btn-outline w-full sm:w-auto justify-between gap-2"
            @click.prevent="toggleDropdown"
            :aria-expanded="isDropdownOpen"
            aria-haspopup="listbox"
        >
            <span class="flex items-center gap-2">
                <span class="text-xs uppercase text-base-content/60">Тема</span>
                <span class="font-semibold capitalize">{{ currentTheme }}</span>
            </span>
            <svg
                class="h-4 w-4 transition-transform"
                :class="isDropdownOpen ? 'rotate-180' : 'rotate-0'"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div
            class="dropdown-content z-10 mb-2 w-64 max-w-[90vw] rounded-box bg-base-200 p-3 shadow-lg"
            v-show="isDropdownOpen"
        >
            <div class="grid grid-cols-1 gap-2 max-h-64 overflow-y-auto pr-1">
                <button
                    v-for="theme in themes"
                    :key="theme"
                    type="button"
                    class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm capitalize transition"
                    :data-theme="theme"
                    :class="currentTheme === theme ? 'border-primary/80 bg-primary/10 text-primary' : 'border-base-300 hover:border-primary/60 hover:text-primary'"
                    @click.stop="selectTheme(theme)"
                >
                    <div class="grid grid-cols-2 gap-0.5">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                        <span class="h-3 w-3 rounded-full bg-secondary"></span>
                        <span class="h-3 w-3 rounded-full bg-accent"></span>
                        <span class="h-3 w-3 rounded-full bg-neutral"></span>
                    </div>
                    <span class="truncate">{{ theme }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
