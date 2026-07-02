<script setup>
import { computed, ref } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';
import { parseThemeCss, renderPluginCss, renderRuntimeCss } from '../lib/theme-css-renderer.js';
import { buildShareUrl, decodeThemeHash } from '../lib/theme-share-codec.js';

const store = useThemeGeneratorStore();

const format = ref('plugin');
const copied = ref(false);
const importText = ref('');
const importError = ref('');

const theme = computed(() => store.draft);

const cssOutput = computed(() => {
    if (!theme.value) {
        return '';
    }

    if (format.value === 'runtime') {
        return renderRuntimeCss(theme.value);
    }

    return renderPluginCss(theme.value);
});

const shareUrl = computed(() => (theme.value ? buildShareUrl(theme.value) : ''));

const copy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1500);
    } catch (error) {
        // clipboard may be blocked; ignore silently
    }
};

const download = () => {
    const blob = new Blob([cssOutput.value], { type: 'text/css' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${theme.value?.slug ?? 'theme'}.css`;
    anchor.click();
    URL.revokeObjectURL(url);
};

const runImport = () => {
    importError.value = '';
    const text = importText.value.trim();

    if (!text) {
        importError.value = 'Вставьте CSS или ссылку с #theme=';
        return;
    }

    const hashIndex = text.indexOf('#theme=');
    if (hashIndex >= 0) {
        const decoded = decodeThemeHash(text.slice(hashIndex + 7));
        if (decoded) {
            store.importThemeData(decoded);
            importText.value = '';
            return;
        }
    }

    const parsed = parseThemeCss(text);
    if (parsed) {
        store.importThemeData(parsed);
        importText.value = '';
        return;
    }

    importError.value = 'Не удалось распознать тему из вставленного текста.';
};

const close = () => { store.cssModalOpen = false; };
</script>

<template>
    <div v-if="store.cssModalOpen" class="modal modal-open">
        <div class="modal-box max-w-2xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold">CSS темы «{{ theme?.name }}»</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" @click="close">✕</button>
            </div>

            <div class="tabs tabs-box tabs-sm mb-3">
                <button type="button" class="tab" :class="{ 'tab-active': format === 'plugin' }" @click="format = 'plugin'">daisyUI plugin</button>
                <button type="button" class="tab" :class="{ 'tab-active': format === 'runtime' }" @click="format = 'runtime'">Runtime CSS</button>
            </div>

            <pre class="max-h-72 overflow-auto rounded-box bg-base-200 p-3 text-xs"><code>{{ cssOutput }}</code></pre>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary btn-sm" @click="copy(cssOutput)">
                    {{ copied ? 'Скопировано!' : 'Копировать CSS' }}
                </button>
                <button type="button" class="btn btn-sm" @click="download">Скачать .css</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="copy(shareUrl)">Копировать ссылку</button>
            </div>

            <div class="divider my-4 text-xs opacity-60">Импорт</div>

            <textarea
                v-model="importText"
                class="textarea textarea-bordered textarea-sm w-full font-mono text-xs"
                rows="3"
                placeholder="Вставьте daisyUI CSS, runtime CSS или ссылку с #theme=..."
            ></textarea>
            <p v-if="importError" class="mt-1 text-xs text-error">{{ importError }}</p>
            <div class="mt-2 flex justify-end">
                <button type="button" class="btn btn-outline btn-sm" @click="runImport">Импортировать</button>
            </div>
        </div>
        <div class="modal-backdrop" @click="close"></div>
    </div>
</template>
