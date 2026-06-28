<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import NewsFeedPanel from '@/Components/News/NewsFeedPanel.vue';
import { useMenuCounters } from '@/composables/useMenuCounters.js';
import { useNewsFeed } from '@/composables/useNewsFeed.js';

const emit = defineEmits(['open-create']);

const page = usePage();
const { menu } = useMenuCounters();
const rootRef = ref(null);
const isOpen = ref(false);
const hasLoaded = ref(false);

const canManage = computed(() => page.props.news?.canManage === true);
const unreadCount = computed(() => Number(menu.value.newsUnreadCount ?? 0));
const unreadBadge = computed(() => {
    if (unreadCount.value <= 0) {
        return null;
    }

    return unreadCount.value > 9 ? '9+' : String(unreadCount.value);
});

const {
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
} = useNewsFeed();

const close = () => {
    isOpen.value = false;
};

const toggle = async () => {
    isOpen.value = !isOpen.value;

    if (!isOpen.value) {
        return;
    }

    if (!hasLoaded.value) {
        await loadFeed();
        hasLoaded.value = true;
    }

    markRead();
    menu.value = {
        ...menu.value,
        newsUnreadCount: 0,
    };
};

const refreshFeed = async () => {
    hasLoaded.value = false;
    if (isOpen.value) {
        await loadFeed();
        hasLoaded.value = true;
    }
};

const onClickOutside = (event) => {
    if (!isOpen.value) {
        return;
    }

    if (rootRef.value && !rootRef.value.contains(event.target)) {
        close();
    }
};

const onDeleted = (postId) => {
    removePost(postId);
};

defineExpose({ refreshFeed });

onMounted(() => {
    initPagehide();
    document.addEventListener('click', onClickOutside);
    router.on('success', () => {
        menu.value = usePage().props.menu ?? {};
    });
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    destroy();
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            class="btn btn-ghost btn-sm btn-square indicator"
            :class="{ 'bg-base-300/60': isOpen }"
            :aria-expanded="isOpen"
            aria-haspopup="true"
            title="Новости"
            @click.stop="toggle"
        >
            <span class="sr-only">Новости</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 opacity-80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
            </svg>
            <span
                v-if="unreadBadge"
                class="indicator-item badge badge-primary badge-xs top-1 right-1 min-w-4 px-1"
            >
                {{ unreadBadge }}
            </span>
        </button>

        <div
            v-show="isOpen"
            class="absolute right-0 top-full z-[60] mt-2 w-[min(34rem,calc(100vw-2rem))] rounded-box border border-base-300 bg-base-100 shadow-xl"
            @click.stop
        >
            <NewsFeedPanel
                :posts="posts"
                :meta="meta"
                :loading="loading"
                :loading-more="loadingMore"
                :reactions-processing="reactionsProcessing"
                :can-manage="canManage"
                :observe-news-element="observeNewsElement"
                @react="reactToNews"
                @load-more="loadMore"
                @deleted="onDeleted"
            />
        </div>
    </div>
</template>
