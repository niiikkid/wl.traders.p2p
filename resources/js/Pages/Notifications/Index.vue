<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {nextTick, onMounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CopyPaymentText from "@/Components/CopyPaymentText.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import {playNotificationAudio} from "@/utils/notificationAudioPlayer.js";

defineOptions({layout: AuthenticatedLayout});

const SOUND_EVENT_KEYS = ['order_assigned', 'dispute_opened', 'message_received'];

const modalStore = useModalStore();
const telegramAccount = ref(usePage().props.telegramAccount ?? {});
const audioTracks = ref(usePage().props.audioTracks ?? []);
const notificationSoundSettings = ref(usePage().props.notificationSoundSettings ?? {});
const showInAppSoundSettings = ref(usePage().props.showInAppSoundSettings ?? false);

const telegramForm = useForm({});
const soundForm = useForm({
    settings: {},
});
const soundPickerDialog = ref(null);
const soundPickerEventKey = ref(null);

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

/** Подпись + тег-описание в одной строке внутри option (видно в закрытом селекте) */
const trackOptionDisplay = (track) => {
    const main = trackOptionLabel(track);
    const sub = getNamedTrackSubtitle(track);
    return sub ? `${main} — ${sub}` : main;
};

const findTrackByValue = (trackValue) => audioTracks.value.find((item) => item.value === trackValue);
const getSelectedTrackForEvent = (eventKey) => findTrackByValue(soundForm.settings[eventKey]?.track ?? null);

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
    if (!showInAppSoundSettings.value) {
        soundForm.settings = {};
        return;
    }
    soundForm.settings = normalizeSoundSettings(notificationSoundSettings.value);
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

const openSoundPicker = async (eventKey) => {
    if (!showInAppSoundSettings.value || !soundPickerDialog.value) {
        return;
    }

    soundPickerEventKey.value = eventKey;
    await nextTick();
    soundPickerDialog.value.showModal();
};

const closeSoundPicker = () => {
    if (soundPickerDialog.value?.open) {
        soundPickerDialog.value.close();
    }
    soundPickerEventKey.value = null;
};

const chooseTrackFromModal = (trackValue) => {
    if (!soundPickerEventKey.value) {
        return;
    }

    selectSoundTrack(soundPickerEventKey.value, trackValue);
    closeSoundPicker();
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

onMounted(() => {
    if (showInAppSoundSettings.value) {
        syncSoundForm();
    }
});

router.on('success', () => {
    telegramAccount.value = usePage().props.telegramAccount ?? {};
    audioTracks.value = usePage().props.audioTracks ?? [];
    notificationSoundSettings.value = usePage().props.notificationSoundSettings ?? {};
    showInAppSoundSettings.value = usePage().props.showInAppSoundSettings ?? false;
    if (showInAppSoundSettings.value) {
        syncSoundForm();
    } else {
        soundForm.settings = {};
    }
});
</script>

<template>
    <div>
        <Head title="Уведомления" />

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Уведомления</h2>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-start">
                <div v-if="showInAppSoundSettings" class="card bg-base-100 shadow xl:col-start-1 xl:row-start-1">
                    <div class="card-body space-y-3">
                        <h3 class="text-lg font-semibold">Звуковые уведомления в панели</h3>
                        <p class="text-sm text-base-content/70">
                            Здесь можно настроить звуковые уведомления для панели — они работают отдельно от уведомлений в Telegram и на них не влияют.
                        </p>

                        <div class="space-y-2">
                            <div
                                v-for="eventKey in SOUND_EVENT_KEYS"
                                :key="eventKey"
                                class="grid grid-cols-1 gap-2 rounded-box border border-base-300 px-3 py-2 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-x-6"
                            >
                                <div class="min-h-0 min-w-0">
                                    <div class="text-sm font-medium leading-snug">{{ soundEventLabels[eventKey] }}</div>
                                    <div class="text-xs text-base-content/60 truncate">
                                        {{ trackOptionDisplay(getSelectedTrackForEvent(eventKey)) || 'Звук не выбран' }}
                                    </div>
                                </div>

                                <label
                                    class="flex min-h-0 w-full max-w-full items-center justify-self-start gap-2 sm:justify-self-auto"
                                >
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-sm"
                                        :checked="soundForm.settings[eventKey]?.enabled"
                                        :disabled="soundForm.processing"
                                        @change="toggleSoundEnabled(eventKey)"
                                    />
                                    <span class="text-xs whitespace-nowrap">
                                        {{ soundForm.settings[eventKey]?.enabled ? 'Включено' : 'Выключено' }}
                                    </span>
                                </label>

                                <div class="flex min-h-0 items-center justify-self-start sm:justify-self-end">
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-outline w-fit shrink-0"
                                        :disabled="soundForm.processing || !audioTracks.length"
                                        @click.prevent="openSoundPicker(eventKey)"
                                    >
                                        Выбрать звук
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="card bg-base-100 shadow"
                    :class="showInAppSoundSettings ? 'xl:col-start-2 xl:row-start-1' : ''"
                >
                    <div class="card-body space-y-4">
                        <div v-if="!telegramAccount.is_active" class="alert alert-info text-sm">
                            Чтобы получать уведомления в Telegram, привяжите бота через ссылку ниже. После привязки уведомления включатся автоматически.
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
            </div>
        </div>

        <ConfirmModal />

        <dialog ref="soundPickerDialog" class="modal" @close="closeSoundPicker">
            <div class="modal-box flex max-h-[78vh] w-[calc(100vw-2rem)] max-w-xl flex-col gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">Выбор звука</h3>
                        <p class="text-sm text-base-content/70">
                            {{ soundPickerEventKey ? soundEventLabels[soundPickerEventKey] : '' }}
                        </p>
                    </div>
                    <button type="button" class="btn btn-sm btn-circle btn-ghost" @click="closeSoundPicker">✕</button>
                </div>

                <div v-if="!audioTracks.length" class="alert alert-info text-sm">
                    Доступные звуки не найдены.
                </div>

                <div v-else class="min-h-0 overflow-y-auto overflow-x-hidden rounded-box border border-base-300">
                    <table class="table table-xs">
                        <tbody>
                            <tr v-for="track in audioTracks" :key="track.value">
                                <td class="min-w-0 py-2">
                                    <div class="font-medium truncate">{{ trackOptionLabel(track) }}</div>
                                    <div v-if="getNamedTrackSubtitle(track)" class="text-xs text-base-content/70 truncate">
                                        {{ getNamedTrackSubtitle(track) }}
                                    </div>
                                </td>
                                <td class="w-px py-2">
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-square btn-outline"
                                        :disabled="soundForm.processing"
                                        :aria-label="`Проиграть ${trackOptionLabel(track)}`"
                                        @click.prevent="previewSoundTrack(track.value)"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        >
                                            <path d="M6.5 4.75v10.5L15 10 6.5 4.75Z" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="w-px py-2 text-right">
                                    <button
                                        type="button"
                                        class="btn btn-xs"
                                        :class="soundPickerEventKey && soundForm.settings[soundPickerEventKey]?.track === track.value ? 'btn-primary' : 'btn-ghost'"
                                        :disabled="soundForm.processing"
                                        @click.prevent="chooseTrackFromModal(track.value)"
                                    >
                                        {{ soundPickerEventKey && soundForm.settings[soundPickerEventKey]?.track === track.value ? 'Выбрано' : 'Выбрать' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button @click="closeSoundPicker">close</button>
            </form>
        </dialog>
    </div>
</template>
