import { defineStore } from 'pinia';
import {
    BUILTIN_THEME_SLUGS,
    DEFAULT_TOKENS,
    isDarkBuiltin,
    readBuiltinTokens,
    sanitizeTokens,
    slugify,
    validateThemeName,
} from '../lib/theme-schema.js';
import {
    applyBuiltinTheme,
    applyLiveTheme,
    persistSelectedTheme,
    readCustomThemes,
    readSelectedTheme,
    writeCustomThemes,
} from '../lib/theme-runtime.js';
import { randomThemeName, randomTokens } from '../lib/theme-randomizer.js';
import { decodeThemeHash } from '../lib/theme-share-codec.js';
import { passesContrastForPublish } from '../lib/theme-contrast.js';

const uid = () => (typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `theme-${Date.now()}-${Math.random().toString(16).slice(2)}`);

const deepClone = (value) => JSON.parse(JSON.stringify(value));

const snapshot = (theme) => JSON.stringify({
    name: theme?.name,
    colorScheme: theme?.colorScheme,
    tokens: theme?.tokens,
});

const makeCustomTheme = ({ name, colorScheme, tokens }) => ({
    id: uid(),
    type: 'custom',
    name,
    slug: slugify(name),
    colorScheme,
    isDefault: false,
    isPrefersDark: colorScheme === 'dark',
    status: 'draft',
    tokens: sanitizeTokens(tokens),
});

export const useThemeGeneratorStore = defineStore('themeGenerator', {
    state: () => ({
        isOpen: false,
        activeTab: 'editor',
        builtin: [],
        custom: [],
        draft: null,
        savedSnapshot: '',
        cssModalOpen: false,
        loaded: false,
        publishing: false,
    }),

    getters: {
        dirty: (state) => Boolean(state.draft) && snapshot(state.draft) !== state.savedSnapshot,
        activeThemeId: (state) => state.draft?.id ?? null,
        isDraftCustom: (state) => state.draft?.type === 'custom',
        draftTokens: (state) => state.draft?.tokens ?? DEFAULT_TOKENS,
        nameError: (state) => (state.draft ? validateThemeName(state.draft.name) : null),
        contrastOk: (state) => (state.draft ? passesContrastForPublish(state.draft.tokens) : true),
        canPublish() {
            return Boolean(this.draft) && !this.nameError && this.contrastOk;
        },
    },

    actions: {
        loadThemes() {
            if (this.loaded) {
                return;
            }

            this.builtin = BUILTIN_THEME_SLUGS.map((slug) => ({
                id: `builtin:${slug}`,
                type: 'builtin',
                name: slug,
                slug,
                colorScheme: isDarkBuiltin(slug) ? 'dark' : 'light',
                isDefault: false,
                isPrefersDark: false,
                status: 'published',
                tokens: readBuiltinTokens(slug),
            }));

            this.custom = readCustomThemes();
            this.loaded = true;
        },

        open() {
            this.loadThemes();

            if (!this.draft) {
                const selected = readSelectedTheme();

                if (selected?.tokens) {
                    this.draft = {
                        id: selected.id ?? uid(),
                        type: selected.type === 'builtin' ? 'builtin' : 'custom',
                        name: selected.name ?? selected.slug,
                        slug: selected.slug,
                        colorScheme: selected.colorScheme,
                        isDefault: false,
                        isPrefersDark: selected.colorScheme === 'dark',
                        status: 'draft',
                        tokens: sanitizeTokens(selected.tokens),
                    };
                } else {
                    this.selectThemeInternal(this.builtin.find((t) => t.slug === 'dim') ?? this.builtin[0]);
                }

                this.savedSnapshot = snapshot(this.draft);
            }

            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
            this.cssModalOpen = false;
        },

        selectThemeInternal(theme) {
            if (!theme) {
                return;
            }

            this.draft = deepClone(theme);
            this.savedSnapshot = snapshot(this.draft);
            this.applyDraftLive();
        },

        selectTheme(id) {
            const theme = [...this.custom, ...this.builtin].find((t) => t.id === id);
            this.selectThemeInternal(theme);
        },

        /** Ensure the draft is an editable custom copy before mutating tokens. */
        ensureEditableDraft() {
            if (this.draft?.type === 'custom') {
                return;
            }

            const baseName = this.draft?.name ?? 'custom theme';
            const copyName = slugify(`${baseName} copy`).replace(/-/g, ' ').slice(0, 20);

            this.draft = {
                ...deepClone(this.draft),
                id: uid(),
                type: 'custom',
                name: copyName,
                slug: slugify(copyName),
                status: 'draft',
            };
        },

        updateToken(key, value) {
            this.ensureEditableDraft();
            this.draft.tokens = { ...this.draft.tokens, [key]: value };
            this.applyDraftLive();
        },

        setName(name) {
            this.ensureEditableDraft();
            this.draft.name = name;
            this.draft.slug = slugify(name);
        },

        setColorScheme(scheme) {
            this.ensureEditableDraft();
            this.draft.colorScheme = scheme === 'dark' ? 'dark' : 'light';
            this.applyDraftLive();
        },

        setDefault(value) {
            this.ensureEditableDraft();
            this.draft.isDefault = Boolean(value);
        },

        setPrefersDark(value) {
            this.ensureEditableDraft();
            this.draft.isPrefersDark = Boolean(value);
        },

        createRandomTheme() {
            const { colorScheme, tokens } = randomTokens();
            const theme = makeCustomTheme({ name: randomThemeName(), colorScheme, tokens });

            this.draft = theme;
            this.savedSnapshot = '';
            this.activeTab = 'editor';
            this.applyDraftLive();
        },

        randomizeActive() {
            this.ensureEditableDraft();
            const { colorScheme, tokens } = randomTokens();
            this.draft.tokens = tokens;
            this.draft.colorScheme = colorScheme;
            this.applyDraftLive();
        },

        duplicateActive() {
            if (!this.draft) {
                return;
            }

            const theme = makeCustomTheme({
                name: `${this.draft.name} copy`.slice(0, 20),
                colorScheme: this.draft.colorScheme,
                tokens: this.draft.tokens,
            });

            this.draft = theme;
            this.savedSnapshot = '';
            this.applyDraftLive();
        },

        saveDraft() {
            this.ensureEditableDraft();

            const index = this.custom.findIndex((t) => t.id === this.draft.id);
            const stored = deepClone({ ...this.draft, status: 'draft' });

            if (index >= 0) {
                this.custom.splice(index, 1, stored);
            } else {
                this.custom.push(stored);
            }

            writeCustomThemes(this.custom);
            persistSelectedTheme(this.draft);
            this.savedSnapshot = snapshot(this.draft);
        },

        /**
         * Publish the current draft as the project-wide theme. Persists it on
         * the server so every user sees it, then mirrors it locally.
         *
         * @returns {Promise<boolean>}
         */
        async publish() {
            if (!this.canPublish || this.publishing) {
                return false;
            }

            this.publishing = true;

            try {
                await window.axios.post(route('admin.appearance.theme.publish'), {
                    type: this.draft.type === 'builtin' ? 'builtin' : 'custom',
                    slug: this.draft.slug,
                    name: this.draft.name,
                    colorScheme: this.draft.colorScheme === 'dark' ? 'dark' : 'light',
                    tokens: sanitizeTokens(this.draft.tokens),
                }, {
                    headers: { Accept: 'application/json' },
                });
            } catch (error) {
                return false;
            } finally {
                this.publishing = false;
            }

            this.draft.status = 'published';

            if (this.draft.type === 'custom') {
                const index = this.custom.findIndex((t) => t.id === this.draft.id);
                const stored = deepClone(this.draft);

                if (index >= 0) {
                    this.custom.splice(index, 1, stored);
                } else {
                    this.custom.push(stored);
                }

                writeCustomThemes(this.custom);
            }

            persistSelectedTheme(this.draft);
            this.savedSnapshot = snapshot(this.draft);
            this.applyDraftLive();

            return true;
        },

        removeTheme(id) {
            const targetId = id ?? this.draft?.id;
            this.custom = this.custom.filter((t) => t.id !== targetId);
            writeCustomThemes(this.custom);

            if (this.draft?.id === targetId) {
                this.selectThemeInternal(this.builtin.find((t) => t.slug === 'dim') ?? this.builtin[0]);
            }
        },

        removeAllCustomThemes() {
            this.custom = [];
            writeCustomThemes(this.custom);
            this.selectThemeInternal(this.builtin.find((t) => t.slug === 'dim') ?? this.builtin[0]);
        },

        applyDraftLive() {
            if (!this.draft) {
                return;
            }

            if (this.draft.type === 'builtin') {
                applyBuiltinTheme(this.draft.slug, this.draft.colorScheme);
            } else {
                applyLiveTheme(this.draft);
            }

            persistSelectedTheme(this.draft);
        },

        resetToBaseTheme() {
            applyBuiltinTheme('dim', 'dark');
            persistSelectedTheme(null);
        },

        importThemeData({ name, colorScheme, tokens }) {
            this.loadThemes();
            this.draft = makeCustomTheme({
                name: name || randomThemeName(),
                colorScheme: colorScheme === 'dark' ? 'dark' : 'light',
                tokens,
            });
            this.savedSnapshot = '';
            this.activeTab = 'editor';
            this.isOpen = true;
            this.applyDraftLive();
        },

        importHash(hash) {
            const encoded = hash.replace(/^#?theme=/, '');
            const decoded = decodeThemeHash(encoded);

            if (!decoded) {
                return false;
            }

            this.importThemeData(decoded);

            return true;
        },
    },
});
