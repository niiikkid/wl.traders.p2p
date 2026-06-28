<script setup>
import { ref, watch, onMounted } from 'vue';
import { highlightCode } from '@/composables/useShikiHighlighter.js';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

const props = defineProps({
    code: {
        type: String,
        required: true,
    },
    lang: {
        type: String,
        default: 'jsonc',
    },
    label: {
        type: String,
        default: '',
    },
});

const html = ref('');
const { copy, copied } = useAppClipboard();

const render = async () => {
    try {
        html.value = await highlightCode(props.code, props.lang);
    } catch (error) {
        html.value = '';
    }
};

onMounted(render);
watch(() => [props.code, props.lang], render);
</script>

<template>
    <div class="group relative overflow-hidden rounded-lg border border-base-300 bg-[#22272e]" data-code-block>
        <div class="flex items-center justify-between border-b border-base-300/40 px-3 py-1.5">
            <span class="text-[11px] font-medium uppercase tracking-wide text-base-content/50">
                {{ label || lang }}
            </span>
            <button
                type="button"
                class="btn btn-ghost btn-xs gap-1 text-base-content/60 hover:text-base-content"
                @click="copy(props.code)"
            >
                <svg v-if="!copied" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 18 20" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 1 1 0 2Zm0-5H5a1 1 0 0 1 0-2h2V2h4v2h2a1 1 0 1 1 0 2Z" />
                </svg>
                <svg v-else class="h-3.5 w-3.5 text-success" fill="none" viewBox="0 0 16 12" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 5.917 5.724 10.5 15 1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                </svg>
                {{ copied ? 'Скопировано' : 'Копировать' }}
            </button>
        </div>

        <div v-if="html" class="code-block-body overflow-x-auto text-sm" v-html="html" />
        <pre v-else class="code-block-body overflow-x-auto px-4 py-3 text-sm text-base-content/80"><code>{{ code }}</code></pre>
    </div>
</template>
