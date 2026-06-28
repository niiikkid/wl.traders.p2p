import { createHighlighterCore } from 'shiki/core';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';

export const SHIKI_THEME = 'github-dark-dimmed';

let highlighterPromise = null;

/**
 * Lazily creates a single shared Shiki highlighter with a minimal,
 * fine-grained set of languages and a dark theme that matches the `dim` DaisyUI theme.
 */
function getHighlighter() {
    if (!highlighterPromise) {
        highlighterPromise = createHighlighterCore({
            themes: [import('@shikijs/themes/github-dark-dimmed')],
            langs: [
                import('@shikijs/langs/jsonc'),
                import('@shikijs/langs/json'),
                import('@shikijs/langs/bash'),
                import('@shikijs/langs/javascript'),
                import('@shikijs/langs/php'),
            ],
            engine: createJavaScriptRegexEngine(),
        });
    }

    return highlighterPromise;
}

/**
 * Highlight a code string to HTML using the shared highlighter.
 *
 * @param {string} code
 * @param {string} lang
 * @returns {Promise<string>}
 */
export async function highlightCode(code, lang = 'jsonc') {
    const highlighter = await getHighlighter();

    return highlighter.codeToHtml(code, {
        lang,
        theme: SHIKI_THEME,
    });
}
