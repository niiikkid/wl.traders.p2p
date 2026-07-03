<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';
import ThemeListPanel from './ThemeListPanel.vue';
import ThemeEditorPanel from './ThemeEditorPanel.vue';
import ThemePreviewPanel from './ThemePreviewPanel.vue';
import ThemeCssExportModal from './ThemeCssExportModal.vue';

const store = useThemeGeneratorStore();

const panel = ref(null);
const compact = ref(false);
let resizeObserver = null;

const COMPACT_THRESHOLD = 880;

const savedFlash = ref(false);

const publishTitle = computed(() => {
    if (store.nameError) {
        return store.nameError;
    }

    if (!store.contrastOk) {
        return 'Низкий контраст: улучшите пары цветов перед публикацией';
    }

    return 'Опубликовать тему';
});

const observePanel = () => {
    if (!panel.value || typeof ResizeObserver === 'undefined') {
        return;
    }

    resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            compact.value = entry.contentRect.width < COMPACT_THRESHOLD;
        }
    });

    resizeObserver.observe(panel.value);
};

const attemptClose = () => {
    if (store.dirty) {
        const shouldSave = window.confirm('Есть несохранённые изменения. Сохранить черновик перед закрытием?');
        if (shouldSave) {
            store.saveDraft();
        }
    }

    store.close();
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && store.isOpen && !store.cssModalOpen) {
        attemptClose();
    }
};

const save = () => {
    store.saveDraft();
    savedFlash.value = true;
    setTimeout(() => { savedFlash.value = false; }, 1500);
};

const publishError = ref(false);

const publish = async () => {
    publishError.value = false;

    if (await store.publish()) {
        savedFlash.value = true;
        setTimeout(() => { savedFlash.value = false; }, 1500);
    } else if (!store.publishing) {
        publishError.value = true;
        setTimeout(() => { publishError.value = false; }, 2500);
    }
};

watch(() => store.isOpen, (open) => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        window.addEventListener('keydown', handleKeydown);
        nextTick(observePanel);
    } else {
        window.removeEventListener('keydown', handleKeydown);
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="tg-fade">
            <div v-if="store.isOpen" class="fixed inset-0 z-[9998] bg-black/50" @click="attemptClose"></div>
        </Transition>

        <Transition name="tg-slide">
            <section
                v-if="store.isOpen"
                ref="panel"
                data-theme-generator-root
                class="fixed inset-y-0 right-0 z-[9999] flex w-full flex-col bg-base-100 text-base-content shadow-2xl lg:w-[50vw] lg:min-w-[760px] lg:max-w-[1120px]"
                role="dialog"
                aria-label="Генератор тем"
            >
                <!-- Header -->
                <header class="flex items-center gap-2 border-b border-base-content/10 px-4 py-2.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="truncate text-sm font-semibold capitalize">{{ store.draft?.name ?? 'Тема' }}</h2>
                            <span
                                class="badge badge-xs"
                                :class="store.draft?.status === 'published' ? 'badge-success' : 'badge-ghost'"
                            >{{ store.draft?.status === 'published' ? 'опубликована' : 'черновик' }}</span>
                            <span v-if="store.dirty" class="badge badge-warning badge-xs">изменено</span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-ghost btn-sm" @click="store.cssModalOpen = true">Export</button>
                    <button type="button" class="btn btn-sm" @click="save">
                        {{ savedFlash ? 'Готово' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :class="{ 'btn-error': publishError }"
                        :disabled="!store.canPublish || store.publishing"
                        :title="publishTitle"
                        @click="publish"
                    >
                        <span v-if="store.publishing" class="loading loading-spinner loading-xs"></span>
                        {{ publishError ? 'Ошибка' : (store.publishing ? 'Публикация…' : 'Опубликовать') }}
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm btn-square" aria-label="Закрыть" @click="attemptClose">✕</button>
                </header>

                <!-- Compact tabs -->
                <div v-if="compact" class="tabs tabs-box tabs-sm justify-center border-b border-base-content/10 px-2 py-1.5">
                    <button type="button" class="tab" :class="{ 'tab-active': store.activeTab === 'themes' }" @click="store.activeTab = 'themes'">Темы</button>
                    <button type="button" class="tab" :class="{ 'tab-active': store.activeTab === 'editor' }" @click="store.activeTab = 'editor'">Редактор</button>
                    <button type="button" class="tab" :class="{ 'tab-active': store.activeTab === 'preview' }" @click="store.activeTab = 'preview'">Превью</button>
                </div>

                <!-- Body -->
                <div class="flex min-h-0 flex-1">
                    <!-- Compact: single active tab -->
                    <template v-if="compact">
                        <div class="min-h-0 flex-1 overflow-hidden">
                            <ThemeListPanel v-show="store.activeTab === 'themes'" />
                            <ThemeEditorPanel v-show="store.activeTab === 'editor'" />
                            <ThemePreviewPanel v-show="store.activeTab === 'preview'" />
                        </div>
                    </template>

                    <!-- Wide: three columns -->
                    <template v-else>
                        <div class="w-56 shrink-0 border-r border-base-content/10">
                            <ThemeListPanel />
                        </div>
                        <div class="w-80 shrink-0 border-r border-base-content/10">
                            <ThemeEditorPanel />
                        </div>
                        <div class="min-h-0 flex-1">
                            <ThemePreviewPanel />
                        </div>
                    </template>
                </div>

                <ThemeCssExportModal />
            </section>
        </Transition>
    </Teleport>
</template>
