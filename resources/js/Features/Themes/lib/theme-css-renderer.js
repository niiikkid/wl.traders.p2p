import { ALL_TOKENS, DEFAULT_TOKENS, sanitizeTokens, slugify } from './theme-schema.js';

const COLOR_VALUE = /^(oklch|hsl|rgb|rgba)\([^{}<>;]*\)$/i;
const HEX_VALUE = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
const DIMENSION_VALUE = /^-?[\d.]+(px|rem|em|%)?$/;
const FORBIDDEN = /url\(|var\(|calc\(|expression|[;{}<>]/i;

/**
 * Guard against CSS injection through token values. Frontend mirror of what a
 * backend validator would enforce (spec §15).
 *
 * @param {string} value
 * @returns {boolean}
 */
export const isSafeTokenValue = (value) => {
    if (typeof value !== 'string') {
        return false;
    }

    const trimmed = value.trim();

    if (!trimmed || FORBIDDEN.test(trimmed)) {
        return false;
    }

    return COLOR_VALUE.test(trimmed) || HEX_VALUE.test(trimmed) || DIMENSION_VALUE.test(trimmed);
};

/**
 * Keep only known tokens with safe values.
 *
 * @param {Record<string,string>} tokens
 * @returns {Record<string,string>}
 */
export const safeTokens = (tokens) => {
    const clean = sanitizeTokens(tokens);
    const result = {};

    for (const token of ALL_TOKENS) {
        result[token] = isSafeTokenValue(clean[token]) ? clean[token].trim() : '';
    }

    return result;
};

const renderTokenLines = (tokens, indent) => ALL_TOKENS
    .filter((token) => tokens[token])
    .map((token) => `${indent}${token}: ${tokens[token]};`)
    .join('\n');

/**
 * Runtime CSS for a theme, applied via `[data-theme="slug"]` without a Vite
 * rebuild. Also targets the theme-controller selector for compatibility.
 *
 * @param {{slug:string,colorScheme:string,tokens:Record<string,string>}} theme
 * @returns {string}
 */
export const renderRuntimeCss = ({ slug, colorScheme, tokens }) => {
    const safeSlug = slugify(slug);
    const clean = safeTokens(tokens);
    const lines = renderTokenLines(clean, '  ');
    const scheme = colorScheme === 'dark' ? 'dark' : 'light';

    return `:root:has(input.theme-controller[value="${safeSlug}"]:checked),
[data-theme="${safeSlug}"] {
  color-scheme: ${scheme};
${lines}
}`;
};

/**
 * daisyUI plugin format, ready to paste into app.css.
 *
 * @param {object} theme
 * @returns {string}
 */
export const renderPluginCss = ({ slug, colorScheme, tokens, isDefault = false, isPrefersDark = false }) => {
    const safeSlug = slugify(slug);
    const clean = safeTokens(tokens);
    const lines = renderTokenLines(clean, '  ');
    const scheme = colorScheme === 'dark' ? 'dark' : 'light';

    return `@plugin "daisyui/theme" {
  name: "${safeSlug}";
  default: ${isDefault ? 'true' : 'false'};
  prefersdark: ${isPrefersDark ? 'true' : 'false'};
  color-scheme: "${scheme}";
${lines}
}`;
};

/**
 * Parse a daisyUI plugin block or runtime CSS block back into a theme object.
 * Only known tokens with safe values survive.
 *
 * @param {string} css
 * @returns {{name:string,colorScheme:'light'|'dark',tokens:Record<string,string>}|null}
 */
export const parseThemeCss = (css) => {
    if (typeof css !== 'string' || !css.trim()) {
        return null;
    }

    const nameMatch = css.match(/name:\s*"([^"]+)"/) || css.match(/\[data-theme="([^"]+)"\]/);
    const schemeMatch = css.match(/color-scheme:\s*"?(light|dark)"?/i);

    const tokens = { ...DEFAULT_TOKENS };
    let found = 0;
    const declarationPattern = /(--[a-z0-9-]+)\s*:\s*([^;{}]+);/gi;
    let match;

    while ((match = declarationPattern.exec(css)) !== null) {
        const [, token, rawValue] = match;
        const value = rawValue.trim();

        if (ALL_TOKENS.includes(token) && isSafeTokenValue(value)) {
            tokens[token] = value;
            found += 1;
        }
    }

    if (found === 0) {
        return null;
    }

    return {
        name: nameMatch ? nameMatch[1] : 'imported theme',
        colorScheme: schemeMatch && schemeMatch[1].toLowerCase() === 'dark' ? 'dark' : 'light',
        tokens,
    };
};
