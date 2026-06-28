<script setup>
import { computed } from 'vue';
import axios from 'axios';
import DateTime from '@/Components/DateTime.vue';
import TableEmptyState from '@/Components/TableEmptyState.vue';
import { useModalStore } from '@/store/modal.js';

const props = defineProps({
    posts: {
        type: Array,
        required: true,
    },
    meta: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    loadingMore: {
        type: Boolean,
        default: false,
    },
    reactionsProcessing: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    observeNewsElement: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['react', 'load-more', 'deleted']);

const modalStore = useModalStore();
const hasMore = computed(() => {
    if (!props.meta) {
        return false;
    }

    return props.meta.current_page < props.meta.last_page;
});

const deleteNews = (post) => {
    modalStore.openConfirmModal({
        title: 'Удалить новость?',
        body: 'Новость и картинка будут удалены без возможности восстановления.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            axios.delete(route('admin.news.destroy', post.id), {
                headers: { Accept: 'application/json' },
            })
                .then(() => {
                    emit('deleted', post.id);
                })
                .catch(() => {
                    // non-critical
                });
        },
    });
};
</script>

<template>
    <div class="flex max-h-[min(32rem,calc(100dvh-6rem))] flex-col">
        <div class="shrink-0 border-b border-base-300/60 px-4 py-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-base-content">Новости</h3>
                <span v-if="meta?.total" class="badge badge-ghost badge-xs">
                    {{ meta.total }}
                </span>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3 [scrollbar-gutter:stable_both-edges]">
            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <div v-else class="flex flex-col gap-3">
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
                            class="w-full max-h-40 object-cover"
                        >
                    </figure>

                    <div class="card-body gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="text-base font-semibold leading-snug text-base-content">
                                {{ post.title || 'Без заголовка' }}
                            </h4>
                            <button
                                v-if="canManage"
                                type="button"
                                class="btn btn-ghost btn-xs btn-square text-error shrink-0"
                                title="Удалить новость"
                                @click.prevent="deleteNews(post)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>

                        <div
                            class="news-prose prose prose-sm max-w-none text-base-content/90 leading-relaxed"
                            v-html="post.content_html"
                        />

                        <div class="divider my-0" />

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                                <DateTime :data="post.created_at" />
                                <span v-if="post.author?.name" class="badge badge-ghost badge-xs font-normal">
                                    {{ post.author.name }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    {{ post.views_count ?? 0 }}
                                </span>
                            </div>

                            <div class="join">
                                <button
                                    type="button"
                                    class="btn btn-xs join-item gap-1"
                                    :class="post.user_reaction === 'up' ? 'btn-success' : 'btn-ghost'"
                                    :disabled="Boolean(reactionsProcessing[post.id])"
                                    @click.prevent="emit('react', post, 'up')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9 9 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.5 4.5 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75a2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218c-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715q.068.633.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48a4.5 4.5 0 0 1-1.423-.23l-3.114-1.04a4.5 4.5 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5q.125.307.27.602c.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.96 8.96 0 0 0-1.302 4.665a9 9 0 0 0 .654 3.375" />
                                    </svg>
                                    <span>{{ post.likes_count ?? 0 }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-xs join-item gap-1"
                                    :class="post.user_reaction === 'down' ? 'btn-error' : 'btn-ghost'"
                                    :disabled="Boolean(reactionsProcessing[post.id])"
                                    @click.prevent="emit('react', post, 'down')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715a12 12 0 0 1-.068-1.285c0-2.848.992-5.464 2.649-7.521C5.287 4.247 5.886 4 6.504 4h4.016a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23h1.294M7.499 15.25c.618 0 .991.724.725 1.282A7.5 7.5 0 0 0 7.5 19.75A2.25 2.25 0 0 0 9.75 22a.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672c.304-.76.93-1.33 1.653-1.715a9 9 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384m-10.253 1.5H9.7m8.075-9.75q.015.075.05.148a8.95 8.95 0 0 1 .925 3.977a8.95 8.95 0 0 1-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368c.339 1.11.521 2.287.521 3.507c0 1.553-.295 3.036-.831 4.398c-.306.774-1.086 1.227-1.918 1.227h-1.053c-.472 0-.745-.556-.5-.96a9 9 0 0 0 .303-.54" />
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

                <button
                    v-if="hasMore"
                    type="button"
                    class="btn btn-ghost btn-sm w-full"
                    :disabled="loadingMore"
                    @click.prevent="emit('load-more')"
                >
                    <span v-if="loadingMore" class="loading loading-spinner loading-xs" />
                    <span>{{ loadingMore ? 'Загрузка…' : 'Показать ещё' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
