import { ALL_TOKENS, sanitizeTokens } from './theme-schema.js';
import { safeTokens } from './theme-css-renderer.js';

/** data-theme value used to render the live-edited / applied theme. */
export const LIVE_SLUG = 'tg-live';

const LIVE_STYLE_ID = 'theme-generator-live-style';

export const STORAGE_KEYS = {
    custom: 'theme-generator:custom',
    selected: 'theme-generator:selected',
};

const readJson = (key, fallback) => {
    if (typeof window === 'undefined') {
        return fallback;
    }

    try {
        const raw = window.localStorage.getItem(key);

        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
};

const writeJson = (key, value) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        // storage may be unavailable (private mode / quota) — ignore
    }
};

/**
 * Inject/refresh the live style block and point the document at it.
 *
 * @param {{colorScheme:string,tokens:Record<string,string>}} theme
 */
export const applyLiveTheme = (theme) => {
    if (typeof document === 'undefined') {
        return;
    }

    const clean = safeTokens(theme.tokens);
    const scheme = theme.colorScheme === 'dark' ? 'dark' : 'light';
    const lines = ALL_TOKENS
        .filter((token) => clean[token])
        .map((token) => `  ${token}: ${clean[token]};`)
        .join('\n');

    const css = `[data-theme="${LIVE_SLUG}"] {\n  color-scheme: ${scheme};\n${lines}\n}`;

    let style = document.getElementById(LIVE_STYLE_ID);

    if (!style) {
        style = document.createElement('style');
        style.id = LIVE_STYLE_ID;
        document.head.appendChild(style);
    }

    style.textContent = css;

    const root = document.documentElement;
    root.setAttribute('data-theme', LIVE_SLUG);
    root.classList.toggle('dark', scheme === 'dark');
};

/**
 * Restore a plain built-in daisyUI theme (no injected variables needed).
 *
 * @param {string} slug
 * @param {'light'|'dark'} colorScheme
 */
export const applyBuiltinTheme = (slug, colorScheme) => {
    if (typeof document === 'undefined') {
        return;
    }

    const style = document.getElementById(LIVE_STYLE_ID);
    if (style) {
        style.remove();
    }

    const root = document.documentElement;
    root.setAttribute('data-theme', slug);
    root.classList.toggle('dark', colorScheme === 'dark');
};

export const readCustomThemes = () => {
    const stored = readJson(STORAGE_KEYS.custom, []);

    return Array.isArray(stored) ? stored : [];
};

export const writeCustomThemes = (themes) => {
    writeJson(STORAGE_KEYS.custom, themes);
};

export const readSelectedTheme = () => readJson(STORAGE_KEYS.selected, null);

export const persistSelectedTheme = (theme) => {
    if (!theme) {
        writeJson(STORAGE_KEYS.selected, null);

        return;
    }

    writeJson(STORAGE_KEYS.selected, {
        id: theme.id ?? null,
        type: theme.type ?? 'custom',
        slug: theme.slug,
        name: theme.name,
        colorScheme: theme.colorScheme,
        tokens: sanitizeTokens(theme.tokens),
    });
};

/**
 * Re-apply the persisted selection on app boot so the chosen theme survives
 * full page reloads. Safe to call before the Vue app mounts.
 */
export const bootstrapPersistedTheme = () => {
    const selected = readSelectedTheme();

    if (!selected) {
        return;
    }

    if (selected.type === 'builtin' && selected.slug) {
        applyBuiltinTheme(selected.slug, selected.colorScheme);

        return;
    }

    if (selected.tokens) {
        applyLiveTheme(selected);
    }
};
