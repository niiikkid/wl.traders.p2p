/**
 * Self-contained color utilities for the theme generator.
 *
 * daisyUI v5 stores theme colors as `oklch(L% C H)`. We keep OKLCH as the
 * canonical editing format and convert to/from sRGB only for the native color
 * input and WCAG contrast math. No external dependency (culori) is used.
 */

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const round = (value, precision = 3) => {
    const factor = 10 ** precision;

    return Math.round(value * factor) / factor;
};

const NAMED_COLORS = {
    black: { r: 0, g: 0, b: 0 },
    white: { r: 255, g: 255, b: 255 },
    transparent: { r: 0, g: 0, b: 0 },
};

/**
 * @param {string} hex
 * @returns {{r:number,g:number,b:number}|null}
 */
export const hexToRgb = (hex) => {
    let value = hex.trim().replace(/^#/, '');

    if (value.length === 3) {
        value = value.split('').map((char) => char + char).join('');
    }

    if (value.length === 8) {
        value = value.slice(0, 6);
    }

    if (value.length !== 6 || /[^0-9a-f]/i.test(value)) {
        return null;
    }

    return {
        r: parseInt(value.slice(0, 2), 16),
        g: parseInt(value.slice(2, 4), 16),
        b: parseInt(value.slice(4, 6), 16),
    };
};

/**
 * @param {{r:number,g:number,b:number}} rgb
 * @returns {string}
 */
export const rgbToHex = ({ r, g, b }) => {
    const toHex = (channel) => clamp(Math.round(channel), 0, 255)
        .toString(16)
        .padStart(2, '0');

    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
};

const srgbToLinear = (channel) => {
    const c = channel / 255;

    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
};

const linearToSrgb = (channel) => {
    const c = channel <= 0.0031308 ? channel * 12.92 : 1.055 * channel ** (1 / 2.4) - 0.055;

    return clamp(c * 255, 0, 255);
};

/**
 * OKLCH → sRGB (0..255). L accepts either 0..1 or (handled by caller) percent.
 *
 * @param {{l:number,c:number,h:number}} oklch
 * @returns {{r:number,g:number,b:number}}
 */
export const oklchToRgb = ({ l, c, h }) => {
    const hueRad = (h * Math.PI) / 180;
    const a = c * Math.cos(hueRad);
    const bb = c * Math.sin(hueRad);

    const l_ = l + 0.3963377774 * a + 0.2158037573 * bb;
    const m_ = l - 0.1055613458 * a - 0.0638541728 * bb;
    const s_ = l - 0.0894841775 * a - 1.291485548 * bb;

    const lCubed = l_ ** 3;
    const mCubed = m_ ** 3;
    const sCubed = s_ ** 3;

    const rLinear = 4.0767416621 * lCubed - 3.3077115913 * mCubed + 0.2309699292 * sCubed;
    const gLinear = -1.2684380046 * lCubed + 2.6097574011 * mCubed - 0.3413193965 * sCubed;
    const bLinear = -0.0041960863 * lCubed - 0.7034186147 * mCubed + 1.707614701 * sCubed;

    return {
        r: linearToSrgb(rLinear),
        g: linearToSrgb(gLinear),
        b: linearToSrgb(bLinear),
    };
};

/**
 * sRGB (0..255) → OKLCH ({l:0..1, c, h:deg}).
 *
 * @param {{r:number,g:number,b:number}} rgb
 * @returns {{l:number,c:number,h:number}}
 */
export const rgbToOklch = ({ r, g, b }) => {
    const rl = srgbToLinear(r);
    const gl = srgbToLinear(g);
    const bl = srgbToLinear(b);

    const l = 0.4122214708 * rl + 0.5363325363 * gl + 0.0514459929 * bl;
    const m = 0.2119034982 * rl + 0.6806995451 * gl + 0.1073969566 * bl;
    const s = 0.0883024619 * rl + 0.2817188376 * gl + 0.6299787005 * bl;

    const l_ = Math.cbrt(l);
    const m_ = Math.cbrt(m);
    const s_ = Math.cbrt(s);

    const okL = 0.2104542553 * l_ + 0.793617785 * m_ - 0.0040720468 * s_;
    const okA = 1.9779984951 * l_ - 2.428592205 * m_ + 0.4505937099 * s_;
    const okB = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.808675766 * s_;

    const c = Math.sqrt(okA * okA + okB * okB);
    let h = (Math.atan2(okB, okA) * 180) / Math.PI;

    if (h < 0) {
        h += 360;
    }

    return { l: okL, c, h };
};

const hslToRgb = ({ h, s, l }) => {
    const sat = s / 100;
    const light = l / 100;
    const k = (n) => (n + h / 30) % 12;
    const a = sat * Math.min(light, 1 - light);
    const f = (n) => light - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));

    return {
        r: f(0) * 255,
        g: f(8) * 255,
        b: f(4) * 255,
    };
};

const NUMBER = '(-?[\\d.]+%?)';
const TRIPLE = new RegExp(`^\\w+\\(\\s*${NUMBER}[\\s,]+${NUMBER}[\\s,]+${NUMBER}`, 'i');

const asNumber = (raw, percentBase = 1) => {
    if (typeof raw !== 'string') {
        return Number(raw);
    }

    if (raw.endsWith('%')) {
        return (parseFloat(raw) / 100) * percentBase;
    }

    return parseFloat(raw);
};

/**
 * Parse any supported CSS color string into sRGB (0..255). Returns null when
 * the value can't be understood.
 *
 * @param {string} input
 * @returns {{r:number,g:number,b:number}|null}
 */
export const parseColorToRgb = (input) => {
    if (typeof input !== 'string') {
        return null;
    }

    const value = input.trim().toLowerCase();

    if (NAMED_COLORS[value]) {
        return { ...NAMED_COLORS[value] };
    }

    if (value.startsWith('#')) {
        return hexToRgb(value);
    }

    const match = value.match(TRIPLE);

    if (!match) {
        return null;
    }

    const [, a, b, c] = match;

    if (value.startsWith('oklch')) {
        const l = a.endsWith('%') ? parseFloat(a) / 100 : parseFloat(a);

        return oklchToRgb({ l, c: parseFloat(b), h: parseFloat(c) });
    }

    if (value.startsWith('hsl')) {
        return hslToRgb({ h: parseFloat(a), s: parseFloat(b), l: parseFloat(c) });
    }

    if (value.startsWith('rgb')) {
        return {
            r: asNumber(a, 255),
            g: asNumber(b, 255),
            b: asNumber(c, 255),
        };
    }

    return null;
};

/**
 * @param {{l:number,c:number,h:number}} oklch  L expressed as 0..1
 * @returns {string} daisyUI-style `oklch(L% C H)`
 */
export const formatOklch = ({ l, c, h }) => {
    const lPercent = round(clamp(l, 0, 1) * 100, 3);
    const chroma = round(Math.max(0, c), 4);
    const hue = round(((h % 360) + 360) % 360, 3);

    return `oklch(${lPercent}% ${chroma} ${hue})`;
};

/**
 * Parse an `oklch(...)` string into `{l:0..1, c, h}`. Falls back through sRGB
 * for non-oklch inputs so the picker can always show OKLCH controls.
 *
 * @param {string} input
 * @returns {{l:number,c:number,h:number}}
 */
export const parseOklch = (input) => {
    if (typeof input === 'string' && input.trim().toLowerCase().startsWith('oklch')) {
        const match = input.trim().toLowerCase().match(TRIPLE);

        if (match) {
            const [, a, b, c] = match;
            const l = a.endsWith('%') ? parseFloat(a) / 100 : parseFloat(a);

            return { l, c: parseFloat(b), h: parseFloat(c) };
        }
    }

    const rgb = parseColorToRgb(input) ?? { r: 0, g: 0, b: 0 };

    return rgbToOklch(rgb);
};

export const colorToHex = (input) => {
    const rgb = parseColorToRgb(input);

    return rgb ? rgbToHex(rgb) : '#000000';
};

export const hexToOklchString = (hex) => {
    const rgb = hexToRgb(hex) ?? { r: 0, g: 0, b: 0 };

    return formatOklch(rgbToOklch(rgb));
};

/**
 * Relative luminance (WCAG) from any supported color string.
 *
 * @param {string} input
 * @returns {number}
 */
export const relativeLuminance = (input) => {
    const rgb = parseColorToRgb(input) ?? { r: 0, g: 0, b: 0 };
    const r = srgbToLinear(rgb.r);
    const g = srgbToLinear(rgb.g);
    const b = srgbToLinear(rgb.b);

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

// --- Tailwind-flavoured OKLCH palette (approximation, generated locally) ---

const FAMILY_HUES = {
    slate: { h: 257, chromaScale: 0.18 },
    gray: { h: 265, chromaScale: 0.14 },
    zinc: { h: 286, chromaScale: 0.1 },
    neutral: { h: 0, chromaScale: 0 },
    stone: { h: 60, chromaScale: 0.08 },
    red: { h: 27, chromaScale: 1 },
    orange: { h: 47, chromaScale: 1 },
    amber: { h: 70, chromaScale: 1 },
    yellow: { h: 95, chromaScale: 1 },
    lime: { h: 130, chromaScale: 1 },
    green: { h: 150, chromaScale: 1 },
    emerald: { h: 165, chromaScale: 1 },
    teal: { h: 185, chromaScale: 0.95 },
    cyan: { h: 210, chromaScale: 0.9 },
    sky: { h: 235, chromaScale: 0.95 },
    blue: { h: 260, chromaScale: 1 },
    indigo: { h: 275, chromaScale: 1 },
    violet: { h: 293, chromaScale: 1 },
    purple: { h: 305, chromaScale: 1 },
    fuchsia: { h: 322, chromaScale: 1 },
    pink: { h: 350, chromaScale: 1 },
    rose: { h: 12, chromaScale: 1 },
};

export const TAILWIND_SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

// Lightness (OKLCH L, 0..1) and base chroma per shade, tuned to feel like Tailwind.
const SHADE_LIGHTNESS = {
    50: 0.985, 100: 0.967, 200: 0.929, 300: 0.872, 400: 0.775,
    500: 0.685, 600: 0.6, 700: 0.52, 800: 0.443, 900: 0.379, 950: 0.274,
};

const SHADE_CHROMA = {
    50: 0.018, 100: 0.032, 200: 0.06, 300: 0.095, 400: 0.14,
    500: 0.16, 600: 0.155, 700: 0.14, 800: 0.12, 900: 0.095, 950: 0.07,
};

export const TAILWIND_FAMILIES = Object.keys(FAMILY_HUES);

export const NEUTRAL_FAMILIES = ['slate', 'gray', 'zinc', 'neutral', 'stone'];

export const CHROMATIC_FAMILIES = TAILWIND_FAMILIES.filter(
    (family) => !NEUTRAL_FAMILIES.includes(family),
);

/**
 * @param {string} family
 * @param {number} shade
 * @returns {string} oklch string
 */
export const tailwindColor = (family, shade) => {
    const config = FAMILY_HUES[family] ?? FAMILY_HUES.neutral;
    const l = SHADE_LIGHTNESS[shade] ?? 0.6;
    const c = SHADE_CHROMA[shade] * config.chromaScale;

    return formatOklch({ l, c, h: config.h });
};

/**
 * @param {string} family
 * @returns {Array<{shade:number,color:string}>}
 */
export const tailwindFamilyPalette = (family) => TAILWIND_SHADES.map((shade) => ({
    shade,
    color: tailwindColor(family, shade),
}));
