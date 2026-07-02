/**
 * Schema, token order and allowed values for daisyUI v5 themes.
 * The token order here MUST match daisyUI so exported CSS looks native.
 */

export const COLOR_TOKENS = [
    '--color-base-100',
    '--color-base-200',
    '--color-base-300',
    '--color-base-content',
    '--color-primary',
    '--color-primary-content',
    '--color-secondary',
    '--color-secondary-content',
    '--color-accent',
    '--color-accent-content',
    '--color-neutral',
    '--color-neutral-content',
    '--color-info',
    '--color-info-content',
    '--color-success',
    '--color-success-content',
    '--color-warning',
    '--color-warning-content',
    '--color-error',
    '--color-error-content',
];

export const NON_COLOR_TOKENS = [
    '--radius-selector',
    '--radius-field',
    '--radius-box',
    '--size-selector',
    '--size-field',
    '--border',
    '--depth',
    '--noise',
];

export const ALL_TOKENS = [...COLOR_TOKENS, ...NON_COLOR_TOKENS];

/** Short aliases shown in the color picker (daisyUI convention). */
export const TOKEN_ALIASES = {
    '--color-base-100': 'b1',
    '--color-base-200': 'b2',
    '--color-base-300': 'b3',
    '--color-base-content': 'bc',
    '--color-primary': 'p',
    '--color-primary-content': 'pc',
    '--color-secondary': 's',
    '--color-secondary-content': 'sc',
    '--color-accent': 'a',
    '--color-accent-content': 'ac',
    '--color-neutral': 'n',
    '--color-neutral-content': 'nc',
    '--color-info': 'in',
    '--color-info-content': 'inc',
    '--color-success': 'su',
    '--color-success-content': 'suc',
    '--color-warning': 'wa',
    '--color-warning-content': 'wac',
    '--color-error': 'er',
    '--color-error-content': 'erc',
};

/** Color groups as shown in the editor. */
export const COLOR_GROUPS = [
    {
        key: 'base',
        label: 'Base',
        swatches: ['--color-base-100', '--color-base-200', '--color-base-300', '--color-base-content'],
    },
    { key: 'primary', label: 'Primary', swatches: ['--color-primary', '--color-primary-content'] },
    { key: 'secondary', label: 'Secondary', swatches: ['--color-secondary', '--color-secondary-content'] },
    { key: 'accent', label: 'Accent', swatches: ['--color-accent', '--color-accent-content'] },
    { key: 'neutral', label: 'Neutral', swatches: ['--color-neutral', '--color-neutral-content'] },
    { key: 'info', label: 'Info', swatches: ['--color-info', '--color-info-content'] },
    { key: 'success', label: 'Success', swatches: ['--color-success', '--color-success-content'] },
    { key: 'warning', label: 'Warning', swatches: ['--color-warning', '--color-warning-content'] },
    { key: 'error', label: 'Error', swatches: ['--color-error', '--color-error-content'] },
];

export const RADIUS_OPTIONS = ['0rem', '0.25rem', '0.5rem', '1rem', '2rem'];

export const RADIUS_TOKENS = [
    { key: '--radius-box', label: 'Boxes', hint: 'card, modal, alert' },
    { key: '--radius-field', label: 'Fields', hint: 'button, input, select, tab' },
    { key: '--radius-selector', label: 'Selectors', hint: 'checkbox, toggle, badge' },
];

export const SIZE_OPTIONS = ['0.1875rem', '0.21875rem', '0.25rem', '0.28125rem', '0.3125rem'];

export const SIZE_TOKENS = [
    { key: '--size-field', label: 'Fields', hint: 'button, input, select, tab' },
    { key: '--size-selector', label: 'Selectors', hint: 'checkbox, toggle, badge' },
];

export const BORDER_OPTIONS = ['0.5px', '1px', '1.5px', '2px'];

export const BINARY_TOKENS = [
    { key: '--depth', label: 'Depth Effect' },
    { key: '--noise', label: 'Noise Effect' },
];

/** Fallback tokens used when nothing else is available. */
export const DEFAULT_TOKENS = {
    '--color-base-100': 'oklch(100% 0 0)',
    '--color-base-200': 'oklch(96% 0 0)',
    '--color-base-300': 'oklch(92% 0 0)',
    '--color-base-content': 'oklch(21% 0.006 285.885)',
    '--color-primary': 'oklch(45% 0.24 277.023)',
    '--color-primary-content': 'oklch(93% 0.034 272.788)',
    '--color-secondary': 'oklch(65% 0.241 354.308)',
    '--color-secondary-content': 'oklch(94% 0.028 342.258)',
    '--color-accent': 'oklch(77% 0.152 181.912)',
    '--color-accent-content': 'oklch(38% 0.063 188.416)',
    '--color-neutral': 'oklch(14% 0.005 285.823)',
    '--color-neutral-content': 'oklch(92% 0.004 286.32)',
    '--color-info': 'oklch(74% 0.16 232.661)',
    '--color-info-content': 'oklch(29% 0.066 243.157)',
    '--color-success': 'oklch(76% 0.177 163.223)',
    '--color-success-content': 'oklch(37% 0.077 168.94)',
    '--color-warning': 'oklch(82% 0.189 84.429)',
    '--color-warning-content': 'oklch(41% 0.112 45.904)',
    '--color-error': 'oklch(71% 0.194 13.428)',
    '--color-error-content': 'oklch(27% 0.105 12.094)',
    '--radius-selector': '0.5rem',
    '--radius-field': '0.25rem',
    '--radius-box': '0.5rem',
    '--size-selector': '0.25rem',
    '--size-field': '0.25rem',
    '--border': '1px',
    '--depth': '1',
    '--noise': '0',
};

/** daisyUI v5 built-in theme slugs (order matters for the list). */
export const BUILTIN_THEME_SLUGS = [
    'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate', 'synthwave',
    'retro', 'cyberpunk', 'valentine', 'halloween', 'garden', 'forest', 'aqua',
    'lofi', 'pastel', 'fantasy', 'wireframe', 'black', 'luxury', 'dracula',
    'cmyk', 'autumn', 'business', 'acid', 'lemonade', 'night', 'coffee', 'winter',
    'dim', 'nord', 'sunset', 'caramellatte', 'abyss', 'silk',
];

/** Built-in themes that ship as dark by default (best-effort metadata). */
const DARK_BUILTIN_SLUGS = new Set([
    'dark', 'synthwave', 'halloween', 'forest', 'black', 'luxury', 'dracula',
    'business', 'night', 'coffee', 'dim', 'sunset', 'abyss',
]);

export const isDarkBuiltin = (slug) => DARK_BUILTIN_SLUGS.has(slug);

/**
 * Read the compiled CSS variable values for a daisyUI theme straight from the
 * browser. This avoids hardcoding all 35 built-in theme palettes: daisyUI is
 * already compiled with `themes: all`, so we just probe an off-screen element.
 *
 * @param {string} slug
 * @returns {Record<string,string>}
 */
export const readBuiltinTokens = (slug) => {
    if (typeof document === 'undefined') {
        return { ...DEFAULT_TOKENS };
    }

    const probe = document.createElement('div');
    probe.setAttribute('data-theme', slug);
    probe.style.position = 'absolute';
    probe.style.width = '0';
    probe.style.height = '0';
    probe.style.overflow = 'hidden';
    probe.style.pointerEvents = 'none';
    probe.style.opacity = '0';
    document.body.appendChild(probe);

    const computed = getComputedStyle(probe);
    const tokens = {};

    for (const token of ALL_TOKENS) {
        const value = computed.getPropertyValue(token).trim();
        tokens[token] = value || DEFAULT_TOKENS[token];
    }

    document.body.removeChild(probe);

    return tokens;
};

/**
 * Name validation identical in spirit to daisyUI: lowercase, starts with a
 * letter, ends alphanumeric, only a-z 0-9 space and dash, length 3..20.
 *
 * @param {string} name
 * @returns {string|null} error message (RU) or null when valid
 */
export const validateThemeName = (name) => {
    const value = String(name ?? '');

    if (value.length < 3 || value.length > 20) {
        return 'Название: от 3 до 20 символов';
    }

    if (!/^[a-z][a-z0-9 -]*[a-z0-9]$/.test(value)) {
        return 'Только строчные a-z, 0-9, пробел и дефис; начинать с буквы';
    }

    return null;
};

/**
 * @param {string} name
 * @returns {string}
 */
export const slugify = (name) => String(name ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 40) || 'custom-theme';

/**
 * @param {Record<string,string>} tokens
 * @returns {Record<string,string>} tokens filtered to the known set only
 */
export const sanitizeTokens = (tokens) => {
    const result = {};

    for (const token of ALL_TOKENS) {
        result[token] = tokens?.[token] ?? DEFAULT_TOKENS[token];
    }

    return result;
};
