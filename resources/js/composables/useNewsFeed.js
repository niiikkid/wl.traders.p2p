import axios from 'axios';
import { reactive, ref } from 'vue';

const minimumVisiblePart = 0.6;
const minimumVisibleMs = 1000;
const flushDelayMs = 700;

export function useNewsFeed() {
    const posts = ref([]);
    const meta = ref(null);
    const loading = ref(false);
    const loadingMore = ref(false);
    const reactionsProcessing = reactive({});
    const newsElements = new Map();
    const visibilityTimers = new Map();
    const trackedPostIds = new Set();
    const pendingPostIds = new Set();
    let intersectionObserver = null;
    let flushTimeout = null;

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
            // non-critical
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

    const loadFeed = async (page = 1) => {
        const isFirstPage = page === 1;
        if (isFirstPage) {
            loading.value = true;
        } else {
            loadingMore.value = true;
        }

        try {
            const response = await axios.get(route('news.feed'), {
                params: { page },
            });
            const payload = response?.data?.data ?? {};
            const newPosts = payload.data ?? [];

            if (isFirstPage) {
                posts.value = newPosts;
            } else {
                posts.value = [...posts.value, ...newPosts];
            }

            meta.value = payload.meta ?? null;
        } finally {
            loading.value = false;
            loadingMore.value = false;
        }
    };

    const loadMore = () => {
        if (loadingMore.value || loading.value || !meta.value) {
            return;
        }

        if (meta.value.current_page >= meta.value.last_page) {
            return;
        }

        loadFeed(meta.value.current_page + 1);
    };

    const markRead = () => {
        axios.post(route('news.mark-read')).catch(() => {
            // non-critical
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
            // non-critical
        }).finally(() => {
            reactionsProcessing[post.id] = false;
        });
    };

    const removePost = (postId) => {
        posts.value = posts.value.filter((post) => Number(post.id) !== Number(postId));
        if (meta.value) {
            meta.value = {
                ...meta.value,
                total: Math.max(0, (meta.value.total ?? 1) - 1),
            };
        }
    };

    const destroy = () => {
        window.removeEventListener('pagehide', flushTrackedViews);
        flushTrackedViews();

        visibilityTimers.forEach((timer) => {
            clearTimeout(timer);
        });
        visibilityTimers.clear();
        newsElements.clear();
        pendingPostIds.clear();
        trackedPostIds.clear();

        if (flushTimeout) {
            clearTimeout(flushTimeout);
            flushTimeout = null;
        }

        if (intersectionObserver) {
            intersectionObserver.disconnect();
            intersectionObserver = null;
        }
    };

    const initPagehide = () => {
        window.addEventListener('pagehide', flushTrackedViews);
    };

    return {
        posts,
        meta,
        loading,
        loadingMore,
        reactionsProcessing,
        loadFeed,
        loadMore,
        markRead,
        reactToNews,
        removePost,
        observeNewsElement,
        destroy,
        initPagehide,
    };
}
