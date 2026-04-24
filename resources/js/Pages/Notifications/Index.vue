<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed, onMounted, ref, watch} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from "@/Components/InputError.vue";
import CopyPaymentText from "@/Components/CopyPaymentText.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import {playNotificationAudio} from "@/utils/notificationAudioPlayer.js";

defineOptions({layout: AuthenticatedLayout});

const SOUND_EVENT_KEYS = ['order_assigned', 'dispute_opened', 'message_received'];

const modalStore = useModalStore();
const rules = ref(usePage().props.rules ?? []);
const filtersVariants = ref(usePage().props.filtersVariants ?? {event: [], currency: [], message_scope: []});
const telegramAccount = ref(usePage().props.telegramAccount ?? {});
const audioTracks = ref(usePage().props.audioTracks ?? []);
const notificationSoundSettings = ref(usePage().props.notificationSoundSettings ?? {});

const ruleForm = useForm({
    event: '',
    currency: '',
    min_amount: '',
    message_scope: 'all',
    enabled: true,
});
const ruleActionForm = useForm({
    enabled: false,
});
const telegramForm = useForm({});
const soundForm = useForm({
    settings: {},
});

const eventLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.event ?? []).map((item) => [item.value, item.name]));
});

const messageScopeLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.message_scope ?? []).map((item) => [item.value, item.name]));
});

const soundEventLabels = {
    order_assigned: 'Новая сделка',
    dispute_opened: 'Новый спор',
    message_received: 'Новое сообщение',
};

/** Убираем расширение из подписи в интерфейсе */
const formatTrackName = (trackName = '') => trackName.replace(/\.mp3$/i, '');

const namedTrackSubtitles = {
    'DreamsAreMessagesFromTheDeep.mp3': 'Мечты — послания из глубины',
    'LetWealthCome.mp3': 'Пусть приходит богатство',
    'Loshadka-1.mp3': 'Лошадка — версия 1',
    'Loshadka-2.mp3': 'Лошадка — версия 2',
    'MoneyPowerWomanDrugs.mp3': 'Деньги, власть, женщины, наркотики',
    'Pressure.mp3': 'Давление',
    'SixDays.mp3': 'Шесть дней',
    'radwimps.mp3': 'Судзумэ, закрывающая двери',
};

const getNamedTrackSubtitle = (track) => namedTrackSubtitles[track?.value] ?? '';

const trackOptionLabel = (track) => formatTrackName(track?.name ?? '');

const findTrackByValue = (trackValue) => audioTracks.value.find((item) => item.value === trackValue);

const selectedTrackSubtitle = (eventKey) => {
    const track = findTrackByValue(soundForm.settings[eventKey]?.track);
    return getNamedTrackSubtitle(track);
};

const isMessageEvent = computed(() => ruleForm.event === 'message.received');
const showMinAmountFilter = computed(() => {
    return ruleForm.event !== 'withdrawal.requested' && !isMessageEvent.value;
});
const showCurrencyFilter = computed(() => {
    return ruleForm.event !== 'withdrawal.requested'
        && ruleForm.event !== 'trust.balance.low'
        && !isMessageEvent.value;
});
const isTrustBalanceLowEvent = computed(() => ruleForm.event === 'trust.balance.low');

const hasRuleAmount = (rule) => {
    return rule?.min_amount !== null && rule?.min_amount !== '' || rule?.currency !== null && rule?.currency !== '';
};

const ruleAmountLabel = (rule) => {
    const parts = [];

    if (rule?.min_amount !== null && rule?.min_amount !== '') {
        parts.push(`от ${rule.min_amount}`);
    }

    if (rule?.currency !== null && rule?.currency !== '') {
        parts.push(rule.currency.toUpperCase());
    }

    return parts.join(' ');
};

const telegramAlertText = computed(() => {
    if (telegramAccount.value?.is_active) {
        return 'Бот привязан к вашему аккаунту. При необходимости вы можете отвязать его здесь.';
    }

    return 'Чтобы получать уведомления в Telegram, привяжите бота через ссылку ниже.';
});

const buildDefaultSoundSettings = () => {
    const defaultTrack = audioTracks.value[0]?.value ?? null;

    return {
        order_assigned: {enabled: true, track: defaultTrack},
        dispute_opened: {enabled: true, track: defaultTrack},
        message_received: {enabled: true, track: defaultTrack},
    };
};

const normalizeSoundSettings = (settings = {}) => {
    const defaults = buildDefaultSoundSettings();
    const normalized = {};

    for (const key of SOUND_EVENT_KEYS) {
        const current = settings?.[key] ?? {};
        normalized[key] = {
            enabled: current.enabled ?? defaults[key].enabled,
            track: current.track ?? defaults[key].track,
        };
    }

    return normalized;
};

const syncSoundForm = () => {
    soundForm.settings = normalizeSoundSettings(notificationSoundSettings.value);
};

const initRuleDefaults = () => {
    if (!ruleForm.event && (filtersVariants.value.event ?? []).length) {
        ruleForm.event = filtersVariants.value.event[0].value;
    }

    if (!ruleForm.message_scope && (filtersVariants.value.message_scope ?? []).length) {
        ruleForm.message_scope = filtersVariants.value.message_scope[0].value;
    }
};

const createRule = () => {
    ruleForm.post(route('notifications.rules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (showMinAmountFilter.value) {
                ruleForm.reset('min_amount');
            } else {
                ruleForm.reset('currency', 'min_amount');
            }
        },
    });
};

const toggleRule = (rule) => {
    ruleActionForm.enabled = !rule.enabled;
    ruleActionForm.patch(route('notifications.rules.update', rule.id), {
        preserveScroll: true,
    });
};

const deleteRule = (rule) => {
    ruleActionForm.delete(route('notifications.rules.destroy', rule.id), {
        preserveScroll: true,
    });
};

const saveSoundSettings = () => {
    soundForm.patch(route('notifications.sound.update'), {
        preserveScroll: true,
    });
};

const toggleSoundEnabled = (eventKey) => {
    soundForm.settings[eventKey].enabled = !soundForm.settings[eventKey].enabled;
    saveSoundSettings();
};

const selectSoundTrack = (eventKey, trackValue) => {
    soundForm.settings[eventKey].track = trackValue;
    saveSoundSettings();
};

const previewSoundTrack = (trackValue) => {
    const track = audioTracks.value.find((item) => item.value === trackValue);
    if (!track) {
        return;
    }

    playNotificationAudio(track.url, {interrupt: true});
};

const refreshTelegramLink = () => {
    telegramForm.post(route('notifications.telegram.link'), {
        preserveScroll: true,
    });
};

const unlinkTelegram = () => {
    modalStore.openConfirmModal({
        title: 'Отвязать Telegram-бота от вашего аккаунта?',
        confirm_button_name: 'Отвязать',
        confirm: () => {
            telegramForm.post(route('notifications.telegram.unlink'), {
                preserveScroll: true,
            });
        },
    });
};

watch(() => ruleForm.event, (value) => {
    if (value === 'withdrawal.requested') {
        ruleForm.currency = '';
        ruleForm.min_amount = '';
        return;
    }

    if (value === 'trust.balance.low') {
        ruleForm.currency = '';
    }

    if (value === 'message.received' && !ruleForm.message_scope) {
        ruleForm.message_scope = filtersVariants.value.message_scope?.[0]?.value ?? 'all';
    }

    if (value === 'message.received') {
        ruleForm.currency = '';
        ruleForm.min_amount = '';
    }
});

onMounted(() => {
    initRuleDefaults();
    syncSoundForm();
});

router.on('success', () => {
    rules.value = usePage().props.rules ?? [];
    filtersVariants.value = usePage().props.filtersVariants ?? {event: [], currency: [], message_scope: []};
    telegramAccount.value = usePage().props.telegramAccount ?? {};
    audioTracks.value = usePage().props.audioTracks ?? [];
    notificationSoundSettings.value = usePage().props.notificationSoundSettings ?? {};
    initRuleDefaults();
    syncSoundForm();
});
</script>

<template>
    <div>
        <Head title="Уведомления" />

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Уведомления</h2>
            </div>

            <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
                <div class="card bg-base-100 shadow lg:col-span-2">
                    <div class="card-body space-y-4">
                        <h3 class="text-lg font-semibold">Звуковые уведомления в панели</h3>
                        <p class="text-sm text-base-content/70">
                            Настраивается отдельно от правил Telegram. Если открыто несколько вкладок, звук проигрывается только в одной.
                        </p>

                        <div class="space-y-3">
                            <div
                                v-for="eventKey in SOUND_EVENT_KEYS"
                                :key="eventKey"
                                class="flex flex-col gap-3 rounded-box border border-base-300 p-3 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="space-y-1">
                                    <div class="font-medium">{{ soundEventLabels[eventKey] }}</div>
                                    <div class="text-xs text-base-content/70">
                                        Звук не наслаивается: пока один трек играет, новый не запускается.
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="toggle toggle-sm"
                                            :checked="soundForm.settings[eventKey]?.enabled"
                                            :disabled="soundForm.processing"
                                            @change="toggleSoundEnabled(eventKey)"
                                        />
                                        <span class="text-sm">
                                            {{ soundForm.settings[eventKey]?.enabled ? 'Включено' : 'Выключено' }}
                                        </span>
                                    </label>

                                    <div class="flex min-w-0 flex-col gap-0.5 sm:max-w-sm">
                                        <select
                                            class="select select-bordered select-sm min-w-0 w-full"
                                            :value="soundForm.settings[eventKey]?.track ?? ''"
                                            :disabled="soundForm.processing || !audioTracks.length"
                                            @change="selectSoundTrack(eventKey, $event.target.value)"
                                        >
                                            <option disabled value="">Выберите звук</option>
                                            <option v-for="track in audioTracks" :key="track.value" :value="track.value">
                                                {{ trackOptionLabel(track) }}
                                            </option>
                                        </select>
                                        <span
                                            v-if="selectedTrackSubtitle(eventKey)"
                                            class="truncate text-[11px] text-base-content/60"
                                        >
                                            {{ selectedTrackSubtitle(eventKey) }}
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        :disabled="!soundForm.settings[eventKey]?.track"
                                        @click.prevent="previewSoundTrack(soundForm.settings[eventKey]?.track)"
                                    >
                                        Проверить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
      
                <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div
                        class="alert text-sm"
                        :class="telegramAccount.is_active ? 'alert-success' : 'alert-info'"
                    >
                        {{ telegramAlertText }}
                    </div>
                    <h3 class="text-lg font-semibold">Telegram</h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="badge" :class="telegramAccount.is_active ? 'badge-success' : 'badge-warning'">
                                {{ telegramAccount.is_active ? 'Привязан' : 'Не привязан' }}
                            </span>
                            <span v-if="telegramAccount.bot_username" class="text-sm text-base-content/70">
                                @{{ telegramAccount.bot_username }}
                            </span>
                        </div>
                        <div v-if="!telegramAccount.is_active && telegramAccount.start_link" class="flex flex-wrap items-center gap-3">
                            <a
                                class="btn btn-sm btn-outline"
                                :href="telegramAccount.start_link"
                                target="_blank"
                                rel="noopener"
                            >
                                Открыть Telegram
                            </a>
                            <CopyPaymentText text="Скопировать ссылку" :copy_text="telegramAccount.start_link" />
                        </div>
                        <div v-else-if="!telegramAccount.is_active" class="text-sm text-base-content/70">
                            Укажите `TELEGRAM_BOT_NAME`, чтобы сформировать ссылку привязки.
                        </div>
                    </div>
                    <button
                        v-if="telegramAccount.is_active"
                        type="button"
                        class="btn btn-sm btn-outline btn-error"
                        :disabled="telegramForm.processing"
                        @click.prevent="unlinkTelegram"
                    >
                        Отвязать бота
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="telegramForm.processing"
                        @click.prevent="refreshTelegramLink"
                    >
                        Обновить ссылку
                    </button>
                </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <h3 class="text-lg font-semibold">Новое правило</h3>
                        <p class="text-sm text-base-content/70">
                            Канал доставки всегда Telegram.
                        </p>
                        <div class="grid gap-3">
                            <div>
                                <label class="label">
                                    <span class="label-text">Событие</span>
                                </label>
                                <select v-model="ruleForm.event" class="select select-bordered w-full">
                                    <option disabled value="">Выберите событие</option>
                                    <option v-for="event in filtersVariants.event" :key="event.value" :value="event.value">
                                        {{ event.name }}
                                    </option>
                                </select>
                                <InputError :message="ruleForm.errors.event" />
                            </div>
                            <div v-if="isMessageEvent">
                                <label class="label">
                                    <span class="label-text">Условие для сообщений</span>
                                </label>
                                <select v-model="ruleForm.message_scope" class="select select-bordered w-full">
                                    <option v-for="scope in filtersVariants.message_scope" :key="scope.value" :value="scope.value">
                                        {{ scope.name }}
                                    </option>
                                </select>
                                <InputError :message="ruleForm.errors.message_scope" />
                            </div>
                            <div v-if="showCurrencyFilter">
                                <label class="label">
                                    <span class="label-text">Валюта (опционально)</span>
                                </label>
                                <select v-model="ruleForm.currency" class="select select-bordered w-full">
                                    <option value="">Любая</option>
                                    <option v-for="currency in filtersVariants.currency" :key="currency.value" :value="currency.value">
                                        {{ currency.name }}
                                    </option>
                                </select>
                                <InputError :message="ruleForm.errors.currency" />
                            </div>
                            <div v-if="showMinAmountFilter">
                                <label class="label">
                                    <span class="label-text">{{ isTrustBalanceLowEvent ? 'Порог траст-баланса (USDT)' : 'Мин. сумма (опционально)' }}</span>
                                </label>
                                <input
                                    v-model="ruleForm.min_amount"
                                    type="text"
                                    class="input input-bordered w-full"
                                    placeholder="Например, 100"
                                />
                                <InputError :message="ruleForm.errors.min_amount" />
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="ruleForm.processing"
                            @click.prevent="createRule"
                        >
                            Создать правило
                        </button>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="text-lg font-semibold mb-4">Правила</h3>
                    <div v-if="!rules.length" class="text-sm text-base-content/70">
                        Пока что правил нет.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="rule in rules"
                            :key="rule.id"
                            class="flex flex-wrap items-center justify-between gap-3 border border-base-300 rounded-box p-3"
                        >
                            <div class="space-y-1">
                                <div class="font-medium">{{ eventLabels[rule.event] ?? rule.event }}</div>
                                <div class="flex flex-wrap gap-2 text-xs text-base-content/70">
                                    <span class="badge badge-ghost badge-xs">
                                        Telegram
                                    </span>
                                    <span v-if="rule.message_scope" class="badge badge-outline badge-xs">
                                        {{ messageScopeLabels[rule.message_scope] ?? rule.message_scope }}
                                    </span>
                                    <span v-if="hasRuleAmount(rule)" class="badge badge-outline badge-xs">
                                        {{ ruleAmountLabel(rule) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-sm"
                                        :checked="rule.enabled"
                                        :disabled="ruleActionForm.processing"
                                        @change="toggleRule(rule)"
                                    />
                                    <span class="text-sm">{{ rule.enabled ? 'Включено' : 'Выключено' }}</span>
                                </label>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline btn-error"
                                    :disabled="ruleActionForm.processing"
                                    @click.prevent="deleteRule(rule)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal />
    </div>
</template>
