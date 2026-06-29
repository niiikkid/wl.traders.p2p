<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertError from '@/Components/Alerts/AlertError.vue';
import { getUserInitials } from '@/utils/userInitials.js';

const props = defineProps({
    avatar: {
        type: Object,
        default: () => ({
            url: null,
            caption: null,
            status: null,
            error: null,
            generated_at: null,
        }),
    },
    openAiConfigured: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? {});

const avatarUrl = ref(props.avatar?.url ?? null);
const caption = ref(props.avatar?.caption ?? null);
const status = ref(props.avatar?.status ?? null);
const generationError = ref(props.avatar?.error ?? null);
const generatedAt = ref(props.avatar?.generated_at ?? null);
const processing = ref(false);
const error = ref('');
const pollingInterval = ref(null);

const hasAvatar = computed(() => Boolean(avatarUrl.value));
const isGenerating = computed(() => ['generating', 'processing'].includes(status.value));
const canGenerate = computed(() => props.openAiConfigured && !hasAvatar.value && !isGenerating.value && !processing.value);

const initials = computed(() => getUserInitials({
    email: authUser.value.email,
    login: authUser.value.login,
}));

watch(
    () => props.avatar,
    (nextAvatar) => {
        avatarUrl.value = nextAvatar?.url ?? null;
        caption.value = nextAvatar?.caption ?? null;
        status.value = nextAvatar?.status ?? null;
        generationError.value = nextAvatar?.error ?? null;
        generatedAt.value = nextAvatar?.generated_at ?? null;
    },
    { deep: true },
);

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

const refreshAvatar = () => {
    router.reload({
        only: ['avatar', 'auth'],
        preserveScroll: true,
        preserveState: true,
    });
};

const startPolling = () => {
    if (pollingInterval.value) {
        return;
    }

    pollingInterval.value = setInterval(refreshAvatar, 5000);
};

watch(isGenerating, (generating) => {
    if (generating) {
        startPolling();
        return;
    }

    stopPolling();
});

if (isGenerating.value) {
    startPolling();
}

onBeforeUnmount(stopPolling);

const resolveRequestError = (requestError) => {
    const status = requestError?.response?.status;
    const message = requestError?.response?.data?.message;

    if (typeof message === 'string' && message !== '') {
        return message;
    }

    if (status === 429) {
        return 'Слишком много попыток. Подождите минуту и попробуйте снова.';
    }

    if (status === 504 || requestError?.code === 'ECONNABORTED') {
        return 'Генерация заняла слишком много времени. Обновите страницу — аватар мог уже сохраниться.';
    }

    if (!requestError?.response) {
        return 'Нет ответа от сервера. Проверьте соединение и попробуйте снова.';
    }

    return 'Не удалось сгенерировать аватар. Попробуйте позже.';
};

const regenerateAvatar = async () => {
    if (!canGenerate.value) {
        return;
    }

    processing.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(
            route('profile.avatar.regenerate'),
            {},
            {
                timeout: 30000,
                headers: { Accept: 'application/json' },
            },
        );

        if (!data?.success || !data?.data) {
            error.value = data?.message || 'Не удалось сгенерировать аватар.';
            return;
        }

        status.value = data.data.status;
        generationError.value = null;
        startPolling();
        refreshAvatar();
    } catch (requestError) {
        error.value = resolveRequestError(requestError);
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <div class="text-left">
        <header class="space-y-1">
            <h2 class="text-base font-semibold text-base-content">Аватар профиля</h2>
            <p class="text-xs leading-relaxed text-base-content/60">
                Нажмите «Сгенерировать аватар» — он создастся на основе вашего логина и роли. Генерация запустится в фоне.
            </p>
        </header>

        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-start">
            <div class="flex flex-col items-center gap-3 sm:shrink-0">
                <div class="avatar">
                    <div class="w-24 rounded-full ring ring-primary/20 ring-offset-2 ring-offset-base-100">
                        <img
                            v-if="hasAvatar"
                            :src="avatarUrl"
                            :alt="`Аватар ${authUser.email}`"
                            class="h-24 w-24 rounded-full object-cover"
                        >
                        <div
                            v-else
                            class="flex h-24 w-24 items-center justify-center rounded-full bg-neutral text-3xl font-semibold text-neutral-content"
                        >
                            {{ initials }}
                        </div>
                    </div>
                </div>

                <PrimaryButton
                    v-if="!hasAvatar"
                    type="button"
                    class="btn-sm"
                    :disabled="!canGenerate"
                    @click="regenerateAvatar"
                >
                    <span v-if="processing" class="loading loading-spinner loading-xs" />
                    {{ processing ? 'Запускаем…' : 'Сгенерировать аватар' }}
                </PrimaryButton>

                <div v-else class="badge badge-success badge-outline">
                    Аватар создан
                </div>
            </div>

            <div class="min-w-0 flex-1 space-y-3">
                <div v-if="isGenerating" class="rounded-2xl border border-primary/20 bg-primary/5 p-4 text-sm text-base-content/80">
                    Аватар генерируется в фоне. Он появится здесь, когда будет готов. Страницу можно закрыть.
                </div>

                <div v-if="caption" class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                        Про ваш ник
                    </div>
                    <p class="mt-2 text-base font-medium leading-snug text-base-content">
                        {{ caption }}
                    </p>
                </div>

                <p v-else-if="!isGenerating" class="text-sm text-base-content/60">
                    Здесь будет описание вашего аватара.
                </p>

                <AlertError
                    v-if="generationError && !isGenerating && !hasAvatar"
                    :message="`Не удалось сгенерировать аватар: ${generationError}`"
                />

                <p v-if="!openAiConfigured" class="text-xs text-base-content/50">
                    OpenAI не настроен. Обратитесь к администратору.
                </p>

                <AlertError v-if="error" :message="error" />
            </div>
        </div>
    </div>
</template>
