<script setup>
import {Head, router} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, reactive} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import DateTime from '@/Components/DateTime.vue';

const props = defineProps({
    news: {
        type: Object,
        required: true,
    },
});

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

const posts = computed(() => props.news?.data ?? []);

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
});

onMounted(() => {
    window.addEventListener('pagehide', flushTrackedViews);
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Новости" />

        <MainTableSection title="Новости" :data="news" :paginate="true" :display-pagination="false">
            <template #body>
                <div class="space-y-4">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        :ref="(element) => observeNewsElement(element, post.id)"
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <DateTime :data="post.created_at" />
                                    <span v-if="post.author?.name" class="badge badge-ghost badge-sm">
                                        {{ post.author.name }}
                                    </span>
                                    <span class="badge badge-outline badge-sm">
                                        👁 {{ post.views_count ?? 0 }}
                                    </span>
                                    <button
                                        type="button"
                                        class="btn btn-xs"
                                        :class="post.user_reaction === 'up' ? 'btn-success' : 'btn-outline'"
                                        :disabled="Boolean(reactionsProcessing[post.id])"
                                        @click.prevent="reactToNews(post, 'up')"
                                    >
                                        👍 {{ post.likes_count ?? 0 }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-xs"
                                        :class="post.user_reaction === 'down' ? 'btn-error' : 'btn-outline'"
                                        :disabled="Boolean(reactionsProcessing[post.id])"
                                        @click.prevent="reactToNews(post, 'down')"
                                    >
                                        👎 {{ post.dislikes_count ?? 0 }}
                                    </button>
                                </div>
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
    </div>
</template>
