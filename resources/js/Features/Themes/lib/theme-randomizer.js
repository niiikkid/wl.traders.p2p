import {
    CHROMATIC_FAMILIES,
    NEUTRAL_FAMILIES,
    tailwindColor,
} from './color.js';
import { RADIUS_OPTIONS } from './theme-schema.js';

const pick = (list) => list[Math.floor(Math.random() * list.length)];

const chance = (probability) => Math.random() < probability;

const SEMANTIC_FAMILIES = {
    info: ['cyan', 'sky', 'blue'],
    success: ['lime', 'green', 'emerald', 'teal'],
    warning: ['yellow', 'amber', 'orange'],
    error: ['red', 'pink', 'rose'],
};

const contentShadeFor = (isDark) => (isDark ? 100 : 950);

/**
 * Generate a fresh, coherent daisyUI token set. Compatible in spirit with the
 * daisyUI generator: neutral-leaning base, semantic colors from sensible
 * families, content colors chosen for contrast.
 *
 * @returns {{colorScheme:'light'|'dark',tokens:Record<string,string>}}
 */
export const randomTokens = () => {
    const isDark = chance(0.5);
    const baseFamily = chance(0.7) ? pick(NEUTRAL_FAMILIES) : pick(CHROMATIC_FAMILIES);

    const baseShades = isDark
        ? { b1: 950, b2: 900, b3: 800, bc: 100 }
        : { b1: 50, b2: 100, b3: 200, bc: 900 };

    const brandShade = () => pick([400, 500, 600]);
    const semanticShade = () => pick([400, 500, 600]);

    const brand = (family) => {
        const shade = brandShade();
        const contentShade = shade >= 500 ? 100 : 900;

        return {
            color: tailwindColor(family, shade),
            content: tailwindColor(family, contentShade),
        };
    };

    const primary = brand(pick(CHROMATIC_FAMILIES));
    const secondary = brand(pick(CHROMATIC_FAMILIES));
    const accent = brand(pick(CHROMATIC_FAMILIES));

    const neutralShade = pick([600, 700, 800, 900, 950]);

    const semantic = (name) => {
        const family = pick(SEMANTIC_FAMILIES[name]);
        const shade = semanticShade();

        return {
            color: tailwindColor(family, shade),
            content: tailwindColor(family, shade >= 500 ? 100 : 950),
        };
    };

    const info = semantic('info');
    const success = semantic('success');
    const warning = semantic('warning');
    const error = semantic('error');

    const radius = pick(RADIUS_OPTIONS);
    const border = chance(0.75) ? '1px' : pick(['1.5px', '2px']);

    return {
        colorScheme: isDark ? 'dark' : 'light',
        tokens: {
            '--color-base-100': tailwindColor(baseFamily, baseShades.b1),
            '--color-base-200': tailwindColor(baseFamily, baseShades.b2),
            '--color-base-300': tailwindColor(baseFamily, baseShades.b3),
            '--color-base-content': tailwindColor(baseFamily, baseShades.bc),
            '--color-primary': primary.color,
            '--color-primary-content': primary.content,
            '--color-secondary': secondary.color,
            '--color-secondary-content': secondary.content,
            '--color-accent': accent.color,
            '--color-accent-content': accent.content,
            '--color-neutral': tailwindColor(baseFamily, neutralShade),
            '--color-neutral-content': tailwindColor(baseFamily, contentShadeFor(neutralShade >= 500)),
            '--color-info': info.color,
            '--color-info-content': info.content,
            '--color-success': success.color,
            '--color-success-content': success.content,
            '--color-warning': warning.color,
            '--color-warning-content': warning.content,
            '--color-error': error.color,
            '--color-error-content': error.content,
            '--radius-selector': radius,
            '--radius-field': pick(RADIUS_OPTIONS),
            '--radius-box': radius,
            '--size-selector': '0.25rem',
            '--size-field': '0.25rem',
            '--border': border,
            '--depth': chance(0.5) ? '1' : '0',
            '--noise': chance(0.2) ? '1' : '0',
        },
    };
};

const ADJECTIVES = ['calm', 'bold', 'soft', 'deep', 'bright', 'cool', 'warm', 'mellow', 'crisp', 'vivid'];
const NOUNS = ['orbit', 'harbor', 'aurora', 'ember', 'meadow', 'cobalt', 'quartz', 'lagoon', 'dusk', 'flux'];

/**
 * @returns {string} a valid random theme name (3..20 lowercase chars)
 */
export const randomThemeName = () => `${pick(ADJECTIVES)} ${pick(NOUNS)}`;
