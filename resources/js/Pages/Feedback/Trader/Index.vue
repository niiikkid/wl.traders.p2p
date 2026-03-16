<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination/Pagination.vue';
import DateTime from '@/Components/DateTime.vue';

const props = defineProps({
    feed: {
        type: Object,
        required: true,
    },
    myFeedbacks: {
        type: Object,
        required: true,
    },
    canModerate: {
        type: Boolean,
        required: true,
    },
    showHidden: {
        type: Boolean,
        required: true,
    },
    hiddenCount: {
        type: Number,
        required: true,
    },
    feedbackCooldownEndsAt: {
        type: String,
        required: false,
        default: null,
    },
});

const maxContentLength = 1000;

const form = useForm({
    content: '',
});

const contentLength = computed(() => form.content.length);
const feedList = computed(() => props.feed?.data ?? []);
const myList = computed(() => props.myFeedbacks?.data ?? []);
const hasAnyVisible = computed(() => feedList.value.length > 0 || myList.value.length > 0);
const feedbackIndexRouteName = computed(() => (props.canModerate ? 'admin.feedback.index' : 'trader.feedback.index'));
const nowTimestamp = ref(Date.now());

const cooldownEndTimestamp = computed(() => {
    if (!props.feedbackCooldownEndsAt) {
        return null;
    }

    const parsedTimestamp = Date.parse(props.feedbackCooldownEndsAt);
    return Number.isNaN(parsedTimestamp) ? null : parsedTimestamp;
});

const cooldownRemainingSeconds = computed(() => {
    if (!cooldownEndTimestamp.value) {
        return 0;
    }

    return Math.max(0, Math.ceil((cooldownEndTimestamp.value - nowTimestamp.value) / 1000));
});

const cooldownActive = computed(() => cooldownRemainingSeconds.value > 0);
const cooldownMinutes = computed(() => Math.floor(cooldownRemainingSeconds.value / 60));
const cooldownSeconds = computed(() => cooldownRemainingSeconds.value % 60);

let cooldownIntervalId = null;

onMounted(() => {
    cooldownIntervalId = window.setInterval(() => {
        nowTimestamp.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (cooldownIntervalId !== null) {
        window.clearInterval(cooldownIntervalId);
    }
});

const submit = () => {
    form.post(route('trader.feedback.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const toggleFavorite = (feedback) => {
    if (!props.canModerate) {
        return;
    }

    router.patch(
        route('admin.feedback.favorite', feedback.id),
        {enabled: !feedback.is_starred},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const toggleHidden = (feedback) => {
    if (!props.canModerate) {
        return;
    }

    router.patch(
        route('admin.feedback.hidden', feedback.id),
        {enabled: !feedback.is_hidden},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const toggleShowHidden = () => {
    if (!props.canModerate) {
        return;
    }

    router.get(
        route(feedbackIndexRouteName.value),
        {
            show_hidden: props.showHidden ? '0' : '1',
            feed_page: 1,
            my_page: 1,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const changeFeedPage = (pageNumber) => {
    router.get(
        route(feedbackIndexRouteName.value),
        {
            show_hidden: props.showHidden ? '1' : '0',
            feed_page: pageNumber,
            my_page: props.myFeedbacks?.meta?.current_page ?? 1,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const changeMyPage = (pageNumber) => {
    router.get(
        route(feedbackIndexRouteName.value),
        {
            show_hidden: props.showHidden ? '1' : '0',
            feed_page: props.feed?.meta?.current_page ?? 1,
            my_page: pageNumber,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Обратная связь" />

        <MainTableSection title="Обратная связь" :data="feed" :paginate="true" :display-pagination="false">
            <template #header>
                <div class="space-y-4">
                    <div v-if="!canModerate" class="card bg-base-100 shadow">
                        <div class="card-body space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="card-title">Новый фидбек</h2>
                                <span class="badge badge-outline">
                                    {{ contentLength }}/{{ maxContentLength }}
                                </span>
                            </div>

                            <form @submit.prevent="submit" class="space-y-3">
                                <textarea
                                    v-model="form.content"
                                    class="textarea textarea-bordered w-full min-h-32"
                                    maxlength="1000"
                                    placeholder="Опишите баг, предложение или идею по улучшению."
                                />
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs text-base-content/60">
                                        Максимум 1000 символов.
                                    </p>
                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-sm"
                                        :disabled="form.processing || cooldownActive || !form.content.trim().length"
                                    >
                                        Отправить
                                    </button>
                                </div>
                                <div v-if="cooldownActive" class="alert alert-warning py-2">
                                    <span class="text-xs sm:text-sm">
                                        Следующее сообщение можно отправить через
                                        <span class="ml-1 inline-flex items-center gap-1 font-mono">
                                            <span class="countdown">
                                                <span
                                                    :style="{'--value': cooldownMinutes}"
                                                    :aria-label="String(cooldownMinutes)"
                                                    aria-live="polite"
                                                >
                                                    {{ cooldownMinutes }}
                                                </span>
                                            </span>
                                            :
                                            <span class="countdown">
                                                <span
                                                    :style="{'--value': cooldownSeconds}"
                                                    :aria-label="String(cooldownSeconds)"
                                                    aria-live="polite"
                                                >
                                                    {{ cooldownSeconds }}
                                                </span>
                                            </span>
                                        </span>
                                        <span class="sr-only">
                                            {{ cooldownMinutes }} минут {{ cooldownSeconds }} секунд
                                        </span>
                                    </span>
                                </div>
                                <InputError :message="form.errors.content" />
                            </form>
                        </div>
                    </div>

                    <div v-if="canModerate" class="flex items-center justify-between gap-2">
                        <label class="label cursor-pointer gap-2">
                            <span class="label-text">Показывать скрытые</span>
                            <input
                                type="checkbox"
                                class="toggle toggle-sm toggle-primary"
                                :checked="showHidden"
                                @change="toggleShowHidden"
                            >
                        </label>
                        <span class="text-xs text-base-content/60">
                            Скрыто: {{ hiddenCount }}
                        </span>
                    </div>

                    <div v-if="canModerate && !showHidden && !hasAnyVisible && hiddenCount > 0" class="alert alert-info">
                        <span>Все записи скрыты. Включите «Показывать скрытые», чтобы просмотреть их.</span>
                    </div>
                </div>
            </template>

            <template #body>
                <div class="space-y-6" v-if="canModerate">
                    <section class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Лента обратной связи</h3>
                            <span class="text-sm text-base-content/60">
                                {{ feed?.meta?.total ?? 0 }} записей
                            </span>
                        </div>

                        <div class="space-y-3">
                            <article
                                v-for="feedback in feedList"
                                :key="`feed-${feedback.id}`"
                                class="card bg-base-100 shadow"
                            >
                                <div class="card-body">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span v-if="feedback.author?.login" class="text-sm font-semibold">{{ feedback.author.login }}</span>
                                            <span v-if="feedback.is_own" class="badge badge-primary badge-sm">Вы</span>
                                            <span v-if="feedback.is_hidden" class="badge badge-warning badge-sm">Скрыт</span>
                                        </div>
                                        <DateTime :data="feedback.created_at" simple class="justify-start text-sm" />
                                    </div>

                                    <p class="whitespace-pre-wrap break-words text-sm mb-2">{{ feedback.content }}</p>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-xs"
                                            :class="feedback.is_starred ? 'btn-warning' : 'btn-outline'"
                                            @click="toggleFavorite(feedback)"
                                        >
                                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M11.48 3.5a.75.75 0 0 1 1.04 0l2.76 2.65 3.8.55a.75.75 0 0 1 .42 1.28l-2.75 2.68.65 3.79a.75.75 0 0 1-1.08.79L12 13.76l-3.4 1.79a.75.75 0 0 1-1.09-.79l.66-3.79-2.76-2.68a.75.75 0 0 1 .42-1.28l3.8-.55L11.48 3.5Z" />
                                            </svg>
                                            <span>{{ feedback.is_starred ? 'Убрать звезду' : 'В избранное' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline"
                                            @click="toggleHidden(feedback)"
                                        >
                                            {{ feedback.is_hidden ? 'Убрать скрытие' : 'Скрыть' }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                            <div v-if="feedList.length === 0" class="text-sm text-base-content/60">
                                Пока нет записей.
                            </div>
                        </div>

                        <Pagination
                            v-if="feed?.meta"
                            :model-value="feed.meta.current_page"
                            :total-pages="feed.meta.last_page"
                            :per-page="feed.meta.per_page"
                            :total-items="feed.meta.total"
                            @page-changed="changeFeedPage"
                        />
                    </section>
                </div>

                <div class="space-y-3" v-else>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Мои фидбеки</h3>
                        <span class="text-sm text-base-content/60">
                            {{ feed?.meta?.total ?? 0 }} записей
                        </span>
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="feedback in feedList"
                            :key="`trader-feed-${feedback.id}`"
                            class="card bg-base-100 shadow"
                        >
                            <div class="card-body space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <DateTime :data="feedback.created_at" simple class="justify-start text-sm" />
                                </div>
                                <p class="whitespace-pre-wrap break-words text-sm">{{ feedback.content }}</p>
                            </div>
                        </article>
                        <div v-if="feedList.length === 0" class="text-sm text-base-content/60">
                            Вы пока не отправляли фидбек.
                        </div>
                    </div>

                    <Pagination
                        v-if="feed?.meta"
                        :model-value="feed.meta.current_page"
                        :total-pages="feed.meta.last_page"
                        :per-page="feed.meta.per_page"
                        :total-items="feed.meta.total"
                        @page-changed="changeFeedPage"
                    />
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
