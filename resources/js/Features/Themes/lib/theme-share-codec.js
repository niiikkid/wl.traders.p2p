import { sanitizeTokens } from './theme-schema.js';

/**
 * Share codec for the theme hash. daisyUI uses deflate+base64url; to avoid a
 * `pako` dependency we use a plain JSON → base64url encoding. Import stays
 * tolerant so a daisyUI hash can still be attempted.
 */

const toBase64Url = (text) => btoa(unescape(encodeURIComponent(text)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

const fromBase64Url = (encoded) => {
    const padded = encoded.replace(/-/g, '+').replace(/_/g, '/')
        + '==='.slice((encoded.length + 3) % 4);

    return decodeURIComponent(escape(atob(padded)));
};

/**
 * @param {{name:string,colorScheme:string,tokens:Record<string,string>}} theme
 * @returns {string} hash payload (without the leading `#theme=`)
 */
export const encodeThemeHash = (theme) => {
    const payload = {
        name: theme.name,
        colorScheme: theme.colorScheme,
        tokens: sanitizeTokens(theme.tokens),
    };

    return toBase64Url(JSON.stringify(payload));
};

/**
 * @param {string} encoded
 * @returns {{name:string,colorScheme:string,tokens:Record<string,string>}|null}
 */
export const decodeThemeHash = (encoded) => {
    try {
        const parsed = JSON.parse(fromBase64Url(encoded));

        if (!parsed || typeof parsed !== 'object' || !parsed.tokens) {
            return null;
        }

        return {
            name: typeof parsed.name === 'string' ? parsed.name : 'imported theme',
            colorScheme: parsed.colorScheme === 'dark' ? 'dark' : 'light',
            tokens: sanitizeTokens(parsed.tokens),
        };
    } catch (error) {
        return null;
    }
};

export const buildShareUrl = (theme) => {
    const hash = encodeThemeHash(theme);

    if (typeof window === 'undefined') {
        return `#theme=${hash}`;
    }

    return `${window.location.origin}${window.location.pathname}#theme=${hash}`;
};
