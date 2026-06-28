<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import ModalFooterNext from '@/Components/Modals/Next/ModalFooterNext.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'created']);

const page = usePage();
const newsConfig = computed(() => page.props.news ?? {});
const openAiConfigured = computed(() => newsConfig.value.openAiConfigured === true);
const newsRoleOptions = computed(() => newsConfig.value.roleOptions ?? []);

const form = ref({
    title: '',
    cover_image: null,
    content_json: null,
    visibility_type: 'all',
    visible_roles: [],
});
const errors = ref({});
const processing = ref(false);
const previewHtml = ref('<p></p>');
const coverPreviewUrl = ref(null);
const aiFormatting = ref(false);
const aiError = ref('');
const quickEmoji = ['🔥', '✅', '📢', '⚠️', '💡'];

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Highlight.configure({ multicolor: false }),
        TextAlign.configure({ types: ['paragraph', 'heading'] }),
    ],
    content: '<p></p>',
});

const previewAuthorEmail = computed(() => page.props.auth?.user?.email ?? '');
const previewTitle = computed(() => form.value.title.trim() || 'Без заголовка');
const previewVisibilityLabel = computed(() => {
    if (form.value.visibility_type === 'all') {
        return 'Все роли';
    }

    if (!form.value.visible_roles.length) {
        return 'Роли не выбраны';
    }

    return form.value.visible_roles.join(', ');
});
const hasPreviewContent = computed(() => {
    const hasTitle = form.value.title.trim().length > 0;
    const plainText = previewHtml.value.replace(/<[^>]*>/g, '').trim();

    return hasTitle || plainText.length > 0 || Boolean(coverPreviewUrl.value);
});

const syncPreviewHtml = () => {
    previewHtml.value = editor.value?.getHTML() ?? '<p></p>';
};

const revokeCoverPreviewUrl = () => {
    if (!coverPreviewUrl.value) {
        return;
    }

    URL.revokeObjectURL(coverPreviewUrl.value);
    coverPreviewUrl.value = null;
};

const resetForm = () => {
    form.value = {
        title: '',
        cover_image: null,
        content_json: null,
        visibility_type: 'all',
        visible_roles: [],
    };
    errors.value = {};
    aiError.value = '';
    revokeCoverPreviewUrl();
    editor.value?.commands.clearContent(true);
    syncPreviewHtml();
};

const close = () => {
    emit('close');
};

const insertIcon = (icon) => {
    editor.value?.chain().focus().insertContent(`${icon} `).run();
};

const onCoverImageChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.value.cover_image = file;
    revokeCoverPreviewUrl();

    if (file) {
        coverPreviewUrl.value = URL.createObjectURL(file);
    }
};

const formatWithAi = async () => {
    if (!openAiConfigured.value || aiFormatting.value) {
        return;
    }

    const text = editor.value?.getText().trim() ?? '';
    if (text.length < 10) {
        aiError.value = 'Добавьте текст для оформления (минимум 10 символов).';
        return;
    }

    aiError.value = '';
    aiFormatting.value = true;

    try {
        const response = await axios.post(route('admin.news.format'), {
            text,
            title: form.value.title.trim() || null,
        });
        const payload = response?.data?.data;

        if (!payload?.content_json || !editor.value) {
            aiError.value = 'AI не вернул оформленный текст.';
            return;
        }

        editor.value.commands.setContent(payload.content_json);
        syncPreviewHtml();

        if (payload.title) {
            form.value.title = payload.title;
        }
    } catch (error) {
        aiError.value = error?.response?.data?.message || 'Не удалось оформить текст с помощью AI.';
    } finally {
        aiFormatting.value = false;
    }
};

const publishNews = async () => {
    if (!editor.value || processing.value) {
        return;
    }

    const contentJson = editor.value.getJSON();
    const hasTextContent = editor.value.getText().trim().length > 0;

    if (!hasTextContent) {
        errors.value = { content_json: 'Добавьте текст новости.' };
        return;
    }

    errors.value = {};
    processing.value = true;

    const formData = new FormData();
    formData.append('title', form.value.title);
    formData.append('content_json', JSON.stringify(contentJson));
    formData.append('visibility_type', form.value.visibility_type);

    if (form.value.visibility_type === 'roles') {
        form.value.visible_roles.forEach((role) => {
            formData.append('visible_roles[]', role);
        });
    }

    if (form.value.cover_image) {
        formData.append('cover_image', form.value.cover_image);
    }

    try {
        await axios.post(route('admin.news.store'), formData, {
            headers: { Accept: 'application/json' },
        });
        resetForm();
        emit('created');
        close();
    } catch (error) {
        errors.value = error?.response?.data?.errors ?? {
            content_json: error?.response?.data?.message || 'Не удалось опубликовать новость.',
        };
    } finally {
        processing.value = false;
    }
};

watch(
    () => props.show,
    (isShown) => {
        if (!isShown) {
            resetForm();
        }
    },
);

watch(
    () => editor.value,
    (currentEditor, _, onCleanup) => {
        if (!currentEditor) {
            return;
        }

        syncPreviewHtml();

        const handleUpdate = () => {
            syncPreviewHtml();
        };

        currentEditor.on('update', handleUpdate);

        onCleanup(() => {
            currentEditor.off('update', handleUpdate);
        });
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    revokeCoverPreviewUrl();
    editor.value?.destroy();
});
</script>

<template>
    <ModalNext :show="show" max-width="7xl" @close="close">
        <ModalHeaderNext title="Новая публикация" @close="close" />

        <ModalBodyNext>
            <div class="max-h-[calc(100dvh-10rem)] overflow-y-auto">
            <div class="grid gap-6 xl:grid-cols-2 xl:items-start p-4 sm:p-5">
                <div class="flex flex-col gap-5 min-w-0">
                    <p class="text-sm text-base-content/60 -mt-1">
                        Редактируйте слева — справа сразу видно, как новость появится в ленте.
                    </p>

                    <div class="flex flex-col gap-3">
                        <div class="join join-horizontal flex-wrap">
                            <button
                                v-for="icon in quickEmoji"
                                :key="icon"
                                type="button"
                                class="btn btn-sm join-item btn-ghost"
                                :title="`Вставить ${icon}`"
                                @click.prevent="insertIcon(icon)"
                            >
                                {{ icon }}
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <div class="join">
                                <button type="button" class="btn btn-sm join-item btn-outline min-w-9" :class="{ 'btn-active': editor?.isActive('bold') }" title="Жирный" @click.prevent="editor?.chain().focus().toggleBold().run()">
                                    <span class="font-bold">B</span>
                                </button>
                                <button type="button" class="btn btn-sm join-item btn-outline min-w-9" :class="{ 'btn-active': editor?.isActive('italic') }" title="Курсив" @click.prevent="editor?.chain().focus().toggleItalic().run()">
                                    <span class="italic">I</span>
                                </button>
                                <button type="button" class="btn btn-sm join-item btn-outline min-w-9" :class="{ 'btn-active': editor?.isActive('underline') }" title="Подчёркнутый" @click.prevent="editor?.chain().focus().toggleUnderline().run()">
                                    <span class="underline">U</span>
                                </button>
                                <button type="button" class="btn btn-sm join-item btn-outline min-w-9" :class="{ 'btn-active': editor?.isActive('strike') }" title="Зачёркнутый" @click.prevent="editor?.chain().focus().toggleStrike().run()">
                                    <span class="line-through">S</span>
                                </button>
                            </div>

                            <div class="join">
                                <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive({ textAlign: 'left' }) }" title="Влево" @click.prevent="editor?.chain().focus().setTextAlign('left').run()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg>
                                </button>
                                <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive({ textAlign: 'center' }) }" title="По центру" @click.prevent="editor?.chain().focus().setTextAlign('center').run()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M6.75 12h10.5m-10.5 5.25h10.5" /></svg>
                                </button>
                                <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive({ textAlign: 'right' }) }" title="Вправо" @click.prevent="editor?.chain().focus().setTextAlign('right').run()">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M10.5 12h10.5M3.75 17.25h16.5" /></svg>
                                </button>
                            </div>

                            <button
                                v-if="openAiConfigured"
                                type="button"
                                class="btn btn-sm btn-accent gap-2"
                                :disabled="aiFormatting"
                                @click.prevent="formatWithAi"
                            >
                                <span v-if="aiFormatting" class="loading loading-spinner loading-xs" />
                                <span>{{ aiFormatting ? 'Оформляем…' : 'Структурировать с AI' }}</span>
                            </button>
                        </div>

                        <p v-if="!openAiConfigured" class="text-xs text-base-content/50">
                            AI-оформление доступно после настройки OpenAI в разделе «Настройки».
                        </p>
                        <p v-if="aiError" class="text-xs text-error">{{ aiError }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="text-xs text-base-content/50 self-center mr-1">Структура:</span>
                        <div class="join">
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('heading', { level: 2 }) }" @click.prevent="editor?.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('heading', { level: 3 }) }" @click.prevent="editor?.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
                        </div>
                        <div class="join">
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('bulletList') }" @click.prevent="editor?.chain().focus().toggleBulletList().run()">•</button>
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('orderedList') }" @click.prevent="editor?.chain().focus().toggleOrderedList().run()">1.</button>
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('blockquote') }" @click.prevent="editor?.chain().focus().toggleBlockquote().run()">«</button>
                            <button type="button" class="btn btn-sm join-item btn-outline" :class="{ 'btn-active': editor?.isActive('highlight') }" @click.prevent="editor?.chain().focus().toggleHighlight().run()">
                                <span class="rounded-sm bg-warning/40 px-1">А</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Заголовок</legend>
                            <input v-model="form.title" type="text" class="input input-bordered w-full" placeholder="Краткий заголовок новости">
                            <InputError :message="errors.title" />
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Обложка</legend>
                            <input type="file" class="file-input file-input-bordered w-full" accept=".jpg,.jpeg,.png,.webp" @change="onCoverImageChange">
                            <InputError :message="errors.cover_image" />
                        </fieldset>
                    </div>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Аудитория</legend>
                        <div class="flex flex-wrap gap-3">
                            <label class="label cursor-pointer gap-2 rounded-box border border-base-300 px-3 py-2 has-checked:border-primary has-checked:bg-primary/5">
                                <input v-model="form.visibility_type" type="radio" class="radio radio-sm radio-primary" value="all">
                                <span class="label-text">Все роли</span>
                            </label>
                            <label class="label cursor-pointer gap-2 rounded-box border border-base-300 px-3 py-2 has-checked:border-primary has-checked:bg-primary/5">
                                <input v-model="form.visibility_type" type="radio" class="radio radio-sm radio-primary" value="roles">
                                <span class="label-text">Выбранные роли</span>
                            </label>
                        </div>

                        <div v-if="form.visibility_type === 'roles'" class="mt-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="role in newsRoleOptions"
                                    :key="role.value"
                                    class="label cursor-pointer gap-2 rounded-box border border-base-300 bg-base-100 px-3 py-1.5 has-checked:border-primary has-checked:bg-primary/10"
                                >
                                    <input v-model="form.visible_roles" type="checkbox" class="checkbox checkbox-sm checkbox-primary" :value="role.value">
                                    <span class="label-text text-sm">{{ role.label }}</span>
                                </label>
                            </div>
                        </div>
                        <InputError :message="errors.visibility_type || errors.visible_roles || errors['visible_roles.0']" />
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Текст</legend>
                        <div class="rounded-box border border-base-300 bg-base-200/30 p-4 min-h-52 focus-within:border-primary/40 transition-colors">
                            <EditorContent :editor="editor" class="news-prose prose prose-sm sm:prose-base max-w-none text-base-content [&_.ProseMirror]:min-h-40 [&_.ProseMirror]:outline-none" />
                        </div>
                        <InputError :message="errors.content_json" />
                    </fieldset>
                </div>

                <aside class="xl:sticky xl:top-0">
                    <div class="rounded-box border border-base-300 bg-base-200/30 p-4">
                        <p class="text-sm font-semibold text-base-content mb-3">Превью публикации</p>
                        <article class="card card-border border-base-300 bg-base-100 shadow-sm overflow-hidden" :class="{ 'opacity-60': !hasPreviewContent }">
                            <figure v-if="coverPreviewUrl" class="bg-base-200">
                                <img :src="coverPreviewUrl" alt="Превью обложки" class="w-full max-h-56 object-cover">
                            </figure>
                            <div class="card-body gap-4 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h3 class="card-title text-lg font-semibold leading-tight">{{ previewTitle }}</h3>
                                    <span class="badge badge-outline badge-xs shrink-0">{{ previewVisibilityLabel }}</span>
                                </div>
                                <div v-if="hasPreviewContent" class="news-prose prose prose-sm max-w-none text-base-content/90" v-html="previewHtml" />
                                <p v-else class="text-sm text-base-content/50">Заполните заголовок или текст — превью появится здесь.</p>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/50">
                                    <span>Сейчас</span>
                                    <span v-if="previewAuthorEmail" class="badge badge-ghost badge-xs">{{ previewAuthorEmail }}</span>
                                </div>
                            </div>
                        </article>
                    </div>
                </aside>
            </div>
            </div>
        </ModalBodyNext>

        <ModalFooterNext>
            <button type="button" class="btn btn-ghost" :disabled="processing" @click.prevent="close">
                Отмена
            </button>
            <button type="button" class="btn btn-primary min-w-36" :disabled="processing" @click.prevent="publishNews">
                <span v-if="processing" class="loading loading-spinner loading-sm" />
                <span>{{ processing ? 'Публикация…' : 'Опубликовать' }}</span>
            </button>
        </ModalFooterNext>
    </ModalNext>
</template>
