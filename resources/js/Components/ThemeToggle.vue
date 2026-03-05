<script setup>
import {computed, onMounted, ref} from "vue";
console.log(import.meta.env.VITE_THEME_LIGHT);
console.log(import.meta.env.VITE_THEME_DARK);
console.log(import.meta.env.VITE_THEME_STORAGE_KEY);
const props = defineProps({
    lightTheme: {
        type: String,
        default: (() => {
            const value = import.meta.env.VITE_THEME_LIGHT;
            if (!value || typeof value !== 'string') {
                return 'winter';
            }
            const trimmed = value.trim();
            return trimmed.length ? trimmed : 'winter';
        })(),
    },
    darkTheme: {
        type: String,
        default: (() => {
            const value = import.meta.env.VITE_THEME_DARK;
            if (!value || typeof value !== 'string') {
                return 'dim';
            }
            const trimmed = value.trim();
            return trimmed.length ? trimmed : 'dim';
        })(),
    },
    storageKey: {
        type: String,
        default: (() => {
            const value = import.meta.env.VITE_THEME_STORAGE_KEY;
            if (!value || typeof value !== 'string') {
                return 'color-theme-payment';
            }
            const trimmed = value.trim();
            return trimmed.length ? trimmed : 'color-theme-payment';
        })(),
    },
});

const themes = computed(() => [props.lightTheme, props.darkTheme]);
const defaultTheme = props.lightTheme;
const currentTheme = ref(defaultTheme);

const isDark = computed(() => currentTheme.value === props.darkTheme);
const label = computed(() => isDark.value ? 'Тёмная тема' : 'Светлая тема');

const persistTheme = (theme) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(props.storageKey, theme);
    } catch (error) {
        // игнорируем ошибки доступа к хранилищу
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
    if (!themes.value.includes(theme)) {
        return;
    }

    currentTheme.value = theme;
    updateHtmlTheme(theme);

    if (typeof window !== 'undefined') {
        persistTheme(theme);
    }
};

const getPreferredTheme = () => {
    if (typeof window === 'undefined') {
        return defaultTheme;
    }

    const savedTheme = window.localStorage.getItem(props.storageKey);
    if (savedTheme && themes.value.includes(savedTheme)) {
        return savedTheme;
    }

    return defaultTheme;
};

const toggleTheme = () => {
    const nextTheme = isDark.value ? props.lightTheme : props.darkTheme;
    applyTheme(nextTheme);
};

onMounted(() => {
    applyTheme(getPreferredTheme());
});
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-semibold text-base-content hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary/60"
        @click.prevent="toggleTheme"
        :aria-pressed="isDark"
        aria-label="Переключить тему"
    >
        <span class="flex items-center justify-center h-5 w-5">
            <svg
                v-if="!isDark"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="h-5 w-5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="h-5 w-5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
        </span>
        <span>
            {{ label }}
        </span>
    </button>
</template>


