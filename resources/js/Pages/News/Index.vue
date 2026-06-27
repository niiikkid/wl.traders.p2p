<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, reactive, ref, watch} from 'vue';
import {EditorContent, useEditor} from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import DateTime from '@/Components/DateTime.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import TableEmptyState from '@/Components/TableEmptyState.vue';
import {useModalStore} from '@/store/modal.js';

const props = defineProps({
    news: {
        type: Object,
        required: true,
    },
    canManageNews: {
        type: Boolean,
        required: true,
    },
    newsRoleOptions: {
        type: Array,
        required: true,
    },
    openAiConfigured: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const form = useForm({
    title: '',
    cover_image: null,
    content_json: null,
    visibility_type: 'all',
    visible_roles: [],
});
const modalStore = useModalStore();
const deleteForm = useForm({});
const newsElements = new Map();
const visibilityTimers = new Map();
const trackedPostIds = new Set();
const pendingPostIds = new Set();
const minimumVisiblePart = 0.6;
const minimumVisibleMs = 1000;
const flushDelayMs = 700;
let intersectionObserver = null;
let flushTimeout = null;
const reactionsProcessing = reactive({});
const previewHtml = ref('<p></p>');
const coverPreviewUrl = ref(null);
const aiFormatting = ref(false);
const aiError = ref('');

const quickEmoji = ['🔥', '✅', '📢', '⚠️', '💡'];

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Highlight.configure({
            multicolor: false,
        }),
        TextAlign.configure({
            types: ['paragraph', 'heading'],
        }),
    ],
    content: '<p></p>',
});

const posts = computed(() => props.news?.data ?? []);
const totalPosts = computed(() => props.news?.meta?.total ?? posts.value.length);
const previewAuthorEmail = computed(() => page.props.auth?.user?.email ?? '');
const previewTitle = computed(() => form.title.trim() || 'Без заголовка');
const previewVisibilityLabel = computed(() => {
    if (form.visibility_type === 'all') {
        return 'Все роли';
    }

    if (!form.visible_roles.length) {
        return 'Роли не выбраны';
    }

    return form.visible_roles.join(', ');
});
const hasPreviewContent = computed(() => {
    const hasTitle = form.title.trim().length > 0;
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

const clearVisibilityTimer = (postId) => {
    const timer = visibilityTimers.get(postId);
    if (!timer) {
        return;
    }

    clearTimeout(timer);
    visibilityTimers.delete(postId);
};

const flushTrackedViews = () => {
    if (flushTimeout) {
        clearTimeout(flushTimeout);
        flushTimeout = null;
    }

    if (pendingPostIds.size === 0) {
        return;
    }

    const postIds = Array.from(pendingPostIds);
    pendingPostIds.clear();

    axios.post(route('news.views.store'), {
        post_ids: postIds,
    }).catch(() => {
        // do nothing: failed events are non-critical for UI
    });
};

const scheduleTrackedViewsFlush = () => {
    if (flushTimeout) {
        return;
    }

    flushTimeout = setTimeout(() => {
        flushTrackedViews();
    }, flushDelayMs);
};

const markPostAsViewed = (postId) => {
    if (trackedPostIds.has(postId)) {
        return;
    }

    trackedPostIds.add(postId);
    pendingPostIds.add(postId);
    clearVisibilityTimer(postId);
    scheduleTrackedViewsFlush();

    const element = newsElements.get(postId);
    if (intersectionObserver && element) {
        intersectionObserver.unobserve(element);
    }
};

const handleIntersection = (entries) => {
    entries.forEach((entry) => {
        const postId = Number(entry.target.dataset.postId);
        if (!Number.isInteger(postId) || trackedPostIds.has(postId)) {
            return;
        }

        const canTrackView = document.visibilityState === 'visible'
            && entry.isIntersecting
            && entry.intersectionRatio >= minimumVisiblePart;

        if (!canTrackView) {
            clearVisibilityTimer(postId);
            return;
        }

        if (visibilityTimers.has(postId)) {
            return;
        }

        const timer = setTimeout(() => {
            markPostAsViewed(postId);
        }, minimumVisibleMs);

        visibilityTimers.set(postId, timer);
    });
};

const observeNewsElement = (element, postId) => {
    const normalizedPostId = Number(postId);
    if (!Number.isInteger(normalizedPostId)) {
        return;
    }

    const oldElement = newsElements.get(normalizedPostId);
    if (oldElement && intersectionObserver) {
        intersectionObserver.unobserve(oldElement);
    }

    if (!element) {
        clearVisibilityTimer(normalizedPostId);
        newsElements.delete(normalizedPostId);
        return;
    }

    element.dataset.postId = String(normalizedPostId);
    newsElements.set(normalizedPostId, element);

    if (!intersectionObserver) {
        intersectionObserver = new IntersectionObserver(handleIntersection, {
            threshold: [minimumVisiblePart],
        });
    }

    if (!trackedPostIds.has(normalizedPostId)) {
        intersectionObserver.observe(element);
    }
};

const insertIcon = (icon) => {
    if (!editor.value) {
        return;
    }

    editor.value.chain().focus().insertContent(`${icon} `).run();
};

const onCoverImageChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.cover_image = file;

    revokeCoverPreviewUrl();

    if (file) {
        coverPreviewUrl.value = URL.createObjectURL(file);
    }
};

const formatWithAi = async () => {
    if (!props.openAiConfigured || aiFormatting.value) {
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
            title: form.title.trim() || null,
        });

        const payload = response?.data?.data;

        if (!payload?.content_json || !editor.value) {
            aiError.value = 'AI не вернул оформленный текст.';
            return;
        }

        editor.value.commands.setContent(payload.content_json);
        syncPreviewHtml();

        if (payload.title) {
            form.title = payload.title;
        }
    } catch (error) {
        aiError.value = error?.response?.data?.message || 'Не удалось оформить текст с помощью AI.';
    } finally {
        aiFormatting.value = false;
    }
};

const publishNews = () => {
    if (!editor.value) {
        return;
    }

    const contentJson = editor.value.getJSON();
    const hasTextContent = editor.value.getText().trim().length > 0;

    if (!hasTextContent) {
        form.setError('content_json', 'Добавьте текст новости.');
        return;
    }

    form.clearErrors('content_json');
    form.content_json = contentJson;

    if (form.visibility_type === 'all') {
        form.visible_roles = [];
    }

    form.post(route('admin.news.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            revokeCoverPreviewUrl();
            if (editor.value) {
                editor.value.commands.clearContent(true);
                syncPreviewHtml();
            }
        },
    });
};

const changePage = (pageNumber) => {
    router.get(
        route(route().current()),
        {page: pageNumber},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const deleteNews = (post) => {
    modalStore.openConfirmModal({
        title: 'Удалить новость?',
        body: 'Новость и картинка будут удалены без возможности восстановления.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            deleteForm.delete(route('admin.news.destroy', post.id), {
                preserveScroll: true,
            });
        },
    });
};

const reactToNews = (post, reaction) => {
    if (!post?.id || reactionsProcessing[post.id]) {
        return;
    }

    reactionsProcessing[post.id] = true;

    axios.post(route('news.reactions.store'), {
        post_id: post.id,
        reaction,
    }).then((response) => {
        const payload = response?.data?.post;
        if (!payload || Number(payload.id) !== Number(post.id)) {
            return;
        }

        post.likes_count = Number(payload.likes_count ?? post.likes_count ?? 0);
        post.dislikes_count = Number(payload.dislikes_count ?? post.dislikes_count ?? 0);
        post.user_reaction = payload.user_reaction ?? reaction;
    }).catch(() => {
        // do nothing: failed events are non-critical for UI
    }).finally(() => {
        reactionsProcessing[post.id] = false;
    });
};

onBeforeUnmount(() => {
    window.removeEventListener('pagehide', flushTrackedViews);
    flushTrackedViews();
    revokeCoverPreviewUrl();

    visibilityTimers.forEach((timer) => {
        clearTimeout(timer);
    });
    visibilityTimers.clear();
    newsElements.clear();
    pendingPostIds.clear();
    if (flushTimeout) {
        clearTimeout(flushTimeout);
        flushTimeout = null;
    }
    if (intersectionObserver) {
        intersectionObserver.disconnect();
        intersectionObserver = null;
    }

    if (editor.value) {
        editor.value.destroy();
    }
});

onMounted(() => {
    window.addEventListener('pagehide', flushTrackedViews);
});

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
    {immediate: true},
);

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Новости" />

        <MainTableSection title="Новости" :data="news" :paginate="true" :display-pagination="false">
            <template #header>
                <section
                    v-if="canManageNews"
                    class="card card-border border-primary/20 bg-base-100 shadow-sm"
                    aria-labelledby="news-compose-title"
                >
                    <div class="card-body gap-6 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="rounded-box bg-primary/10 p-2.5 text-primary shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h2 id="news-compose-title" class="text-lg font-semibold text-base-content">
                                        Новая публикация
                                    </h2>
                                    <p class="text-sm text-base-content/60 mt-0.5">
                                        Редактируйте слева — справа сразу видно, как новость появится в ленте.
                                    </p>
                                </div>
                            </div>
                            <span class="badge badge-primary badge-outline badge-sm shrink-0">
                                Администратор
                            </span>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
                            <div class="flex flex-col gap-5 min-w-0">
                                <div class="divider my-0 text-xs text-base-content/50">Оформление текста</div>

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
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline min-w-9"
                                                :class="{ 'btn-active': editor?.isActive('bold') }"
                                                title="Жирный"
                                                @click.prevent="editor?.chain().focus().toggleBold().run()"
                                            >
                                                <span class="font-bold">B</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline min-w-9"
                                                :class="{ 'btn-active': editor?.isActive('italic') }"
                                                title="Курсив"
                                                @click.prevent="editor?.chain().focus().toggleItalic().run()"
                                            >
                                                <span class="italic">I</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline min-w-9"
                                                :class="{ 'btn-active': editor?.isActive('underline') }"
                                                title="Подчёркнутый"
                                                @click.prevent="editor?.chain().focus().toggleUnderline().run()"
                                            >
                                                <span class="underline">U</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline min-w-9"
                                                :class="{ 'btn-active': editor?.isActive('strike') }"
                                                title="Зачёркнутый"
                                                @click.prevent="editor?.chain().focus().toggleStrike().run()"
                                            >
                                                <span class="line-through">S</span>
                                            </button>
                                        </div>

                                        <div class="join">
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline"
                                                :class="{ 'btn-active': editor?.isActive({ textAlign: 'left' }) }"
                                                title="Влево"
                                                @click.prevent="editor?.chain().focus().setTextAlign('left').run()"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline"
                                                :class="{ 'btn-active': editor?.isActive({ textAlign: 'center' }) }"
                                                title="По центру"
                                                @click.prevent="editor?.chain().focus().setTextAlign('center').run()"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M6.75 12h10.5m-10.5 5.25h10.5" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm join-item btn-outline"
                                                :class="{ 'btn-active': editor?.isActive({ textAlign: 'right' }) }"
                                                title="Вправо"
                                                @click.prevent="editor?.chain().focus().setTextAlign('right').run()"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M10.5 12h10.5M3.75 17.25h16.5" />
                                                </svg>
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
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                            </svg>
                                            <span>{{ aiFormatting ? 'Оформляем…' : 'Структурировать с AI' }}</span>
                                        </button>
                                    </div>

                                    <p v-if="!openAiConfigured" class="text-xs text-base-content/50">
                                        AI-оформление доступно после настройки OpenAI в разделе «Настройки».
                                    </p>
                                    <p v-else class="text-xs text-base-content/50">
                                        AI расставит заголовки, списки, выделения и акценты — без изменения фактов.
                                    </p>
                                    <p v-if="aiError" class="text-xs text-error">
                                        {{ aiError }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs text-base-content/50 self-center mr-1">Структура:</span>
                                    <div class="join">
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('heading', { level: 2 }) }"
                                            title="Заголовок раздела"
                                            @click.prevent="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                                        >
                                            H2
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('heading', { level: 3 }) }"
                                            title="Подзаголовок"
                                            @click.prevent="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                                        >
                                            H3
                                        </button>
                                    </div>
                                    <div class="join">
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('bulletList') }"
                                            title="Маркированный список"
                                            @click.prevent="editor?.chain().focus().toggleBulletList().run()"
                                        >
                                            •
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('orderedList') }"
                                            title="Нумерованный список"
                                            @click.prevent="editor?.chain().focus().toggleOrderedList().run()"
                                        >
                                            1.
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('blockquote') }"
                                            title="Цитата / важное"
                                            @click.prevent="editor?.chain().focus().toggleBlockquote().run()"
                                        >
                                            «
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            :class="{ 'btn-active': editor?.isActive('highlight') }"
                                            title="Выделить важное"
                                            @click.prevent="editor?.chain().focus().toggleHighlight().run()"
                                        >
                                            <span class="rounded-sm bg-warning/40 px-1">А</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm join-item btn-outline"
                                            title="Разделитель"
                                            @click.prevent="editor?.chain().focus().setHorizontalRule().run()"
                                        >
                                            —
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <fieldset class="fieldset">
                                        <legend class="fieldset-legend">Заголовок</legend>
                                        <input
                                            v-model="form.title"
                                            type="text"
                                            class="input input-bordered w-full"
                                            placeholder="Краткий заголовок новости"
                                        >
                                        <InputError :message="form.errors.title" />
                                    </fieldset>

                                    <fieldset class="fieldset">
                                        <legend class="fieldset-legend">Обложка</legend>
                                        <input
                                            type="file"
                                            class="file-input file-input-bordered w-full"
                                            accept=".jpg,.jpeg,.png,.webp"
                                            @change="onCoverImageChange"
                                        >
                                        <p class="label text-xs text-base-content/50">
                                            JPG, PNG или WebP — по желанию
                                        </p>
                                        <InputError :message="form.errors.cover_image" />
                                    </fieldset>
                                </div>

                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Аудитория</legend>
                                    <div class="flex flex-wrap gap-3">
                                        <label class="label cursor-pointer gap-2 rounded-box border border-base-300 px-3 py-2 has-checked:border-primary has-checked:bg-primary/5">
                                            <input
                                                v-model="form.visibility_type"
                                                type="radio"
                                                class="radio radio-sm radio-primary"
                                                value="all"
                                            >
                                            <span class="label-text">Все роли</span>
                                        </label>
                                        <label class="label cursor-pointer gap-2 rounded-box border border-base-300 px-3 py-2 has-checked:border-primary has-checked:bg-primary/5">
                                            <input
                                                v-model="form.visibility_type"
                                                type="radio"
                                                class="radio radio-sm radio-primary"
                                                value="roles"
                                            >
                                            <span class="label-text">Выбранные роли</span>
                                        </label>
                                    </div>

                                    <div
                                        v-if="form.visibility_type === 'roles'"
                                        class="mt-3 rounded-box border border-base-300 bg-base-200/40 p-3"
                                    >
                                        <div class="flex flex-wrap gap-2">
                                            <label
                                                v-for="role in newsRoleOptions"
                                                :key="role.value"
                                                class="label cursor-pointer gap-2 rounded-box border border-base-300 bg-base-100 px-3 py-1.5 has-checked:border-primary has-checked:bg-primary/10"
                                            >
                                                <input
                                                    v-model="form.visible_roles"
                                                    type="checkbox"
                                                    class="checkbox checkbox-sm checkbox-primary"
                                                    :value="role.value"
                                                >
                                                <span class="label-text text-sm">{{ role.label }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.visibility_type || form.errors.visible_roles || form.errors['visible_roles.0']" />
                                </fieldset>

                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Текст</legend>
                                    <div class="rounded-box border border-base-300 bg-base-200/30 p-4 min-h-52 focus-within:border-primary/40 transition-colors">
                                        <EditorContent :editor="editor" class="news-prose prose prose-sm sm:prose-base max-w-none text-base-content [&_.ProseMirror]:min-h-40 [&_.ProseMirror]:outline-none" />
                                    </div>
                                    <InputError :message="form.errors.content_json" />
                                </fieldset>

                                <div class="card-actions justify-end pt-1">
                                    <button
                                        type="button"
                                        class="btn btn-primary min-w-36"
                                        :disabled="form.processing"
                                        @click.prevent="publishNews"
                                    >
                                        <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                                        <span>{{ form.processing ? 'Публикация…' : 'Опубликовать' }}</span>
                                    </button>
                                </div>
                            </div>

                            <aside class="xl:sticky xl:top-4">
                                <div class="rounded-box border border-base-300 bg-base-200/30 p-4">
                                    <div class="flex items-center justify-between gap-2 mb-4">
                                        <div>
                                            <p class="text-sm font-semibold text-base-content">Превью публикации</p>
                                            <p class="text-xs text-base-content/50 mt-0.5">Обновляется при вводе</p>
                                        </div>
                                        <span class="badge badge-ghost badge-sm">Live</span>
                                    </div>

                                    <article
                                        class="card card-border border-base-300 bg-base-100 shadow-sm overflow-hidden"
                                        :class="{ 'opacity-60': !hasPreviewContent }"
                                    >
                                        <figure v-if="coverPreviewUrl" class="bg-base-200">
                                            <img
                                                :src="coverPreviewUrl"
                                                alt="Превью обложки"
                                                class="w-full max-h-56 object-cover"
                                            >
                                        </figure>

                                        <div class="card-body gap-4 p-4 sm:p-5">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <h3 class="card-title text-lg sm:text-xl font-semibold leading-tight text-base-content">
                                                    {{ previewTitle }}
                                                </h3>
                                                <span class="badge badge-outline badge-xs shrink-0">
                                                    {{ previewVisibilityLabel }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="hasPreviewContent"
                                                class="news-prose prose prose-sm max-w-none text-base-content/90 leading-relaxed"
                                                v-html="previewHtml"
                                            />
                                            <p v-else class="text-sm text-base-content/50">
                                                Заполните заголовок, текст или добавьте обложку — превью появится здесь.
                                            </p>

                                            <div class="divider my-0" />

                                            <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/50">
                                                <span>Сейчас</span>
                                                <span v-if="previewAuthorEmail" class="badge badge-ghost badge-xs font-normal">
                                                    {{ previewAuthorEmail }}
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    0
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </aside>
                        </div>
                    </div>
                </section>

                <div v-if="canManageNews && posts.length > 0" class="divider text-sm text-base-content/50">
                    Лента · {{ totalPosts }}
                </div>
            </template>

            <template #body>
                <div class="flex flex-col gap-5">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        :ref="(element) => observeNewsElement(element, post.id)"
                        class="card card-border border-base-300 bg-base-100 shadow-sm overflow-hidden"
                    >
                        <figure v-if="post.cover_image_url" class="bg-base-200">
                            <img
                                :src="post.cover_image_url"
                                alt="Обложка новости"
                                class="w-full max-h-80 object-cover"
                            >
                        </figure>

                        <div class="card-body gap-4 p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <h3 class="card-title text-xl sm:text-2xl font-semibold leading-tight text-base-content">
                                    {{ post.title || 'Без заголовка' }}
                                </h3>
                                <button
                                    v-if="canManageNews"
                                    type="button"
                                    class="btn btn-ghost btn-sm btn-square text-error shrink-0"
                                    :disabled="deleteForm.processing"
                                    title="Удалить новость"
                                    @click.prevent="deleteNews(post)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>

                            <div
                                class="news-prose prose prose-sm sm:prose-base max-w-none text-base-content/90 leading-relaxed"
                                v-html="post.content_html"
                            />

                            <div class="divider my-0" />

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/60">
                                    <DateTime :data="post.created_at" />
                                    <span v-if="post.author?.name" class="badge badge-ghost badge-sm font-normal">
                                        {{ post.author.name }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-xs">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        {{ post.views_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="join">
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item gap-1.5"
                                        :class="post.user_reaction === 'up' ? 'btn-success' : 'btn-ghost'"
                                        :disabled="Boolean(reactionsProcessing[post.id])"
                                        @click.prevent="reactToNews(post, 'up')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.278 2.122-.783 1.302-1.176 3.302-1.176 4.604 0 .589.505 1.316.783 2.122.783.92 0 1.667-.746 1.667-1.667 0-.35-.11-.676-.3-.95L13.5 2.5c-.276-.414-.736-.75-1.25-.75s-.974.336-1.25.75L7.633 7.633c-.19.274-.3.6-.3.95 0 .921.746 1.667 1.667 1.667Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25H4.5A1.5 1.5 0 0 0 3 11.75v7.5A1.5 1.5 0 0 0 4.5 20.5h9a1.5 1.5 0 0 0 1.5-1.5v-4.167" />
                                        </svg>
                                        <span>{{ post.likes_count ?? 0 }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm join-item gap-1.5"
                                        :class="post.user_reaction === 'down' ? 'btn-error' : 'btn-ghost'"
                                        :disabled="Boolean(reactionsProcessing[post.id])"
                                        @click.prevent="reactToNews(post, 'down')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15h2.25m8.024-9.75c.011.05.028.1.052.148.591 1.2.924 2.55.924 3.977a8.96 8.96 0 0 1-.924 3.977c-.024.048-.041.098-.052.148-.558 1.361-1.938 2.227-3.448 2.227H5.25a2.25 2.25 0 0 1-2.25-2.25v-5.25A2.25 2.25 0 0 1 5.25 9h6.75Z" />
                                        </svg>
                                        <span>{{ post.dislikes_count ?? 0 }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>

                    <TableEmptyState
                        v-if="posts.length === 0"
                        title="Новостей пока нет"
                        description="Когда появятся публикации, они отобразятся здесь."
                    />
                </div>

                <div class="mt-6">
                    <Pagination
                        v-if="news?.meta"
                        :model-value="news.meta.current_page"
                        :total-pages="news.meta.last_page"
                        :per-page="news.meta.per_page"
                        :total-items="news.meta.total"
                        @page-changed="changePage"
                    />
                </div>
            </template>
        </MainTableSection>
        <ConfirmModal />
    </div>
</template>
