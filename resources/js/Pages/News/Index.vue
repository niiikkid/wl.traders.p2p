<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, onBeforeUnmount} from 'vue';
import {EditorContent, useEditor} from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import DateTime from '@/Components/DateTime.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
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
});

const form = useForm({
    title: '',
    cover_image: null,
    content_json: null,
    visibility_type: 'all',
    visible_roles: [],
});
const modalStore = useModalStore();
const deleteForm = useForm({});

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        TextAlign.configure({
            types: ['paragraph'],
        }),
    ],
    content: '<p></p>',
});

const posts = computed(() => props.news?.data ?? []);

const insertIcon = (icon) => {
    if (!editor.value) {
        return;
    }

    editor.value.chain().focus().insertContent(`${icon} `).run();
};

const onCoverImageChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.cover_image = file;
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
            if (editor.value) {
                editor.value.commands.clearContent(true);
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

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Новости" />

        <MainTableSection title="Новости" :data="news" :paginate="true" :display-pagination="false">
            <template #header>
                <div v-if="canManageNews" class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h3 class="card-title">Новая новость</h3>
                        <div class="grid gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline" @click.prevent="insertIcon('🔥')">🔥</button>
                                <button type="button" class="btn btn-sm btn-outline" @click.prevent="insertIcon('✅')">✅</button>
                                <button type="button" class="btn btn-sm btn-outline" @click.prevent="insertIcon('📢')">📢</button>
                                <button type="button" class="btn btn-sm btn-outline" @click.prevent="insertIcon('⚠️')">⚠️</button>
                                <button type="button" class="btn btn-sm btn-outline" @click.prevent="insertIcon('💡')">💡</button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive('bold') }"
                                    @click.prevent="editor?.chain().focus().toggleBold().run()"
                                >
                                    Жирный
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive('italic') }"
                                    @click.prevent="editor?.chain().focus().toggleItalic().run()"
                                >
                                    Курсив
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive('underline') }"
                                    @click.prevent="editor?.chain().focus().toggleUnderline().run()"
                                >
                                    Подчеркнутый
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive('strike') }"
                                    @click.prevent="editor?.chain().focus().toggleStrike().run()"
                                >
                                    Зачеркнутый
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive({ textAlign: 'left' }) }"
                                    @click.prevent="editor?.chain().focus().setTextAlign('left').run()"
                                >
                                    Влево
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive({ textAlign: 'center' }) }"
                                    @click.prevent="editor?.chain().focus().setTextAlign('center').run()"
                                >
                                    Центр
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    :class="{ 'btn-active': editor?.isActive({ textAlign: 'right' }) }"
                                    @click.prevent="editor?.chain().focus().setTextAlign('right').run()"
                                >
                                    Вправо
                                </button>
                            </div>

                            <div class="space-y-2">
                                <label class="label">
                                    <span class="label-text">Заголовок новости</span>
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="Введите заголовок новости"
                                >
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <label class="label">
                                    <span class="label-text">Картинка новости (опционально)</span>
                                </label>
                                <input
                                    type="file"
                                    class="file-input file-input-bordered w-full"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    @change="onCoverImageChange"
                                >
                                <p class="text-xs text-base-content/70">
                                    Допустимые расширения: jpg, jpeg, png, webp.
                                </p>
                                <InputError :message="form.errors.cover_image" />
                            </div>

                            <div class="space-y-2">
                                <label class="label">
                                    <span class="label-text">Кто увидит новость</span>
                                </label>
                                <div class="flex flex-wrap items-center gap-4">
                                    <label class="label cursor-pointer gap-2">
                                        <input
                                            v-model="form.visibility_type"
                                            type="radio"
                                            class="radio radio-sm"
                                            value="all"
                                        >
                                        <span class="label-text">Все доступные роли</span>
                                    </label>
                                    <label class="label cursor-pointer gap-2">
                                        <input
                                            v-model="form.visibility_type"
                                            type="radio"
                                            class="radio radio-sm"
                                            value="roles"
                                        >
                                        <span class="label-text">Только выбранные роли</span>
                                    </label>
                                </div>

                                <div
                                    v-if="form.visibility_type === 'roles'"
                                    class="rounded-box border border-base-300 bg-base-100 p-3"
                                >
                                    <div class="grid sm:grid-cols-2 gap-2">
                                        <label
                                            v-for="role in newsRoleOptions"
                                            :key="role.value"
                                            class="label cursor-pointer justify-start gap-2"
                                        >
                                            <input
                                                v-model="form.visible_roles"
                                                type="checkbox"
                                                class="checkbox checkbox-sm"
                                                :value="role.value"
                                            >
                                            <span class="label-text">{{ role.label }}</span>
                                        </label>
                                    </div>
                                </div>
                                <InputError :message="form.errors.visibility_type || form.errors.visible_roles || form.errors['visible_roles.0']" />
                            </div>

                            <div class="space-y-2">
                                <label class="label">
                                    <span class="label-text">Текст новости</span>
                                </label>
                                <div class="rounded-box border border-base-300 bg-base-100 p-3 min-h-64">
                                    <EditorContent :editor="editor" class="prose max-w-none" />
                                </div>
                                <InputError :message="form.errors.content_json" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="form.processing"
                                @click.prevent="publishNews"
                            >
                                Опубликовать
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #body>
                <div class="space-y-4">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        class="card bg-base-100 shadow"
                    >
                        <div class="card-body space-y-4">
                            <figure
                                v-if="post.cover_image_url"
                                class="rounded-box border-2 border-base-300 bg-base-200 p-2 sm:p-3 flex items-center justify-center min-h-56 sm:min-h-72"
                            >
                                <img
                                    :src="post.cover_image_url"
                                    alt="Обложка новости"
                                    class="max-w-full max-h-[28rem] h-auto w-auto object-contain"
                                >
                            </figure>

                            <h3 class="text-xl sm:text-2xl font-semibold px-2 sm:px-3 leading-tight">
                                {{ post.title || 'Без заголовка' }}
                            </h3>

                            <div
                                class="prose max-w-none leading-relaxed px-2 sm:px-3"
                                v-html="post.content_html"
                            ></div>

                            <div class="pt-3 border-t border-base-300 flex items-center justify-between gap-2 text-sm text-base-content/70">
                                <div class="flex items-center gap-2">
                                    <DateTime :data="post.created_at" />
                                    <span v-if="post.author?.name" class="badge badge-ghost badge-sm">
                                        {{ post.author.name }}
                                    </span>
                                </div>
                                <button
                                    v-if="canManageNews"
                                    type="button"
                                    class="btn btn-xs btn-outline btn-error"
                                    :disabled="deleteForm.processing"
                                    @click.prevent="deleteNews(post)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </article>

                    <div v-if="posts.length === 0" class="card bg-base-100 shadow">
                        <div class="card-body text-base-content/70">
                            Пока новостей нет.
                        </div>
                    </div>
                </div>

                <div class="mt-5">
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
