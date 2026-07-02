import { relativeLuminance } from './color.js';

/**
 * WCAG 2.x contrast ratio between two colors (any supported CSS color string).
 *
 * @param {string} foreground
 * @param {string} background
 * @returns {number}
 */
export const contrastRatio = (foreground, background) => {
    const l1 = relativeLuminance(foreground);
    const l2 = relativeLuminance(background);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);

    return (lighter + 0.05) / (darker + 0.05);
};

/**
 * @param {number} ratio
 * @returns {'Low'|'AA'|'AAA'}
 */
export const contrastLevel = (ratio) => {
    if (ratio >= 7) {
        return 'AAA';
    }

    if (ratio >= 4.5) {
        return 'AA';
    }

    return 'Low';
};

const LEVEL_BADGE_CLASS = {
    Low: 'badge-error',
    AA: 'badge-warning',
    AAA: 'badge-success',
};

export const contrastBadgeClass = (level) => LEVEL_BADGE_CLASS[level] ?? 'badge-ghost';

/**
 * Pairs of tokens whose contrast matters for readability.
 *
 * @type {Array<{content:string,surface:string,label:string}>}
 */
export const CONTRAST_PAIRS = [
    { content: '--color-base-content', surface: '--color-base-100', label: 'Base' },
    { content: '--color-primary-content', surface: '--color-primary', label: 'Primary' },
    { content: '--color-secondary-content', surface: '--color-secondary', label: 'Secondary' },
    { content: '--color-accent-content', surface: '--color-accent', label: 'Accent' },
    { content: '--color-neutral-content', surface: '--color-neutral', label: 'Neutral' },
    { content: '--color-info-content', surface: '--color-info', label: 'Info' },
    { content: '--color-success-content', surface: '--color-success', label: 'Success' },
    { content: '--color-warning-content', surface: '--color-warning', label: 'Warning' },
    { content: '--color-error-content', surface: '--color-error', label: 'Error' },
];

const MIN_PUBLISH_RATIO = 3;

/**
 * @param {Record<string,string>} tokens
 * @returns {Array<{label:string,ratio:number,level:string,content:string,surface:string}>}
 */
export const evaluateContrast = (tokens) => CONTRAST_PAIRS.map((pair) => {
    const ratio = contrastRatio(tokens[pair.content], tokens[pair.surface]);

    return {
        label: pair.label,
        content: pair.content,
        surface: pair.surface,
        ratio: Math.round(ratio * 100) / 100,
        level: contrastLevel(ratio),
    };
});

/**
 * @param {Record<string,string>} tokens
 * @returns {boolean} true when every critical pair is above the publish threshold
 */
export const passesContrastForPublish = (tokens) => evaluateContrast(tokens)
    .every((pair) => pair.ratio >= MIN_PUBLISH_RATIO);
