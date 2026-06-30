<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import {ref, onMounted, onBeforeUnmount, nextTick} from 'vue';
import axios from 'axios';
import ApiDocumentation from '@/Pages/Integration/Components/ApiDocumentation.vue';
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import PageToolbar from '@/Components/Table/PageToolbar.vue';
import PageToolbarAction from '@/Components/Table/PageToolbarAction.vue';
import {useModalStore} from "@/store/modal.js";

const pageProps = usePage().props;
const apiToken = ref('');
const webhookSecret = ref('');
const hasApiToken = ref(Boolean(pageProps.hasApiToken));
const hasWebhookSecret = ref(Boolean(pageProps.hasWebhookSecret));

const { copy, copied } = useAppClipboard();
const modalStore = useModalStore();

const hasWindow = typeof window !== 'undefined';

const regenerating = ref(false);
const regeneratingWebhookSecret = ref(false);
const downloadingDocumentation = ref(false);

const scrollToHash = (hashOverride = null) => {
    if (!hasWindow) {
        return;
    }

    const hash = hashOverride ?? window.location.hash;

    if (!hash) {
        return;
    }

    const target = document.querySelector(hash);
    if (target) {
        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
};

const normalizeDocumentationUrl = () => {
    if (!hasWindow) {
        return;
    }

    const url = new URL(window.location.href);

    if (url.searchParams.has('tab')) {
        url.searchParams.delete('tab');
        window.history.replaceState({}, '', url.toString());
    }
};

const handleHashChange = () => {
    if (!hasWindow) {
        return;
    }

    nextTick(() => scrollToHash());
};

onMounted(() => {
    normalizeDocumentationUrl();
    nextTick(() => scrollToHash());

    window.addEventListener('hashchange', handleHashChange);
});

onBeforeUnmount(() => {
    if (!hasWindow) {
        return;
    }
    window.removeEventListener('hashchange', handleHashChange);
});

const regenerateToken = async () => {
    if (regenerating.value) {
        return;
    }

    regenerating.value = true;

    try {
        const response = await axios.post(route('integration.regenerate-token'));
        const newToken = response?.data?.data?.token;

        if (typeof newToken === 'string' && newToken.length > 0) {
            apiToken.value = newToken;
            hasApiToken.value = true;
        }
    } catch (error) {
        console.error('Не удалось перегенерировать токен:', error);
    } finally {
        regenerating.value = false;
    }
};

const regenerateWebhookSecret = async () => {
    if (regeneratingWebhookSecret.value) {
        return;
    }

    regeneratingWebhookSecret.value = true;

    try {
        const response = await axios.post(route('integration.regenerate-webhook-secret'));
        const newSecret = response?.data?.data?.webhook_secret;

        if (typeof newSecret === 'string' && newSecret.length > 0) {
            webhookSecret.value = newSecret;
            hasWebhookSecret.value = true;
        }
    } catch (error) {
        console.error('Не удалось перегенерировать webhook secret:', error);
    } finally {
        regeneratingWebhookSecret.value = false;
    }
};

const openRegenerateConfirm = () => {
    modalStore.openConfirmModal({
        title: 'Перегенерировать API токен?',
        body: 'Старый токен станет недействительным. Действие невозможно отменить.',
        confirm_button_name: 'Перегенерировать',
        cancel_button_name: 'Отмена',
        confirm: regenerateToken,
    });
};

const openRegenerateWebhookSecretConfirm = () => {
    modalStore.openConfirmModal({
        title: 'Перегенерировать Webhook secret?',
        body: 'Старый секрет перестанет подходить для проверки подписи callback’ов. Действие невозможно отменить.',
        confirm_button_name: 'Перегенерировать',
        cancel_button_name: 'Отмена',
        confirm: regenerateWebhookSecret,
    });
};

const normalizeText = (value = '') => value.replace(/\s+/g, ' ').trim();

const pushMarkdownLine = (lines, value = '') => {
    const line = value ?? '';

    if (!line) {
        if (lines.length && lines[lines.length - 1] !== '') {
            lines.push('');
        }
        return;
    }

    lines.push(line);
};

const getMarkdownTable = (tableElement) => {
    const rows = Array.from(tableElement.querySelectorAll('tr'))
        .map((row) => Array.from(row.querySelectorAll('th, td')).map((cell) => normalizeText(cell.innerText)));

    if (!rows.length || !rows[0].length) {
        return '';
    }

    const [header, ...bodyRows] = rows;
    const separator = header.map(() => '---');
    const markdownRows = [
        `| ${header.join(' | ')} |`,
        `| ${separator.join(' | ')} |`,
        ...bodyRows.map((row) => `| ${row.join(' | ')} |`)
    ];

    return `${markdownRows.join('\n')}\n`;
};

const getHeadingPrefix = (tagName) => {
    if (tagName === 'H1') {
        return '#';
    }

    if (tagName === 'H2') {
        return '##';
    }

    if (tagName === 'H3') {
        return '###';
    }

    if (tagName === 'H4') {
        return '####';
    }

    return '#####';
};

const appendEndpointLine = (element, lines) => {
    const badge = element.querySelector(':scope > span.badge');
    const endpointCode = element.querySelector(':scope > code');

    if (!badge || !endpointCode) {
        return false;
    }

    const method = normalizeText(badge.innerText);
    const endpoint = normalizeText(endpointCode.innerText);

    if (!method || !endpoint) {
        return false;
    }

    pushMarkdownLine(lines, `- \`${method}\` \`${endpoint}\``);
    pushMarkdownLine(lines);

    return true;
};

const walkDocumentationNode = (element, lines) => {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    if (appendEndpointLine(element, lines)) {
        return;
    }

    const tagName = element.tagName;

    if (element.classList.contains('collapse-title')) {
        const title = normalizeText(element.textContent ?? '');
        if (title) {
            pushMarkdownLine(lines, `#### ${title}`);
            pushMarkdownLine(lines);
        }
        return;
    }

    if (/^H[1-5]$/.test(tagName)) {
        const title = normalizeText(element.innerText);
        if (title) {
            pushMarkdownLine(lines, `${getHeadingPrefix(tagName)} ${title}`);
            pushMarkdownLine(lines);
        }
        return;
    }

    if (tagName === 'P') {
        const text = normalizeText(element.textContent ?? '');
        if (text) {
            pushMarkdownLine(lines, text);
            pushMarkdownLine(lines);
        }
        return;
    }

    if (tagName === 'UL' || tagName === 'OL') {
        const items = Array.from(element.querySelectorAll(':scope > li'))
            .map((item) => normalizeText(item.textContent ?? ''))
            .filter(Boolean);

        items.forEach((itemText) => {
            pushMarkdownLine(lines, `- ${itemText}`);
        });

        if (items.length) {
            pushMarkdownLine(lines);
        }
        return;
    }

    if (tagName === 'TABLE') {
        const tableMarkdown = getMarkdownTable(element);
        if (tableMarkdown) {
            pushMarkdownLine(lines, tableMarkdown.trimEnd());
            pushMarkdownLine(lines);
        }
        return;
    }

    if (tagName === 'PRE') {
        const code = (element.textContent ?? '').trim();
        if (code) {
            pushMarkdownLine(lines, '```json');
            pushMarkdownLine(lines, code);
            pushMarkdownLine(lines, '```');
            pushMarkdownLine(lines);
        }
        return;
    }

    if (['DIV', 'SECTION', 'ARTICLE'].includes(tagName) && element.children.length === 0) {
        const text = normalizeText(element.textContent ?? '');
        if (text) {
            pushMarkdownLine(lines, text);
            pushMarkdownLine(lines);
        }
        return;
    }

    Array.from(element.children).forEach((child) => {
        walkDocumentationNode(child, lines);
    });
};

const buildDocumentationMarkdown = () => {
    const root = document.querySelector('[data-api-docs-markdown-root]');

    if (!root) {
        return '';
    }

    const sections = Array.from(root.querySelectorAll('article'));
    const lines = ['# API Интеграция', ''];

    sections.forEach((section) => {
        walkDocumentationNode(section, lines);
    });

    while (lines.length && lines[lines.length - 1] === '') {
        lines.pop();
    }

    return lines.join('\n');
};

const downloadDocumentation = async () => {
    if (downloadingDocumentation.value) {
        return;
    }

    downloadingDocumentation.value = true;

    try {
        await nextTick();
        await new Promise((resolve) => requestAnimationFrame(resolve));

        const markdown = buildDocumentationMarkdown();
        if (!markdown) {
            return;
        }

        const blob = new Blob([markdown], {type: 'text/markdown;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'api-integration-documentation.md';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } finally {
        downloadingDocumentation.value = false;
    }
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <Head title="API Интеграция"/>

    <div class="antialiased">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6 flex items-center justify-between gap-4">
                <h2 class="text-3xl font-bold text-base-content">API Интеграция</h2>
                <PageToolbar>
                    <PageToolbarAction
                        icon="download"
                        title="Скачать документацию"
                        label="Скачать документацию"
                        :loading="downloadingDocumentation"
                        :disabled="downloadingDocumentation"
                        @click="downloadDocumentation"
                    />
                </PageToolbar>
            </div>

            <div class="grid gap-6 mb-6 lg:grid-cols-2">
                <div class="card w-full bg-base-100 shadow">
                    <div class="card-body gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="card-title">API токен</h3>
                                <p class="text-sm text-base-content/70">
                                    Используется в заголовке <code class="bg-base-200 px-1 rounded">Access-Token</code> для запросов к API.
                                </p>
                            </div>
                            <span class="badge shrink-0 whitespace-nowrap" :class="hasApiToken ? 'badge-success' : 'badge-warning'">
                                {{ hasApiToken ? 'создан' : 'не создан' }}
                            </span>
                        </div>

                        <div v-if="apiToken" class="alert alert-warning text-sm">
                            Скопируйте токен сейчас. После обновления страницы он больше не будет показан.
                        </div>

                        <div class="relative">
                            <input
                                id="api-key"
                                type="text"
                                class="bg-base-200 border border-base-300 text-base-content/70 text-sm rounded-xl focus:ring-primary focus:border-primary block w-full p-2.5 pr-24"
                                :value="apiToken || 'Скрыт из соображений безопасности'"
                                disabled
                                readonly
                            >
                            <div class="absolute end-2 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                <button
                                    @click="copy(apiToken)"
                                    class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                    :class="{ 'opacity-50 pointer-events-none': !apiToken }"
                                    :disabled="!apiToken"
                                    type="button"
                                    aria-label="Скопировать API токен"
                                >
                                    <span v-if="!copied">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                            <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 1 1 0 2Zm0-5H5a1 1 0 0 1 0-2h2V2h4v2h2a1 1 0 1 1 0 2Z"/>
                                        </svg>
                                    </span>
                                    <span v-else class="inline-flex items-center">
                                        <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 12">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5.917 5.724 10.5 15 1.5"/>
                                        </svg>
                                    </span>
                                </button>
                                <button
                                    @click="openRegenerateConfirm"
                                    class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                    :class="{ 'opacity-50 pointer-events-none': regenerating }"
                                    :disabled="regenerating"
                                    type="button"
                                    aria-label="Перегенерировать API токен"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card w-full bg-base-100 shadow">
                    <div class="card-body gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="card-title">Webhook secret</h3>
                                <p class="text-sm text-base-content/70">
                                    Используется для проверки HMAC-подписи callback’ов.
                                </p>
                            </div>
                            <span class="badge shrink-0 whitespace-nowrap" :class="hasWebhookSecret ? 'badge-success' : 'badge-warning'">
                                {{ hasWebhookSecret ? 'создан' : 'не создан' }}
                            </span>
                        </div>

                        <div v-if="webhookSecret" class="alert alert-warning text-sm">
                            Скопируйте secret сейчас. После обновления страницы он больше не будет показан.
                        </div>

                        <div class="relative">
                            <input
                                id="webhook-secret"
                                type="text"
                                class="bg-base-200 border border-base-300 text-base-content/70 text-sm rounded-xl focus:ring-primary focus:border-primary block w-full p-2.5 pr-24"
                                :value="webhookSecret || 'Скрыт из соображений безопасности'"
                                disabled
                                readonly
                            >
                            <div class="absolute end-2 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                <button
                                    @click="copy(webhookSecret)"
                                    class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                    :class="{ 'opacity-50 pointer-events-none': !webhookSecret }"
                                    :disabled="!webhookSecret"
                                    type="button"
                                    aria-label="Скопировать Webhook secret"
                                >
                                    <span v-if="!copied">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 18 20">
                                            <path d="M16 1h-3.278A1.992 1.992 0 0 0 11 0H7a1.993 1.993 0 0 0-1.722 1H2a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-3 14H5a1 1 0 0 1 0-2h8a1 1 0 0 1 0 2Zm0-4H5a1 1 0 0 1 0-2h8a1 1 0 1 1 0 2Zm0-5H5a1 1 0 0 1 0-2h2V2h4v2h2a1 1 0 1 1 0 2Z"/>
                                        </svg>
                                    </span>
                                    <span v-else class="inline-flex items-center">
                                        <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 12">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5.917 5.724 10.5 15 1.5"/>
                                        </svg>
                                    </span>
                                </button>
                                <button
                                    @click="openRegenerateWebhookSecretConfirm"
                                    class="text-base-content/70 hover:bg-base-200 rounded-xl p-2 inline-flex items-center justify-center"
                                    :class="{ 'opacity-50 pointer-events-none': regeneratingWebhookSecret }"
                                    :disabled="regeneratingWebhookSecret"
                                    type="button"
                                    aria-label="Перегенерировать Webhook secret"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ApiDocumentation />
        </div>
    </div>

    <ConfirmModal />
</template>
